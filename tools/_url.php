<?php

if (!isset($_POST['value'])) {
    $_POST['value'] = '';
}

?>
<html>
    <head>
        <title>Parse url string</title>
    </head>
    <body>
        <form action="" method="post">
            <textarea name="value" rows="5" cols="50"><?php echo $_POST['value'];?></textarea>
            <br>
            <input type="submit" value="Submit">
        </form>

        <?php
        if ($_POST['value']) {
            JBDump::url($_POST['value'], 'JBDump::url');

            parse_str($_POST['value'], $var);
            JBDump($var, 0, 'Parsed url params');
        }
        ?>

    </body>
</html>

