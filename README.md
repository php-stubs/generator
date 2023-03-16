# PHP Stubs Generator

[![Build Status](https://travis-ci.com/php-stubs/generator.svg?branch=master)](https://travis-ci.com/github/php-stubs/generator)

Use this tool to generate stub declarations for functions, classes, interfaces, and global variables defined in any PHP code. The stubs can subsequently be used to facilitate IDE completion or static analysis via [PHPStan](https://phpstan.org) or potentially other tools.  Stub generation is particularly useful for code which mixes definitions with side-effects.

The generator is based on nikic's [PHP-Parser](https://github.com/nikic/PHP-Parser), and the code also relies on several [Symfony](https://symfony.com) components.

Contributions in the form of [issues](https://github.com/php-stubs/generator/issues/new) or Pull Requests are welcome!

## Example

The idea is to turn this:

```php
// source-file-1.php
/**
 * @param string $bar
 * @return int
 */
function foo($bar)
{
    return (int) $bar;
}

/** @var string */
$something = '123abc';

if ($something) {
    echo foo($something);
}

// source-file-2.php
namespace MyNamespace;

class MyClass extends MyParentClass
{
    public function method(): string
    {
        return '';
    }
}
```

Into this:

```php
// stubs.php
namespace {
    /**
     * @param string $bar
     * @return int
     */
    function foo($bar)
    {
    }

    /** @var string */
    $something = '123abc';
}

namespace MyNamespace {
    class MyClass extends MyParentClass
    {
        public function method(): string
        {
        }
    }
}
```

## Command Line Usage

To install:

```bash
composer global require php-stubs/generator
```

To get the pretty-printed stubs for all the PHP files in a directory:

```bash
generate-stubs /path/to/my-library
```

You may also pass multiple directories, or filenames, separated by spaces.  All stubs will be concatenated in the output.

To write the stubs to a file (and see a few statistics in the stdout):

```bash
generate-stubs /path/to/my-library --out=/path/to/output.php
```

For the complete set of command line options:

```bash
generate-stubs --help
```

## Usage in PHP

To install:

```bash
composer require php-stubs/generator
```

### Simple Example

```php
// You'll need the Composer Autoloader.
require 'vendor/autoload.php';

// You may alias the classnames for convenience.
use StubsGenerator\{StubsGenerator, Finder};

// First, instantiate a `StubsGenerator\StubsGenerator`.
$generator = new StubsGenerator();

// Then, create a `StubsGenerator\Finder` which contains
// the set of files you wish to generate stubs for.
$finder = Finder::create()->in('/path/to/my-library/');

// Now you may use the `StubsGenerator::generate()` method,
// which will return a `StubsGenerator\Result` instance.
$result = $generator->generate($finder);

// You can use the `Result` instance to pretty-print the stubs.
echo $result->prettyPrint();

// You can also use it to retrieve the PHP-Parser nodes
// that represent the generated stub declarations.
$stmts = $result->getStubStmts();
```

### Additional Features

You can restrict the set of symbol types for which stubs are generated:

```php
// This will only generate stubs for function declarations.
$generator = new StubsGenerator(StubsGenerator::FUNCTIONS);

// This will only generate stubs for class or interface declarations.
$generator = new StubsGenerator(StubsGenerator::CLASSES | StubsGenerator::INTERFACES);
```

The set of symbol types are:

- `StubsGenerator::FUNCTIONS`: Function declarations.
- `StubsGenerator::CLASSES`: Class declarations.
- `StubsGenerator::TRAITS`: Trait declarations.
- `StubsGenerator::INTERFACES`: Interface declarations.
- `StubsGenerator::DOCUMENTED_GLOBALS`: Global variables, but only those with a doc comment.
- `StubsGenerator::UNDOCUMENTED_GLOBALS`: Global variable, but only those without a doc comment.
- `StubsGenerator::GLOBALS`: Shortcut to include both documented and undocumented global variables.
- `StubsGenerator::CONSTANTS`: Constant declarations.
- `StubsGenerator::DEFAULT`: Shortcut to include everything _except_ undocumented global variables.
- `StubsGenerator::ALL`: Shortcut to include everything.
