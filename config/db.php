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
$port     = '57474';$dbname   = 'railway';
$username = 'root';$password = 'hAqBMmQQGNBJMYiTqWRXotmWcPekjwLx';
$dsn      = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE                => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE     => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES       => true,
    PDO::ATTR_TIMEOUT                => 10,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true
];

try {
    $db  = new PDO($dsn,$username, $password,$options);
    $pdo =$db;

    // 1. Eğer bolumbaskani hesabı yoksa database_setup.sql henüz tam çalışmamıştır, çalıştır:
    $needSetup = false;
    $checkTable =$db->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$checkTable) {$needSetup = true;
    } else {
        $checkAdmin = (int)$db->query("SELECT COUNT(*) FROM users WHERE email = 'bolumbaskani@selcuk.edu.tr'")->fetchColumn();
        if ($checkAdmin === 0) {$needSetup = true;
        }
    }

    if ($needSetup) {$sqlPath = __DIR__ . '/../database_setup.sql';
        if (file_exists($sqlPath)) {$sql = file_get_contents($sqlPath);$sql = preg_replace('/CREATE\s+DATABASE[^;]*;/i', '', $sql);$sql = preg_replace('/USE\s+[^;]*;/i', '', $sql);$db->exec("SET FOREIGN_KEY_CHECKS = 0;\n" . $sql . "\nSET FOREIGN_KEY_CHECKS = 1;");
            $db  = new PDO($dsn,$username, $password,$options);
            $pdo =$db;
        }
    }

    // 2. 5 Test hesabının şifresini (123456) ve e-postalarını kesin olarak doğrula/onar:
    if (empty($_SESSION['accounts_verified'])) {$validHash = password_hash('123456', PASSWORD_DEFAULT);

        // Mevcut tüm test hesaplarının şifresini geçerli 123456 hash'i ile güncelle
        $cols =$db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('password_hash', $cols)) {
            $stmtUpd =$db->prepare("UPDATE users SET password_hash = ? WHERE email LIKE '%@selcuk.edu.tr' OR email LIKE '%@ogr.selcuk.edu.tr'");
            $stmtUpd->execute([$validHash]);
        }
        if (in_array('password', $cols)) {
            $stmtUpd2 =$db->prepare("UPDATE users SET password = ? WHERE email LIKE '%@selcuk.edu.tr' OR email LIKE '%@ogr.selcuk.edu.tr'");
            $stmtUpd2->execute([$validHash]);
        }
        if (in_array('sifre', $cols)) {
            $stmtUpd3 =$db->prepare("UPDATE users SET sifre = ? WHERE email LIKE '%@selcuk.edu.tr' OR email LIKE '%@ogr.selcuk.edu.tr'");
            $stmtUpd3->execute([$validHash]);
        }

        // Öğrenci e-postalarını hocaya verilen maildeki formatla birebir eşleştir
        $db->exec("UPDATE users SET email = '203311041@selcuk.edu.tr' WHERE ogrenci_no = '203311041'");
        $db->exec("UPDATE users SET email = '223301099@selcuk.edu.tr' WHERE ogrenci_no = '223301099'");

        $_SESSION['accounts_verified'] = true;
    }
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

if
