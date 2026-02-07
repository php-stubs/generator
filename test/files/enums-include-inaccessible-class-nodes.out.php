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
    }

    /** doc */
    protected function b(): void
    {
    }

    /** doc */
    private function c(): void
    {
    }

    /** doc */
    public static function d(): self
    {
    }

    /** doc */
    protected static function e(): string
    {
    }

    /** doc */
    private static function e(): void
    {
    }
}
