<?php
/**
 * @package     JBDump test
 * @version     1.3.0
 * @author      admin@joomla-book.ru
 * @link        http://joomla-book.ru/
 * @copyright   Copyright (c) 2009-2011 Joomla-book.ru
 * @license     GNU General Public License version 2 or later; see LICENSE
 * 
 */

$pages = array (
        'json_decode',
        'base64_decode',
        'base64_encode',
        'htmlspecialchars',
        'htmlspecialchars_decode',
        'mail',
//        'phpinfo',
        'unserialize',
        'dates',
        'hash',
        'url'
    );

$folder = 'tools';
$defaut = 'base64_decode';

include('../_template.php');
