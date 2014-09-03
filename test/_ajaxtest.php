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

jbdump::mark('test::start');

JBDump::showErrors();
set_time_limit(10);
echo '<pre>';

echo $unknown_var;
jbdump::get();

jbdump::mark('test::finish');

$simpleObject = new simpleObject();
$simpleObject->getException();

echo '</pre>';
