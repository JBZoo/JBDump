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

JBDump::showErrors();

?><h3>Code</h3><?php
JBDump(file_get_contents(__FILE__), 0, '-= Code =-::source');
?><h3>Result</h3><?php

JBDump::errors();

// trigger some errors, first define a mixed array with a non-numeric item
$a = array(2, 3, "foo", 5.5, 43.3, 21.11);

// now generate second array
$b = scale_by_log($a, M_PI);

// this is trouble, we pass a string instead of an array
$c = scale_by_log("not array", 2.3);

// this is a critical error, log of zero or negative number is undefined
$d = scale_by_log($a, -2.5);

echo $_NOT_DEFINED_VAR;

$simpleObject = new simpleObject();
$simpleObject->getException();

echo "Not Executed\n";
