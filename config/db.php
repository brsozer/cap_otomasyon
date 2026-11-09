<?php
// config/db.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
}

$host     = 'acela.proxy.rlwy.net';
$port     = '57474';
$dbname   = 'railway';
$username = 'root';
$password = 'hAqBMmQQGNBJMYiTqWRXotmWcPekjwLx';
$dsn      = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES         => true,
    PDO::ATTR_TIMEOUT                  => 10,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS   => true
];

try {
    $db  = new PDO($dsn, $username, $password, $options);
    $pdo = $db;

    // Tablolar henüz kurulmadıysa depodaki orijinal database_setup.sql dosyasını otomatik kur
    $check = $db->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$check) {
        $sqlPath = __DIR__ . '/../database_setup.sql';
        if (file_exists($sqlPath)) {
            $sql = file_get_contents($sqlPath);
            $sql = preg_replace('/CREATE\s+DATABASE[^;]*;/i', '', $sql);
            $sql = preg_replace('/USE\s+[^;]*;/i', '', $sql);
            $db->exec("SET FOREIGN_KEY_CHECKS = 0;\n" . $sql . "\nSET FOREIGN_KEY_CHECKS = 1;");
            // Çoklu sorgu sonrası temiz bağlantı yenilemesi
            $db  = new PDO($dsn, $username, $password, $options);
            $pdo = $db;
        }
    }
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
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

if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
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
