<?php
namespace StubsGenerator;

use ArrayIterator;
use Exception;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;
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
        ['constants', StubsGenerator::CONSTANTS],
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
        $this->setName('generate-stubs')
            ->setDescription('Generates stubs for the PHP files in the given sources.')
            ->addArgument('sources', InputArgument::IS_ARRAY, 'The sources from which to generate stubs.  Either directories or specific files.  At least one must be specified, unless --finder is specified.')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Path to a file to write pretty-printed stubs to.  If unset, stubs will be written to stdout.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Whether to force an overwrite.')
            ->addOption('finder', null, InputOption::VALUE_REQUIRED, 'Path to a PHP file which returns a `Symfony\Finder` instance including the set of files that should be parsed.  Can be used instead of, but not in addition to, passing sources directly.')
            ->addOption('visitor', null, InputOption::VALUE_REQUIRED, 'Path to a PHP file which returns a `StubsGenerator\NodeVisitor` instance to replace the default node visitor.')
            ->addOption('header', null, InputOption::VALUE_REQUIRED, 'A doc comment to prepend to the top of the generated stubs file.  (Will be added below the opening `<?php` tag.)', '')
            ->addOption('nullify-globals', null, InputOption::VALUE_NONE, 'Initialize all global variables with a value of `null`, instead of their assigned value.')
            ->addOption('include-inaccessible-class-nodes', null, InputOption::VALUE_NONE, 'Include inaccessible class nodes like private members.')
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Whether to print stats instead of outputting stubs.  Stats will always be printed if --out is provided.');

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

            $io = new SymfonyStyle($input, $output);
            $message = "The file '{$this->outFile}' already exists.  Overwrite?";
            if (!$input->getOption('force') && !$io->confirm($message, false)) {
                exit(1);
            }

            $this->confirmedOverwrite = true;
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $visitor = null;
        $visitorPath = $input->getOption('visitor');

        if ($visitorPath) {
            $visitorPath = $this->resolvePath($visitorPath);
            if (!$this->filesystem->exists($visitorPath) || is_dir($visitorPath)) {
                throw new InvalidArgumentException("Bad --visitor path: '$visitorPath' does not exist or is a directory.");
            }
            try {
                $visitor = @include $visitorPath;
            } catch (Exception $e) {
                throw new RuntimeException("Could not resolve a `StubsGenerator\NodeVisitor` from '$visitorPath'.", 0, $e);
            }
            if (!$visitor || !($visitor instanceof NodeVisitor)) {
                throw new RuntimeException("Could not resolve a `StubsGenerator\NodeVisitor` from '$visitorPath'.");
            }
        }

        $finder = $this->parseSources($input);
        $generator = new StubsGenerator($this->parseSymbols($input), [
            'nullify_globals' => $input->getOption('nullify-globals'),
            'include_inaccessible_class_nodes' => $input->getOption('include-inaccessible-class-nodes')
        ]);

        $result = $generator->generate($finder, $visitor);

        $printer = new Standard();

        if ($header = $input->getOption('header')) {
            $header = "\n$header";
        }
        // 5 === strlen('<?php')
        $prettyPrinted = substr_replace($result->prettyPrint($printer), "<?php{$header}", 0, 5);

        if ($this->outFile) {
            $io->title('PHP Stubs Generator');

            if ($this->confirmedOverwrite || !$this->filesystem->exists($this->outFile)) {
                $this->filesystem->dumpFile($this->outFile, $prettyPrinted);
            } else {
                throw new InvalidArgumentException("Cannot write to '{$this->outFile}'.");
            }

            $io->success("Stubs written to {$this->outFile}");

            $this->printStats($io, $result);
        } elseif ($input->getOption('stats')) {
            $io->title('PHP Stubs Generator: Stats');

            $this->printStats($io, $result);
        } else {
            $output->writeln($prettyPrinted);
        }

        return 0;
    }

    private function printStats(OutputStyle $io, Result $result): void
    {
        $io->table(array_keys($result->getStats()), [$result->getStats()]);

        if ($dupes = $result->getDuplicates()) {
            $io->section('Duplicate declarations found');
            $io->table(array_keys($dupes[0]), $dupes);
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
        if ($finderPath = $input->getOption('finder')) {
            $finderPath = $this->resolvePath($finderPath);
            if (!$this->filesystem->exists($finderPath) || is_dir($finderPath)) {
                throw new InvalidArgumentException("Bad --finder path: '$finderPath' does not exist or is a directory.");
            }
            try {
                $finder = @include $finderPath;
            } catch (Exception $e) {
                throw new RuntimeException("Could not resolve a `Symfony\Finder` from '$finderPath'.", 0, $e);
            }
            if (!$finder || !($finder instanceof Finder)) {
                throw new RuntimeException("Could not resolve a `Symfony\Finder` from '$finderPath'.");
            }
            return $finder;
        }

        $sources = $input->getArgument('sources');
        if (!$sources) {
            throw new RuntimeException('Not enough arguments.  Missing either <sources> or --finder.');
        }

        $finder = Finder::create();
        $singleFiles = [];
        foreach ($sources as $source) {
            $source = $this->resolvePath($source);
            if (!$this->filesystem->exists($source)) {
                throw new InvalidArgumentException("Bad source path: '$source' does not exist.");
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
