<?php
namespace StubsGenerator;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

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
    ];

    /**
     * @return void
     */
    public function configure()
    {
        $this->setName('run')
            ->setDescription('Generates stubs for the PHP files in the given directories')
            ->addArgument('directories', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The directories from which to generate stubs.');

        foreach (self::SYMBOL_OPTIONS as $opt) {
            $this->addOption($opt[0], null, InputOption::VALUE_NONE, "Include declarations for {$opt[0]}");
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $finder = Finder::create()->in($this->parseDirectories($input));
        $generator = new StubsGenerator($this->parseSymbols($input));

        $output->writeln('<?php');
        foreach ($generator->generate($finder) as $file => $lines) {
            $output->writeln("// $file");
            $output->writeln($lines);
        }
    }

    /**
     * Validate and get full paths to the requested directories.
     *
     * @param InputInterface $input
     *
     * @throws InvalidArgumentException If any directory does not exist.
     *
     * @return string[] List of full paths to directories.
     */
    private function parseDirectories(InputInterface $input): array
    {
        $filesystem = new Filesystem();

        $directories = $input->getArgument('directories');
        foreach ($directories as &$directory) {
            if (!$filesystem->isAbsolutePath($directory)) {
                $directory = getcwd() . DIRECTORY_SEPARATOR . $directory;
            }
            $directory = realpath($directory);
            if (!is_dir($directory)) {
                throw new InvalidArgumentException("Bad path: '$directory' is not a directory.");
            }
        }

        return $directories;
    }

    /**
     * If any symbol types are passed explicitly, only use those; otherwise
     * default to all of them.
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
        return $symbols ?: StubsGenerator::ALL;
    }
}
