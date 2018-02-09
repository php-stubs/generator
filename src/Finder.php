<?php
namespace StubsGenerator;

use Symfony\Component\Finder\Finder as SymfonyFinder;

/**
 * Subclass of Symfony's `Finder` which filters to PHP files only by default.
 */
class Finder extends SymfonyFinder
{
    public function __construct()
    {
        parent::__construct();

        $this
            ->files()
            ->name('*.php')
            ->ignoreVCS(true)
        ;
    }
}
