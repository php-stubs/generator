<?php
namespace StubsGenerator;

use ArrayIterator;
use IteratorAggregate;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use Traversable;

/**
 * Contains the results of stub generation, including the stubs themselves as
 * well as some metadata.
 */
class Result implements IteratorAggregate
{
    /** @var NodeVisitor */
    private $visitor;

    /**
     * @var \Exception[]
     * @psalm-var array<string, \Exception>
     */
    private $unparsed;

    /**
     * @param NodeVisitor $visitor The visitor which was used to generate stubs.
     * @param \Exception[] $unparsed A map of file path => reason for any
     *                               unparsed files.
     * @psalm-param $unparsed array<string, \Exception>
     */
    public function __construct(NodeVisitor $visitor, array $unparsed)
    {
        $this->visitor = $visitor;
        $this->unparsed = $unparsed;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getStubStmts());
    }

    /**
     * Returns the list of stub statements, which can be pretty-printed or
     * operated on further.
     *
     * @return \PhpParser\Node[]
     */
    public function getStubStmts(): array
    {
        return $this->visitor->getStubStmts();
    }

    /**
     * Returns a map of symbol type to count of unique symbols of that type
     * which are included in the stubs.
     *
     * Psalm doesn't seem to parse this correctly.
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     *
     * @return int[]
     * @psalm-return array<string, int>
     */
    public function getStats(): array
    {
        return array_map('count', $this->visitor->getCounts());
    }

    /**
     * Returns a list which includes any symbols for which more than one
     * declaration was found during stub generation.
     *
     * @return (string|int)[][]
     * @psalm-return array<array{ type: string, name: string, count: int }>
     */
    public function getDuplicates(): array
    {
        $dupes = [];
        foreach ($this->visitor->getCounts() as $type => $names) {
            foreach ($names as $name => $count) {
                if ($count > 1) {
                    $dupes[] = [
                        'type' => $type,
                        'name' => $type === 'globals' ? '$' . $name : ltrim($name, '\\'),
                        'count' => $count,
                    ];
                }
            }
        }

        usort($dupes, function (array $a, array $b): int {
            return $a['type'] <=> $b['type'] ?: $a['name'] <=> $b['name'];
        });

        return $dupes;
    }

    /**
     * Shortcut to pretty print all the stubs as one file.
     *
     * If no `$printer` is provided, a `\PhpParser\PrettyPrinter\Standard` will
     * be used.
     *
     * @param PrettyPrinterAbstract|null $printer Pretty printer instance.
     *
     * @return string The pretty printed version.
     */
    public function prettyPrint(PrettyPrinterAbstract $printer = null): string
    {
        if (!$printer) {
            $printer = new Standard();
        }
        return $printer->prettyPrintFile($this->getStubStmts());
    }
}
