<?php
// config/db.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Çevre değişkenlerini iptal ettik, bilgileri doğrudan giriyoruz.
$host     = 'BURAYA_TCP_PROXY_HOST_YAZILACAK'; // Örn: viaduct.proxy.rlwy.net
$port     = 'BURAYA_TCP_PROXY_PORT_YAZILACAK'; // Örn: 12345 (Standart 3306 DEĞİLDİR)
$dbname   = 'railway';
$username = 'root';
$password = 'TtLSRZbEVXUusmaXOdzhHlnkgwdKxbgXI'; 

try {
    $db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Bağlantı Hatası: " . $e->getMessage());
}
