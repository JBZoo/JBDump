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


echo "JBDump::trace();";
JBDump::trace();

$var['simpleObject']->checkTrace('arg1', 2, simpleObject::SIMPLE_CONST);

testFuncArgs(1, 2);

$var['simpleObject']->testFuncArgs(1, 2);
