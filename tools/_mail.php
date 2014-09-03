<?php

if (!isset($_POST['value'])) {
    $_POST['value'] = '';
}

?>
<html>
    <head>
        <title>Mail</title>
    </head>
    <body>
        <form action="" method="post">
            <textarea name="value" rows="5" cols="50"><?php echo $_POST['value'];?></textarea>
            <br>
            <input type="submit" value="Submit">
        </form>
        <?php
        if ($_POST['value']) {
            $message = 'Testing is sending a letter with the PHP mail ()';
            JBDump::mail($message, 'JBDump test', $_POST['value']);
        }
        ?>
    </body>
</html>
