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
include './included_benchmarks.php';

JBDump::showErrors();



//d(GetPHPCPUMark());
d(GetPHPFilesMark());


