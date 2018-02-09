<?php
namespace StubsGenerator;

use Generator;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
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
     * Shortcut to include every symbol type.
     *
     * @var int
     */
    public const ALL = self::FUNCTIONS | self::CLASSES | self::TRAITS | self::INTERFACES;

    /** @var bool */
    private $needsFunctions;
    /** @var bool */
    private $needsClasses;
    /** @var bool */
    private $needsTraits;
    /** @var bool */
    private $needsInterfaces;
    /**
     * @var bool[]
     * @psalm-var array<string, bool>
     */
    private $allSymbols = [];

    /**
     * @param int $symbols Bitmask of symbol types to include in the stubs.
     */
    public function __construct(int $symbols = self::ALL)
    {
        $this->needsFunctions = $symbols & self::FUNCTIONS;
        $this->needsClasses = $symbols & self::CLASSES;
        $this->needsTraits = $symbols & self::TRAITS;
        $this->needsInterfaces = $symbols & self::INTERFACES;
    }

    /**
     * Iterates through all the files found by the `$finder` and yields
     * pretty-printed stubs.
     *
     * @param Finder $finder
     *
     * @return Generator
     */
    public function generate(Finder $finder): Generator
    {
        $out = '';

        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $printer = new PrettyPrinter\Standard();

        foreach ($finder as $file) {
            $stmts = $parser->parse($file->getContents());
            if ($stubs = $this->extractStubStmts($stmts)) {
                yield $file->getRelativePathname() => $printer->prettyPrint($stubs);
            }
        }
    }

    /**
     * Iterates through the `$stmts` and prepares them for pretty printing,
     * extracting only the interesting ones.
     *
     * @param \PhpParser\Node $stmts
     * @param bool $isInIf If we're already in an if statement, we don't want to
     *                     recur further.
     *
     * @return \PhpParser\Node[] Extracted simplified stmts
     */
    private function extractStubStmts(array $stmts, bool $isInIf = false): array
    {
        $stubs = [];

        foreach ($stmts as $s) {
            $name = '';
            if ($s instanceof Function_) {
                if (!$this->needsFunctions || function_exists($s->name)) {
                    // Some functions are defined for compatibility with earlier
                    // versions of PHP, but we shouldn't redeclare them if our
                    // version supports it.
                    continue;
                }

                $s->stmts = [];
                $name = $s->getType() . $s->name;
            } elseif ($s instanceof Class_ || $s instanceof Interface_ || $s instanceof Trait_) {
                if ($s instanceof Class_ && (!$this->needsClasses || class_exists($s->name))) {
                    continue;
                } elseif ($s instanceof Interface_ && (!$this->needsInterfaces || class_exists($s->name))) {
                    continue;
                } elseif ($s instanceof Trait_ && (!$this->needsTraits || class_exists($s->name))) {
                    continue;
                }

                foreach ($s->stmts as $_s) {
                    if (!empty($_s->stmts)) {
                        $_s->stmts = [];
                    }
                }

                $name = $s->getType() . $s->name;
            } elseif ($s instanceof Namespace_) {
                if ($s->stmts = $this->extractStubStmts($s->stmts)) {
                    $name = $s->getType() . $s->name;
                }
            } elseif ($s instanceof If_ && !$isInIf) {
                // Find function and class definitions wrapped in a single `if`
                // statement, probably checking `if (function_exists('f')) ...`.
                $stubs += $this->extractStubStmts($s->stmts, true);
            }

            if ($name && !isset($this->allSymbol[$name])) {
                // Avoid adding symbols that were declared twice.
                $this->allSymbols[$name] = true;
                $stubs[$name] = $s;
            }
        }

        return $stubs;
    }
}
