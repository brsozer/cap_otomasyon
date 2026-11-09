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
$port     = '57474';
$dbname   = 'railway';
$username = 'root';
$password = 'hAqBMmQQGNBJMYiTqWRXotmWcPekjwLx';

try {
    $db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,
        PDO::ATTR_TIMEOUT            => 10
    ]);
    $pdo = $db;
} catch (PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// OTOMATİK VERİTABANI KURULUMU (Tablolar yoksa ilk açılışta otomatik kurar)
try {
    $tableCheck = $db->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$tableCheck) {
        $sqlFile = __DIR__ . '/../database_setup.sql';
        if (file_exists($sqlFile)) {
            $sqlContent = file_get_contents($sqlFile);
            // CREATE DATABASE ve USE satırlarını Railway uyumu için temizle
            $sqlContent = preg_replace('/CREATE\s+DATABASE.*?;/i', '', $sqlContent);
            $sqlContent = preg_replace('/USE\s+`?[a-zA-Z0-9_]+`?\s*;/i', '', $sqlContent);
            $db->exec($sqlContent);
        }

        // Eğer sql dosyası yoksa veya eksik tablo kaldıysa tüm mimariyi garantiye al
        $db->exec("
            SET FOREIGN_KEY_CHECKS = 0;

            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ogrenci_no VARCHAR(30) DEFAULT NULL,
                email VARCHAR(150) DEFAULT NULL,
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
                anadal_bolum VARCHAR(150) DEFAULT 'Elektrik-Elektronik Mühendisliği',
                anadal_bolumu VARCHAR(150) DEFAULT 'Elektrik-Elektronik Mühendisliği',
                cap_bolum VARCHAR(150) DEFAULT 'Bilgisayar Mühendisliği',
                anadal_gano DECIMAL(4,2) DEFAULT 3.25,
                anadal_gano_kaynak VARCHAR(100) DEFAULT 'Öğrenci Transkripti',
                cap_gano DECIMAL(4,2) DEFAULT 3.10,
                mezuniyet_ortalamasi DECIMAL(4,2) DEFAULT 0.00,
                toplam_akts INT DEFAULT 60,
                gano_dusme_sayisi INT DEFAULT 0,
                ust_uste_ders_almama INT DEFAULT 0,
                durum VARCHAR(50) DEFAULT 'Aktif',
                durum_gerekcesi TEXT DEFAULT NULL,
                koordinator_ozel_notu TEXT DEFAULT NULL,
                kayit_yili INT DEFAULT 2024,
                mevcut_yariyil INT DEFAULT 3,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS course_catalog (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ders_kodu VARCHAR(30) NOT NULL,
                ders_adi VARCHAR(150) NOT NULL,
                akts INT NOT NULL DEFAULT 5,
                kredi INT NOT NULL DEFAULT 3,
                yariyil INT DEFAULT 1,
                program_turu VARCHAR(20) DEFAULT 'ORTAK',
                zorunlu_mu TINYINT(1) DEFAULT 1,
                is_active TINYINT(1) DEFAULT 1,
                aktif TINYINT(1) DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS student_courses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                course_id INT NOT NULL,
                akademik_yil VARCHAR(20) DEFAULT '2025-2026',
                donem VARCHAR(20) DEFAULT 'Güz',
                deneme_sayisi INT DEFAULT 1,
                vize_notu DECIMAL(5,2) DEFAULT NULL,
                final_notu DECIMAL(5,2) DEFAULT NULL,
                butunleme_notu DECIMAL(5,2) DEFAULT NULL,
                sayisal_ortalama DECIMAL(5,2) DEFAULT NULL,
                harf_notu VARCHAR(5) DEFAULT NULL,
                katsayi DECIMAL(3,2) DEFAULT NULL,
                akts INT DEFAULT 5,
                durum VARCHAR(50) DEFAULT 'Geçti',
                onay_durumu VARCHAR(50) DEFAULT 'Onaylandı',
                degisiklik_gerekcesi TEXT DEFAULT NULL,
                islem_yapan_id INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS student_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                belge_turu VARCHAR(100) NOT NULL DEFAULT 'Transkript',
                belge_adi VARCHAR(200) DEFAULT 'Güncel Transkript Belgesi',
                akademik_yil VARCHAR(20) DEFAULT '2025-2026',
                donem VARCHAR(20) DEFAULT 'Güz',
                dosya_adi VARCHAR(255) DEFAULT 'transkript_203311041.pdf',
                sunucu_dosya_yolu VARCHAR(255) DEFAULT 'uploads/transkript_203311041.pdf',
                dosya_yolu VARCHAR(255) DEFAULT 'uploads/transkript_203311041.pdf',
                onay_durumu VARCHAR(50) DEFAULT 'Onaylandı',
                red_veya_talep_notu TEXT DEFAULT NULL,
                aciklama TEXT DEFAULT NULL,
                yukleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS regulation_rules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                kural_kodu VARCHAR(50) NOT NULL,
                kural_adi VARCHAR(150) NOT NULL,
                deger VARCHAR(100) NOT NULL,
                aciklama TEXT DEFAULT NULL,
                versiyon VARCHAR(20) DEFAULT 'v1.0',
                is_active TINYINT(1) DEFAULT 1,
                aktif TINYINT(1) DEFAULT 1,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                islem_turu VARCHAR(100) NOT NULL,
                tablo_adi VARCHAR(100) DEFAULT NULL,
                kayit_id INT DEFAULT NULL,
                eski_deger TEXT DEFAULT NULL,
                yeni_deger TEXT DEFAULT NULL,
                aciklama TEXT DEFAULT NULL,
                ip_adresi VARCHAR(45) DEFAULT NULL,
                tarih TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                gonderen_user_id INT NOT NULL,
                alici_user_id INT DEFAULT NULL,
                sender_id INT DEFAULT NULL,
                receiver_id INT DEFAULT NULL,
                konu VARCHAR(200) NOT NULL,
                mesaj TEXT NOT NULL,
                ekli_dosya_yolu VARCHAR(255) DEFAULT NULL,
                belge_talebi_mi TINYINT(1) DEFAULT 0,
                talep_durumu VARCHAR(50) DEFAULT 'Beklemede',
                okundu_mu TINYINT(1) DEFAULT 0,
                gonderim_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            SET FOREIGN_KEY_CHECKS = 1;
        ");

        // Kullanıcı tablosu boşsa varsayılan koordinatör ve öğrenci hesaplarını ekle (Şifre: 123456)
        $userCount = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($userCount === 0) {
            $defaultHash = password_hash('123456', PASSWORD_DEFAULT);

            $stmtUser = $db->prepare("
                INSERT INTO users (id, ogrenci_no, email, password_hash, password, sifre, ad_soyad, rol, role)
                VALUES (:id, :ogr_no, :email, :hash, :pass, :sifre, :ad, :rol, :role)
            ");

            // 1. Koordinatör Hesabı
            $stmtUser->execute([
                ':id'     => 1,
                ':ogr_no' => 'koordinator',
                ':email'  => 'koordinator@selcuk.edu.tr',
                ':hash'   => $defaultHash,
                ':pass'   => '123456',
                ':sifre'  => '123456',
                ':ad'     => 'Doç. Dr. ÇAP/YDP Koordinatörü',
                ':rol'    => 'koordinator',
                ':role'   => 'koordinator'
            ]);

            // 2. ÇAP Öğrencisi (203311041)
            $stmtUser->execute([
                ':id'     => 2,
                ':ogr_no' => '203311041',
                ':email'  => '203311041@ogr.selcuk.edu.tr',
                ':hash'   => $defaultHash,
                ':pass'   => '123456',
                ':sifre'  => '123456',
                ':ad'     => 'Ahmet Yılmaz',
                ':rol'    => 'ogrenci',
                ':role'   => 'ogrenci'
            ]);

            // 3. YDP Öğrencisi (213311055)
            $stmtUser->execute([
                ':id'     => 3,
                ':ogr_no' => '213311055',
                ':email'  => '213311055@ogr.selcuk.edu.tr',
                ':hash'   => $defaultHash,
                ':pass'   => '123456',
                ':sifre'  => '123456',
                ':ad'     => 'Zeynep Kaya',
                ':rol'    => 'ogrenci',
                ':role'   => 'ogrenci'
            ]);

            // Öğrenci detay kayıtlarını ekle
            $db->exec("
                INSERT INTO students (id, user_id, ogrenci_no, ad_soyad, email, program_turu, anadal_bolum, anadal_bolumu, cap_bolum, anadal_gano, cap_gano, toplam_akts, durum)
                VALUES
                (1, 2, '203311041', 'Ahmet Yılmaz', '203311041@ogr.selcuk.edu.tr', 'CAP', 'Elektrik-Elektronik Mühendisliği', 'Elektrik-Elektronik Mühendisliği', 'Bilgisayar Mühendisliği', 3.42, 3.15, 65, 'Aktif'),
                (2, 3, '213311055', 'Zeynep Kaya', '213311055@ogr.selcuk.edu.tr', 'YDP', 'Makine Mühendisliği', 'Makine Mühendisliği', 'Bilgisayar Mühendisliği', 3.10, 2.85, 45, 'Aktif');

                INSERT INTO course_catalog (id, ders_kodu, ders_adi, akts, kredi, yariyil, program_turu)
                VALUES
                (1, 'BM101', 'Algoritma ve Programlama I', 6, 4, 1, 'ORTAK'),
                (2, 'BM201', 'Veri Yapıları', 6, 4, 3, 'ORTAK'),
                (3, 'BM204', 'Veritabanı Yönetim Sistemleri', 5, 3, 4, 'ORTAK'),
                (4, 'BM301', 'İşletim Sistemleri', 5, 3, 5, 'CAP');

                INSERT INTO student_courses (student_id, course_id, akademik_yil, donem, vize_notu, final_notu, sayisal_ortalama, harf_notu, akts, durum, onay_durumu, islem_yapan_id)
                VALUES
                (1, 1, '2025-2026', 'Güz', 85, 90, 88.00, 'AA', 6, 'Geçti', 'Onaylandı', 1),
                (1, 2, '2025-2026', 'Güz', 75, 80, 78.00, 'BB', 6, 'Geçti', 'Onaylandı', 1);

                INSERT INTO regulation_rules (kural_kodu, kural_adi, deger, aciklama, versiyon)
                VALUES
                ('CAP_MIN_GANO', 'ÇAP Başvuru ve Devam Min. Anadal GANO', '2.72', 'Anadal GANO en az 2.72 olmalıdır.', '2026/v1'),
                ('YDP_MIN_GANO', 'Yandal Min. Anadal GANO', '2.50', 'Anadal GANO en az 2.50 olmalıdır.', '2026/v1'),
                ('MAX_GANO_DUSME', 'Maksimum GANO Düşme Sayısı', '1', 'Anadal GANO bir defaya mahsus 2.50 altına düşebilir.', '2026/v1');
            ");
        }
    }
} catch (PDOException $setupEx) {
    // Tablo kurulum uyarısı olursa sessizce devam et
}

// CSRF ve Yardımcı Güvenlik Fonksiyonları
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
