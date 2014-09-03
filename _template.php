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

sort($pages);

if (!isset($_GET['page']) || in_array($_GET['page'], $pages) === false) {
    $_GET['page'] = $defaut;
}
 

?><html>
    <head>
        <title>JBDump by Joomla-book.ru</title>
    </head>
    <body>
        <table style="width: 1100px; margin: 0 auto;height: 100%;">
            <tr>
                <td style="vertical-align: top;width:180px;">
                    <strong>Pages:</strong>
                    <ul>
                        <?php foreach ($pages as $page) {?>
                            <?php if($page == $_GET['page']) { ?>
                                <li><strong><a href="/<?php echo $folder;?>/index.php?page=<?php echo $page;?>"><?php echo $page;?></a></strong></li>
                            <?php } else { ?>
                                <li><a href="/<?php echo $folder;?>/index.php?page=<?php echo $page;?>"><?php echo $page;?></a></li>
                            <?php } ?>
                        <?php } ?>
                    </ul>
                    
                    <strong>Links:</strong>
                    <ul>
                        <li><a href="http://joomla-book.ru/projects/jbdump">About JBDump</a></li>
                        <li><a href="http://trac.mysvn.ru/smet.denis/JBDump/newticket">Bug report</a></li>
                        <li><a href="http://<?php echo $_SERVER['HTTP_HOST'];?>/test/">Test & Demo</a></li>
                        <li><a href="http://<?php echo $_SERVER['HTTP_HOST'];?>/tools/">Tools</a></li>
                    </ul>
                    
                </td>
                <td style="vertical-align: top;">
                    <h2 style="text-transform: capitalize;"><?php echo $_GET['page'];?></h2>
                    <iframe src="http://<?php echo $_SERVER['HTTP_HOST'];?>/<?php echo $folder;?>/_<?php echo $_GET['page'].'.php';?>"
                        width="100%" height="100%" scrolling="auto"
                        style="width: 100%; border: none; min-height:500px;"></iframe>
                </td>
            </tr>
        </table>
        
        <?php
        if (file_exists(dirname(__FILE__).'/_counters.php')) {
            include(dirname(__FILE__).'/_counters.php');
        }
        ?>
        
    </body>
</html>

