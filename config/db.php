<?php
// config/db.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host     = getenv('MYSQLHOST') ?: 'roundhouse.proxy.rlwy.net'; // Railway'in vereceği veya verdiği host.
$port     = getenv('MYSQLPORT') ?: '3306'; // Railway portu.
$dbname   = 'railway';
$username = 'root';
$password = 'TtLSRZbEVXUusmaXOdzhHlnkgwdKxbgXI';

// 1. Aşama Bağlantı Denemesi
try {
    $db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Bağlantı Hatası: " . $e->getMessage() . " | Host: " . $host . " | User: " . $username);
}
