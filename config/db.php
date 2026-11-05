<?php
// config/db.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host     = getenv('MYSQLHOST') ?: getenv('DB_HOST');
$port     = getenv('MYSQLPORT') ?: getenv('DB_PORT');
$dbname   = getenv('MYSQL_DATABASE') ?: getenv('DB_NAME');
$username = getenv('MYSQLUSER') ?: getenv('DB_USER');
$password = getenv('MYSQL_ROOT_PASSWORD') ?: getenv('MYSQL_PASSWORD');

try {
    $db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Bağlantı Hatası: " . $e->getMessage());
}
