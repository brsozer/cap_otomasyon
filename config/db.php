<?php
// config/db.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host     = 'acela.proxy.rlwy.net';
$port     = '57474';
$dbname   = 'railway';
$username = 'root';
$password = 'hAqBMmQQGNBJMYiTqWRXotmWcPekjwLx';

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

//
$tamamlanan_akts   = $tamamlanan_akts   ?? 60;
$hedef_akts        = $hedef_akts        ?? 135;
$kalan_akts        = $kalan_akts        ?? 75;
$bolum_ortalamasi  = $bolum_ortalamasi  ?? 3.15;
$uyarilar          = $uyarilar          ?? [];
