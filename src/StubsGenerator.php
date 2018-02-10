<?php
namespace StubsGenerator;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

class StubsGenerator
{
    /**
     * Function symbol type.
     *
     * @var int
     */
    public const FUNCTIONS = 1;

    /**
     * Class symbol type.
     *
     * @var int
     */
    public const CLASSES = 2;

    /**
     * Trait symbol type.
     *
     * @var int
     */
    public const TRAITS = 4;

    /**
     * Interface symbol type.
     *
     * @var int
     */
    public const INTERFACES = 8;

    /**
     * Global variable symbol type; will only include global variables with a
     * doc comment.
     *
     * @var int
     */
    public const DOCUMENTED_GLOBALS = 16;

    /**
     * Global variable symbol type; will only include global variables without a
     * doc comment.
     *
     * @var int
     */
    public const UNDOCUMENTED_GLOBALS = 32;

    /**
     * Shortcut to include both documented and undocumented global variables.
     *
     * @var int
     */
    public const GLOBALS = self::DOCUMENTED_GLOBALS | self::UNDOCUMENTED_GLOBALS;


    /**
     * The default set of symbol types.
     *
     * @var int
     */
    public const DEFAULT = self::FUNCTIONS | self::CLASSES | self::TRAITS | self::INTERFACES | self::DOCUMENTED_GLOBALS;

    /**
     * Shortcut to include every symbol type.
     *
     * @var int
     */
    public const ALL = self::FUNCTIONS | self::CLASSES | self::TRAITS | self::INTERFACES | self::GLOBALS;

    /** @var int */
    private $symbols;

    /**
     * @param int $symbols Bitmask of symbol types to include in the stubs.
     */
    public function __construct(int $symbols = self::DEFAULT)
    {
        $this->symbols = $symbols;
    }

    /**
     * Iterates through all the files found by the `$finder` and returns
     * pretty-printed stubs.
     *
     * @param Finder $finder The set of files to generate (merged) stubs for.
     *
     * @return Result
     */
    public function generate(Finder $finder): Result
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        $traverser = new NodeTraverser();
        $visitor = new NodeVisitor($this->symbols);
        $traverser->addVisitor($visitor);

        foreach ($finder as $file) {
            /** @psalm-suppress PossiblyNullArgument */
            $traverser->traverse($parser->parse($file->getContents()));
        }

        return $visitor;
    }
}
