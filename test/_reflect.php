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

?><h3>Classes</h3><?php
echo "JBDump::classInfo('simpleInterface');";
JBDump::classInfo('simpleInterface');

echo "JBDump::classInfo('simpleRootObject');";
JBDump::classInfo('simpleRootObject');

echo "JBDump::classInfo('simpleParentObject');";
JBDump::classInfo('simpleParentObject');

echo "JBDump::classInfo(new simpleObject());";
JBDump::classInfo(new simpleObject());

echo "JBDump::classInfo(JBDump::i());";
JBDump::classInfo(JBDump::i());

echo "JBDump::classInfo('reflection');";
JBDump::classInfo('reflection');

echo "JBDump::classInfo('unknowClass_123');";
JBDump::classInfo('unknowClass_123');

?><h3>Functions</h3><?php
echo "JBDump::funcInfo('simpleFunction');";
JBDump::funcInfo('simpleFunction');

echo 'JBDump::funcInfo($simpleClosureFunction);';
JBDump::funcInfo($simpleClosureFunction);

echo "JBDump::funcInfo('is_array');";
JBDump::funcInfo('is_array');

echo "JBDump::funcInfo('mysql_query');";
JBDump::funcInfo('mysql_query');

echo "JBDump::funcInfo('call_user_method_array');";
JBDump::funcInfo('call_user_method_array');

echo "JBDump::funcInfo('unkhowFunction_123');";
JBDump::funcInfo('unkhowFunction_123');

?><h3>Extensions</h3><?php
echo "JBDump::extInfo('reflection');";
JBDump::extInfo('reflection');

echo "JBDump::extInfo('mysqli');";
JBDump::extInfo('mysqli');

echo "JBDump::extInfo('unknowExtenseion_123');";
JBDump::extInfo('unknowExtenseion_123');
