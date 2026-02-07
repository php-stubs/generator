<?php
/** doc */
enum A
{
    case A;
    case B;

    public const C = 'C';

    /** doc */
    public function a(): void
    {
    }

    /** doc */
    public static function d(): self
    {
    }
}
