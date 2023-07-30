<?php
/** doc */
abstract class A extends \B implements \C
{
    /** doc */
    public static $a = 'a';

    /** doc */
    public static function b($a): void
    {
    }

    /** doc */
    abstract public function c($a): string;
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
    abstract public function b($a): string;
}

final class G
{
    use \F;

    /** doc */
    public function b($a): string
    {
    }
}
