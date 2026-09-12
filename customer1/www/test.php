<?php
$link = mysql_connect('0.0.0.0', 'root', 'SPIndiaMySQLpwd2024');
if (!$link) {
die('Could not connect: ' . mysql_error());
}
echo 'Connected successfully';
mysqli_close($link);
?>
