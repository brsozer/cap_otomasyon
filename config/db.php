<?php
// config/db.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host     = 'BURADAYI_RAILWAY_VERDIGI_HOST'; // Örn: autorack.proxy.rlwy.net
$port     = 'BURADAYI_VERDIGI_PORT';         // Örn: 12345 (Portu da yazmayı unutma!)
$dbname   = 'railway';
$username = 'root';
$password = 'TtLSRZbEVXUusmaXOdzhHlnkgwdKxbgXI'; // Senin şifren

try {
    $db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Bağlantı Hatası: " . $e->getMessage());
}
