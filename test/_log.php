<?php
/**
 * @package     JBDump test
 * @version     1.2.0
 * @author      admin@joomla-book.ru
 * @link        http://joomla-book.ru/
 * @copyright   Copyright (c) 2009-2011 Joomla-book.ru
 * @license     GNU General Public License version 2 or later; see LICENSE
 */


include './included_file.php';

JBDump::showErrors();

?><h3>Code</h3><?php
JBDump(file_get_contents(__FILE__), 0, '-= Code =-::source');
?><h3>Result</h3><?php

$logger = JBDump::i();

echo 'Disable for demo';
JBDump::off(); // only for test

JBDump::log($var['null'], 'null');
JBDump::log($var['bool'], 'Boolean');
JBDump::log($var['integer'], 'Integer');
JBDump::log($var['float'], 'Float');
JBDump::log($var['string'], 'String');
JBDump::log($var['longString'], 'longString');
JBDump::log($var['stdClass'], 'stdClass');
JBDump::log($var['simpleObject'], 'simpleObject');
JBDump::log($var['var'], 'complex var');

//// other methods
JBDump::i()->log('Hello world!');
if (function_exists('l')) {
    l('short log function');
}

// set params
$logger
    ->setParams(array('file' => 'newLogName'), 'log')
    ->log($var['string'], 'message_name');

$logger->setParams(array(
            'file'   => 'newLogFormat',
            'format' => "{DATETIME}\t{CLIENT_IP} --------- {VAR1} // {VAR2} // {VAR3}",
            'serialize' => 'format'
        ), 'log')
    ->log(array(
        'var1' => '1',
        'Var2' => '2',
        'vAR3' => '3',
        'VAR4' => '4'
    ));
