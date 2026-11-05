<?php
// config/db.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Görseldeki Host ve Port bilgileri eklendi
$host     = 'acela.proxy.rlwy.net'; 
$port     = '57474';         
$dbname   = 'railway';
$username = 'root';
$password = 'SIFRENI_BURAYA_YAZ'; // Variables sekmesindeki güncel MYSQL_ROOT_PASSWORD değerini buraya yaz

try {
    $db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5 
    ]);
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}
