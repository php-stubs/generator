<?php
namespace StubsGenerator;

use Exception;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\TestCase;

class NodeVisitorTest extends TestCase
{
    private function parse(string $php, int $symbols): NodeVisitor
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        $traverser = new NodeTraverser();
        $visitor = new NodeVisitor($symbols);
        $traverser->addVisitor($visitor);

        $stmts = $parser->parse($php);
        $traverser->traverse($stmts);

        return $visitor;
    }

    private function print(array $stmts): string
    {
        $printer = new Standard();
        return $printer->prettyPrintFile($stmts);
    }

    public function inputOutputProvider(): array
    {
        $cases = [
            'classes',
            'functions',
            ['globals', 'globals.all'],
            ['globals', 'globals.doc', StubsGenerator::DOCUMENTED_GLOBALS],
            ['globals', 'globals.no-doc', StubsGenerator::UNDOCUMENTED_GLOBALS],
            'junk',
            'namespaces',
        ];

        $baseDir = __DIR__ . '/files/';

        return array_map(function ($case) use ($baseDir): array {
            if (is_string($case)) {
                $inFile = $outFile = $case;
            } elseif (count($case) === 2 && is_int($case[1])) {
                [$inFile, $symbols] = $case;
                $outFile = $inFile;
            } elseif (count($case) === 2) {
                [$inFile, $outFile] = $case;
            } else {
                [$inFile, $outFile, $symbols] = $case;
            }

            $inFile = "{$baseDir}{$inFile}.in.php";
            $outFile = "{$baseDir}{$outFile}.out.php";
            $symbols = $symbols ?? StubsGenerator::ALL;

            if (!file_exists($inFile)) {
                throw new Exception("$inFile does not exist");
            }
            if (!file_exists($outFile)) {
                throw new Exception("$outFile does not exist");
            }

            return [
                file_get_contents($inFile),
                file_get_contents($outFile),
                $symbols,
                $inFile,
            ];
        }, $cases);
    }

    /**
     * @dataProvider inputOutputProvider
     */
    public function testOutput(string $in, string $out, int $symbols, string $filename)
    {
        $this->assertSame(
            $this->normalize($out),
            $this->normalize($this->print($this->parse($in, $symbols)->getStubStmts())),
            "Expected input and output to match for '$filename'"
        );
    }

    private function normalize(string $string): string
    {
        // Really should be testing AST output, this is just easier...
        $string = trim(
            // https://stackoverflow.com/questions/709669/how-do-i-remove-blank-lines-from-text-in-php
            preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $string)
        );
        $string = str_replace([
            'abstract public',
            'abstract protected',
            'abstract private',
        ], [
            'public abstract',
            'protected abstract',
            'private abstract',
        ], $string);

        $string = str_replace('abstract static', 'static abstract', $string);

        $string = str_replace('): ', ') : ', $string);

        return $string;
    }
}
