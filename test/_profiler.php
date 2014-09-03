<?php
/**
 * @package     JBDump test
 * @version     1.2.0
 * @author      admin@joomla-book.ru
 * @link        http://joomla-book.ru/
 * @copyright   Copyright (c) 2009-2011 Joomla-book.ru
 * @license     GNU General Public License version 2 or later; see LICENSE
 * 
 */

 
include './included_file.php';

JBDump::i(array(
    'profiler' => array(
        'render'    => 28,
    ),
));

JBDump::showErrors();

?><h3>Code</h3><?php
JBDump(file_get_contents(__FILE__), 0, '-= Code =-::source');
?><h3>Result</h3><?php

JBDump::memory();

JBDump::mark('start loop');

    $bigArray = array( 0 => 0);
    for ($i=1; $i < 10000; $i++) {
        $bigArray[$i] = $i+@$bigArray[$i-1];
    }

JBDump::mark('finish loop');


unset($bigArray);
JBDump::mark('unset $bigArray');


JBDump::mark('start loop #2');
    for ($i=0; $i < 1000000; $i++) {
    }
JBDump::mark('finish loop #2');


JBDump::mark('start loop #3');
    $j = 0;
    for ($i=0; $i < 1000000; $i++) {
        $j++;
    }
JBDump::mark('finish loop #3');

    echo "JBDump::microtime();";
    JBDump::microtime();

    echo "JBDump::memory();";
    JBDump::memory();

    echo "JBDump::microtime();";
    JBDump::microtime();

    
JBDump::i()->mark('other functions');
