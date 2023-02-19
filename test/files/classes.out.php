<?php
/** doc */
abstract class A extends \B implements \C
{
    /** doc */
    protected const A = 'B';

    /** doc */
    public static $a = 'a';

    /** doc */
    public static function b($a): void
    {
    }

    /** doc */
    abstract public function c($a): string;

    /** doc */
    protected function d($a) : void
    {
    }

    /** doc */
    protected abstract function e($a) : string;
}

class D
{
    public function a(string $a): string
    {
    }
}

final class E
{
    /** doc */
    public $a = 'a';

    /** doc */
    public function a($a) : void
    {
    }
}

trait F
{
    /** doc */
    private $a = 'a';

    /** doc */
    private function a($a) : void
    {
    }

    /** doc */
    abstract public function b($a): string;

    /** doc */
    abstract protected function c($a): string;

    /** doc */
    abstract private function d($a): string;
}

final class G
{
    use \F;

    /** doc */
    public function b($a): string
    {
    }
}
