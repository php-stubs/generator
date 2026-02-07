<?php
/** doc */
enum A
{
    case A;
    case B;

    public const C = 'C';
    protected const D = 'D';
    private const E = 'E';

    /** doc */
    public function a(): void
    {
        return;
    }

    /** doc */
    protected function b(): void
    {
        return;
    }

    /** doc */
    private function c(): void
    {
        return;
    }

    /** doc */
    public static function d(): self
    {
        return self::A;
    }

    /** doc */
    protected static function e(): string
    {
        return self::D;
    }

    /** doc */
    private static function e(): void
    {
        return;
    }
}
