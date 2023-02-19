<?php
/** doc */
abstract class A extends B implements C
{
    /** doc */
    protected const A = 'B';

    /** doc */
    private const B = 'C';

    /** doc */
    public static $a = 'a';

    private static $b = 'b';

    /** doc */
    public static function b($a): void
    {
        return;
    }

    /** doc */
    abstract public function c($a): string;

    /** doc */
    protected function d($a): void
    {
        return;
    }

    /** doc */
    abstract protected function e($a): string;

    /** doc */
    private function f($a): void
    {
        return;
    }
}

if (!class_exists('D')) {
    class D
    {
        public function a(string $a): string
        {
            return '';
        }
    }
}

final class E
{
    /** doc */
    public $a = 'a';

    /** doc */
    protected $b = 'b';

    /** doc */
    public function a($a): void
    {
        return;
    }

    /** doc */
    protected function b($a): void
    {
        return;
    }
}

trait F
{
    /** doc */
    private $a = 'a';

    /** doc */
    private function a($a): void
    {
        return;
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
    use F;

    /** doc */
    public function b($a): string
    {
        return '';
    }

    /** doc */
    protected function c($a): string
    {
        return '';
    }

    /** doc */
    private function d($a): string
    {
        return '';
    }
}
