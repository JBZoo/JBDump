<?php

define('JBDUMP_DATE_DATETIME', 'Y-m-d H:i:s');
define('JBDUMP_DATE_DATE', 'Y-m-d');
define('JBDUMP_DATE_TIME', 'H:i:s');

if (!isset($_POST['value'])) {
    $_POST['value'] = time();
}

?>
<html>
    <head>
        <title>Dates</title>
    </head>
    <body>
        <form action="" method="post">
            <textarea name="value" rows="5" cols="50"><?php echo $_POST['value'];?></textarea>
            <br>
            <input type="submit" value="Submit">
        </form>

        <?php
        JBDump(time() - $_POST['value'] . ' sec', 0, 'time() - $value');

        echo '<hr>';
        $consts = get_defined_constants(true);

        foreach ($consts['user'] as $key => $const) {
            if (strpos($key, 'JBDUMP_DATE_') === 0) {
                $key = str_replace('JBDUMP_DATE_', '', $key);
                JBDump(date($const, $_POST['value']), 0, $key . '  ' . $const);
            }
        }

        foreach ($consts['date'] as $key => $const) {
            if (strpos($key, 'DATE_') === 0) {
                $key = str_replace('DATE_', '', $key);
                JBDump(date($const, $_POST['value']), 0, $key . '  ' . $const);
            }
        }
        ?>
    </body>
</html>
