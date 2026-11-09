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

// Railway Güncel Veritabanı Bilgileri
$host     = 'acela.proxy.rlwy.net';
$port     = '57474';$dbname   = 'railway';
$username = 'root';$password = 'hAqBMmQQGNBJMYiTqWRXotmWcPekjwLx';

try {
    $db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username,$password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,
        PDO::ATTR_TIMEOUT            => 10
    ]);
    $pdo =$db;
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// TAM MİMARİ VE 5 TEST HESABI OTOMATİK KURULUMU
try {
    $isFullyInstalled = false;
    $tableExists =$db->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($tableExists) {
        $stmtCheck =$db->query("SELECT COUNT(*) FROM users WHERE email = 'bolumbaskani@selcuk.edu.tr'");
        $isFullyInstalled = ((int)$stmtCheck->fetchColumn() > 0);
    }

    if (!$isFullyInstalled) {
        // Önceki eksik kurulumdan kalan tabloları temizle ve tam şemayı kur
        $db->exec("
            SET FOREIGN_KEY_CHECKS = 0;
            DROP TABLE IF EXISTS messages;
            DROP TABLE IF EXISTS audit_logs;
            DROP TABLE IF EXISTS regulation_rules;
            DROP TABLE IF EXISTS student_documents;
            DROP TABLE IF EXISTS student_courses;
            DROP TABLE IF EXISTS course_catalog;
            DROP TABLE IF EXISTS students;
            DROP TABLE IF EXISTS users;
            SET FOREIGN_KEY_CHECKS = 1;
        ");

        // Projede database_setup.sql varsa öncelikle onu çalıştır
        $sqlFile = __DIR__ . '/../database_setup.sql';
        if (file_exists($sqlFile)) {
            $sqlContent = file_get_contents($sqlFile);
            $sqlContent = preg_replace('/CREATE\s+DATABASE.*?;/i', '',$sqlContent);
            $sqlContent = preg_replace('/USE\s+`?[a-zA-Z0-9_]+`?\s*;/i', '', $sqlContent);
            $db->exec($sqlContent);
        }

        // Tüm tabloların ve sütunların eksiksiz olduğundan emin ol
        $db->exec("
            SET FOREIGN_KEY_CHECKS = 0;

            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ogrenci_no VARCHAR(50) DEFAULT NULL,
                email VARCHAR(150) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                password VARCHAR(255) DEFAULT NULL,
                sifre VARCHAR(255) DEFAULT NULL,
                ad_soyad VARCHAR(150) NOT NULL,
                rol VARCHAR(50) NOT NULL DEFAULT 'ogrenci',
                role VARCHAR(50) NOT NULL DEFAULT 'ogrenci',
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS students (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                ogrenci_no VARCHAR(30) NOT NULL,
                ad_soyad VARCHAR(150) NOT NULL,
                email VARCHAR(150) DEFAULT NULL,
                program_turu VARCHAR(20) NOT NULL DEFAULT 'CAP',
                anadal_bolum VARCHAR(150) DEFAULT '
