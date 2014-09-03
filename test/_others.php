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

echo "JBDump::hash('123456');";
JBDump::hash('123456');

echo "JBDump::timestamp(time());";
JBDump::timestamp(time());

echo "JBDump::ip();";
JBDump::ip();

echo 'JBDump::json($jsonData, \'JSON\');';
JBDump::json($jsonData, 'JSON');

echo "JBDump::url('http://yandex.ru/yandsearch?text=joomla-book.ru&lr=213', 'yandex url');";
JBDump::url('http://yandex.ru/yandsearch?text=joomla-book.ru&lr=213', 'yandex url');

echo 'JBDump::print_r($var, 0, \'print_r\');';
JBDump::print_r($var, 0, 'print_r');

echo 'JBDump::var_dump($var, 0, \'var_dump\');';
JBDump::var_dump($var, 'var_dump');

