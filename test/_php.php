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

echo 'JBDump::locale();';
JBDump::locale();

echo 'JBDump::conf();';
JBDump::conf();

echo "JBDump::conf('pcre')";
JBDump::conf('pcre');

echo "JBDump::extensions();";
JBDump::extensions();

echo "JBDump::extensions(1);";
JBDump::extensions(1);

echo "JBDump::phpini();";
JBDump::phpini();

echo "JBDump::timezone();";
JBDump::timezone();

echo "JBDump::path();";
JBDump::path();
