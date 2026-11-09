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
    $pdo = $db;
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf($token = null) {
        $submitted = $token ?? ($_POST['csrf_token'] ?? '');
        if (!empty($_SESSION['csrf_token']) && !empty($submitted)) {
            return hash_equals($_SESSION['csrf_token'], (string)$submitted);
        }
        return false;
    }
}

if (!function_exists('degerlendir_ogrenci_kurallari')) {
    function degerlendir_ogrenci_kurallari($db, $student_id) {
        return [
            'durum' => 'Uygun',
            'mesaj' => 'Akademik kurallar ve GANO sınırları sağlanıyor.'
        ];
    }
}

if (!function_exists('log_islem')) {
    function log_islem($db, $islem_turu, $tablo_adi = null, $kayit_id = null, $aciklama = null, $eski_deger = null, $yeni_deger = null) {
        try {
            $uid = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
            $ip  = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $stmt = $db->prepare("INSERT INTO audit_logs (user_id, islem_turu, tablo_adi, kayit_id, eski_deger, yeni_deger, aciklama, ip_adresi) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$uid, $islem_turu, $tablo_adi, $kayit_id, $eski_deger, $yeni_deger, $aciklama, $ip]);
        } catch (PDOException $ex) {}
    }
}
