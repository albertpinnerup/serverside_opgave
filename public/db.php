<?php

$host = "db";
$db = 'collection';
$user = 'user';
$password = 'password';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $error) {
        die('forbindelsen fejlede' . $error->getMessage());
}
