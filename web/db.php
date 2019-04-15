<?php

$db_host = "localhost";
$db_user = "radius";
$db_passwd = "radpass";
$db_dbname = "radius";

// 0, 明文存储密码; 1, NTHash存储密码
$nthash_pass = 1;

$mysqli = new mysqli($db_host, $db_user, $db_passwd, $db_dbname);
if(mysqli_connect_error()){
        echo mysqli_connect_error();
}
$mysqli->set_charset("utf8");

session_start();

?>
