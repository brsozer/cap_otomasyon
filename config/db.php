<?php
// config/db.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Railway bağlantı bilgileri (Doğrudan tanımlı, hatasız)
$host     = 'BURAYA_RAILWAY_PUBLIC_HOST_ADRESINI_YAZ'; // Örn: autorack.proxy.rlwy.net
$port     = 'BURAYA_PORT_YAZ';                        // Örn: 3306 (Görselde 3306 yazıyor)
$dbname   = 'railway';
$username = 'root';
$password = 'TtLSRZbEVXUusmaXOdzhHlnkgwdKxbgXI';       // Görseldeki MYSQL_ROOT_PASSWORD şifren

try {
    $db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Bağlantı Hatası: " . $e->getMessage());
}
