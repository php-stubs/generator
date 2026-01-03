<?php

/** doc */
const FOO = 'bar';

/** doc */
define('FIZ', 'BUZ');

const A = 1, B = 2;

if (!defined('ACME')) {
    define('ACME', 1);
}

if (!defined('ACME')) {
    define('ACME', 2);
}

\define( 'FOOBAR', /** @var int */ 0 );
