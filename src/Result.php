<?php
namespace StubsGenerator;

use ArrayIterator;
use IteratorAggregate;
use PhpParser\PrettyPrinterAbstract;

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

    public function getIterator()
    {
        return new ArrayIterator($this->getStubStmts());
    }

    /**
     * Returns the list of stub statements.
     *
     * @return \PhpParser\Node[]
     */
    public function getStubStmts(): array
    {
        return $this->visitor->getStubStmts();
    }

    /**
     * Shortcut to pretty print all the stubs as one file.
     *
     * @param PrettyPrinterAbstract $printer Pretty printer instance to use.
     *
     * @return string The pretty printed version.
     */
    public function prettyPrint(PrettyPrinterAbstract $printer): string
    {
        return $printer->prettyPrintFile($this->getStubStmts());
    }
}
