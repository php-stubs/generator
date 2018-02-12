# PHP Stubs Generator

Use this tool to generate stub declarations for functions, classes, interfaces, and global variables defined in any PHP code.  The stubs can subsequently be used to facilitate IDE completion or static analysis via [Psalm](https://getpsalm.org) or potentially other tools.  Stub generation is particularly useful for code which mixes definitions with side-effects.

The generator is based on nikic's [PHP-Parser](https://github.com/nikic/PHP-Parser), and the code also relies on several [Symfony](https://symfony.com) components.

Contributions in the form of issue reports or Pull Requests are welcome!

## Command Line Usage

To install:

```
composer global require giacocorsiglia/stubs-generator
```

To get the pretty-printed stubs for all the PHP files in a directory:

```
generate-stubs /path/to/my-library
```

You may also pass multiple directories, or filenames, separated by spaces.  All stubs will be concatenated in the output.

To write the stubs to a file (and see a few statistics in the stdout):

```
generate-stubs /path/to/my-library --out=/path/to/output.php
```

For the complete set of command line options:

```
generate-stubs --help
```

## Usage in PHP

To install:

```
composer require giacocorsiglia/stubs-generator
```

### Simple Example

```php
// You'll need the Composer Autoloader.
require 'vendor/autoload.php';

// You may alias the classnames for convenience.
use StubsGenerator\{StubsGenerator, Finder};

// First, instantiate a `StubsGenerator\StubsGenerator`.
$generator = new StubsGenerator();

// Then, create a `StubsGenerator\Finder` which contains the set of
// files you wish to generate stubs for.
$finder = Finder::create()->in('/path/to/my-library/');

// Now you may use the `StubsGenerator::generate()` method, which will
// return a `StubsGenerator\Result` instance.
$result = $generator->generate($finder);

// You can use the `Result` instance to pretty-print the stubs.
echo $result->prettyPrint();

// You can also use it to retrieve the PHP-Parser nodes that represent
// the generated stub declarations.
$stmts = $result->getStubStmts();
```

### Additional Features

You can restrict the set of symbol types for which stubs are generated:

```
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
- `StubsGenerator::DEFAULT`: Shortcut to include everything _except_ undocumented global variables.
- `StubsGenerator::ALL`: Shortcut to include everything.

## TODO

- Add support for constants declared with `const`.
- Add support for constants declared with `define()`.
    - Consider parsing function and method bodies for these declarations.
