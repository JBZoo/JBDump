<?php

if (!isset($_POST['value'])) {
    $_POST['value'] = '';
}

?>
<html>
    <head>
        <title>Base64 encode</title>
    </head>
    <body>
        <form action="" method="post">
            <textarea name="value" rows="5" cols="50"><?php echo $_POST['value'];?></textarea>
            <br>
            <input type="submit" value="Submit">
        </form>

        <?php
        if ($_POST['value']) {
            JBDump(base64_encode($_POST['value']), 'base64_decode');
        }
        ?>
    </body>
</html>
