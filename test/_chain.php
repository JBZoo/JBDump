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

echo '$jbdump = jbdump::i()->post()->get()->server();';
$jbdump = jbdump::i()->post()->get()->server();

echo 'jbdump::get();';
jbdump::get();

echo 'jbdump::post();';
jbdump::post();

echo 'jbdump::server();';
jbdump::server();

echo '$jbdump->get();';
$jbdump->get();

echo "jbdump()->dump('123')->get();";
jbdump()->dump('123')->get();

