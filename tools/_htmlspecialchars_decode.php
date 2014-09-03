<?php

if (!isset($_POST['value'])) {
    $_POST['value'] = '';
}

?>
<html>
    <head>
        <title>Html special chars decode</title>
    </head>
    <body>
        <form action="" method="post">
            <textarea name="value" rows="5" cols="50"><?php echo $_POST['value'];?></textarea>
            <br>
            <input type="submit" value="Submit">
        </form>

        <?php
        if ($_POST['value']) {
            JBDump(htmlspecialchars_decode($_POST['value']), 'htmlspecialchars');
        }
        ?>

    </body>
</html>

