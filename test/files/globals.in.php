<?php
/** doc */
$a = 'a' . 'a';

/** @doc */
$GLOBALS['b'] = 'b' . 'b';

$c = 'c' . 'c';

$GLOBALS['d'] = 'd' . 'd';

// Should not be included:

$foo['bar'] = 'baz';

$GLOBALS[$foo['bar']] = 'baz';

$GLOBALS['0abc'] = 'baz';

$GLOBALS['ab' . 'cd'] = 'baz';

$foo->bar = 'baz';

$$foo = 'bar';

function () {
};
