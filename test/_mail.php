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

echo 'Disabled for demo';
JBDump::off();
JBDump::mail('Test message');
JBDump::mail('Test message', null, 'admin@site.com');
JBDump::mail('Test message', 'Test subject text', null);
JBDump::mail('Test message', 'Test subject text', 'admin@site.com');

JBDump::i()->mail(array(
        'test'  => 'test2',
        'test4' => 'test3',
    ));
