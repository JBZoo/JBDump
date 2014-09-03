<?php

// for PHP 5.3+ only
$simpleClosureFunction = function($arg1, $arg2 = 123)
{
    static $i, $j, $k;
    return ++$i;
};
