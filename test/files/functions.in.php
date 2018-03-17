<?php
/** doc */
function a(string $a): string
{
    return '';
}

/** outer doc */
if (!function_exists('b')) {
    /** doc */
    function b(string $b): string
    {
        return '';
    }

    /** doc */
    function c(string $c): string
    {
        return '';
    }
}
