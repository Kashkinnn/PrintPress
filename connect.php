<?php
session_start();

$connection = new mysqli('localhost', 'root', '', 'db_printpress');

if ($connection->connect_error) {
    die('Connection failed: ' . $connection->connect_error);
}

$connection->set_charset('utf8mb4');
?>
