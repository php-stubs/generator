<?php
namespace StubsGenerator;

use IteratorAggregate;
use PhpParser\PrettyPrinterAbstract;

interface Result extends IteratorAggregate
{
    /**
     * Returns the list of stub statements.
     *
     * @return \PhpParser\Node[]
     */
    public function getStubStmts(): array;

    /**
     * Shortcut to pretty print all the stubs as one file.
     *
     * @param PrettyPrinterAbstract $printer Pretty printer instance to use.
     *
     * @return string The pretty printed version.
     */
    public function prettyPrint(PrettyPrinterAbstract $printer): string;
}
