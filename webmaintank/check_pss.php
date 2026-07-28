<?php
if (!isset($_GET['host']) || !isset($_GET['port'])) {
    echo "Missing parameters";
    exit;
}

$host = $_GET['host'];
$port = (int) $_GET['port'];
$timeout = 1;

$connection = @fsockopen($host, $port, $errno, $errstr, $timeout);

if ($connection) {
    fclose($connection);
    echo "Online";
} else {
    echo "Offline";
}
?>


