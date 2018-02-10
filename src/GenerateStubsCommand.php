<?php
namespace StubsGenerator;

use ArrayIterator;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;

/**
 * The command to generate stubs from the CLI.
 */
class GenerateStubsCommand extends Command
{
    /**
     * @var (string|int)[][]
     * @psalm-var array<int,array{0: string, 1: string, 2: int}>
     */
    private const SYMBOL_OPTIONS = [
        ['functions', StubsGenerator::FUNCTIONS],
        ['classes', StubsGenerator::CLASSES],
        ['interfaces', StubsGenerator::INTERFACES],
        ['traits', StubsGenerator::TRAITS],
        ['documented-globals', StubsGenerator::DOCUMENTED_GLOBALS],
        ['undocumented-globals', StubsGenerator::UNDOCUMENTED_GLOBALS],
        ['globals', StubsGenerator::GLOBALS],
    ];

    /**
     * @psalm-suppress PropertyNotSetInConstructor
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @psalm-suppress PropertyNotSetInConstructor
     * @var string|null
     */
    private $outFile;

    /** @var bool */
    private $confirmedOverwrite = false;

    public function configure(): void
    {
        $this->setName('run')
            ->setDescription('Generates stubs for the PHP files in the given sources')
            ->addArgument('sources', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The sources from which to generate stubs.  Either directories or specific files.  At least one must be specified.')
            ->addOption('out', null, InputOption::VALUE_OPTIONAL, 'Path to a file to write pretty-printed stubs to.  If unset, stubs will be written to stdout.');

        foreach (self::SYMBOL_OPTIONS as $opt) {
            $this->addOption($opt[0], null, InputOption::VALUE_NONE, "Include declarations for {$opt[0]}");
        }
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->filesystem = new Filesystem();
        $out = $input->getOption('out');
        $this->outFile = $out ? $this->resolvePath($out) : null;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if ($this->outFile && $this->filesystem->exists($this->outFile)) {
            if (is_dir($this->outFile)) {
                throw new InvalidArgumentException("Bad --out: '{$this->outFile}' is a directory, please pass a file.");
            }

            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion("The file '{$this->outFile}' already exists.  Overwrite?", false);
            if (!$helper->ask($input, $output, $question)) {
                exit(1);
            }

            $this->confirmedOverwrite = true;
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $finder = $this->parseSources($input);
        $generator = new StubsGenerator($this->parseSymbols($input));

        $result = $generator->generate($finder);

        $printer = new Standard();
        if ($this->outFile) {
            if ($this->confirmedOverwrite || !$this->filesystem->exists($this->outFile)) {
                $this->filesystem->dumpFile($this->outFile, $result->prettyPrint($printer));
            } else {
                throw new InvalidArgumentException("Cannot write to '{$this->outFile}'.");
            }
        } else {
            $output->writeln($result->prettyPrint($printer));
        }
    }

    /**
     * Resolves a path argument relative to the working directory.
     *
     * @param string $path Input path.
     *
     * @return string Resolved path.
     */
    private function resolvePath(string $path): string
    {
        if (!$this->filesystem->isAbsolutePath($path)) {
            $path = getcwd() . DIRECTORY_SEPARATOR . $path;
        }
        return $path;
    }

    /**
     * Validate and get full paths to the requested sources.
     *
     * @param InputInterface $input
     *
     * @throws InvalidArgumentException If any source does not exist.
     *
     * @return Finder Finder including all sources.
     */
    private function parseSources(InputInterface $input): Finder
    {
        $finder = Finder::create();

        $sources = $input->getArgument('sources');
        $singleFiles = [];
        foreach ($sources as $source) {
            $source = $this->resolvePath($source);
            if (!$this->filesystem->exists($source)) {
                throw new InvalidArgumentException("Bad path: '$source' does not exist.");
            }
            if (is_dir($source)) {
                $finder->in($source);
            } else {
                // HACK: Dumb but necessary to get instance of correct thing.
                $singleFiles[] = new SplFileInfo($source, $source, $source);
            }
        }
        if ($singleFiles) {
            $finder->append(new ArrayIterator($singleFiles));
        }

        return $finder;
    }

    /**
     * If any symbol types are passed explicitly, only use those; otherwise use
     * the default set.
     *
     * @param InputInterface $input
     *
     * @return int Bitmask of symbol types.
     */
    private function parseSymbols(InputInterface $input): int
    {
        $symbols = 0;
        foreach (self::SYMBOL_OPTIONS as $opt) {
            if ($input->getOption($opt[0])) {
                $symbols |= $opt[1];
            }
        }
        return $symbols ?: StubsGenerator::DEFAULT;
    }
}
