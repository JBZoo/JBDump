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

?>
<script type="text/javascript" src="http://yandex.st/jquery/1.7.0/jquery.min.js"></script>
<script type="text/javascript">
$(function(){
    $('#ajaxtest').click(function(){
        $.get(
            '/test/_ajaxtest.php',
            {'var1': 'value1', 'var2': 'value2'},
            function(data) {
                $('#responce').html(data);
            },
            'html'
        );
        return false;
    });

});
</script>

<input type="button" id="ajaxtest" name="ajaxtest" value="Ajax test" />
<div id="responce"></div>
