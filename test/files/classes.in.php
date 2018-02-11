<?php
/** doc */
abstract class A extends B implements C
{
    /** doc */
    protected const A = 'B';

    /** doc */
    public static $a = 'a';

    /** doc */
    public static function b($a): void
    {
        return;
    }

    /** doc */
    abstract public function c($a): string;
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
