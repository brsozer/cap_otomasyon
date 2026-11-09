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

// TAM MİMARİ VE 5 TEST HESABI OTOMATİK KURULUMU
try {
    $isFullyInstalled = false;
    $tableExists = $db->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($tableExists) {
        $stmtCheck = $db->query("SELECT COUNT(*) FROM users WHERE email = 'bolumbaskani@selcuk.edu.tr'");
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
            $sqlContent = preg_replace('/CREATE\s+DATABASE.*?;/i', '', $sqlContent);
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
                anadal_bolum VARCHAR(150) DEFAULT 'Elektrik-Elektronik Mühendisliği',
                anadal_bolumu VARCHAR(150) DEFAULT 'Elektrik-Elektronik Mühendisliği',
                cap_bolum VARCHAR(150) DEFAULT 'Bilgisayar Mühendisliği',
                anadal_gano DECIMAL(4,2) DEFAULT 3.25,
                anadal_gano_kaynak VARCHAR(150) DEFAULT 'Onaylı Transkript Belgesi',
                cap_gano DECIMAL(4,2) DEFAULT 3.10,
                ydp_gano DECIMAL(4,2) DEFAULT 2.90,
                mezuniyet_ortalamasi DECIMAL(5,2) DEFAULT 78.50,
                toplam_akts INT DEFAULT 60,
                tamamlanan_akts INT DEFAULT 60,
                gano_dusme_sayisi INT DEFAULT 0,
                ust_uste_ders_almama INT DEFAULT 0,
                durum VARCHAR(50) DEFAULT 'Aktif',
                durum_gerekcesi TEXT DEFAULT NULL,
                mezuniyet_karari VARCHAR(100) DEFAULT 'Devam Ediyor',
                koordinator_ozel_notu TEXT DEFAULT NULL,
                kayit_yili INT DEFAULT 2024,
                mevcut_yariyil INT DEFAULT 3,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS course_catalog (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ders_kodu VARCHAR(30) NOT NULL,
                course_code VARCHAR(30) DEFAULT NULL,
                ders_adi VARCHAR(150) NOT NULL,
                course_name VARCHAR(150) DEFAULT NULL,
                akts INT NOT NULL DEFAULT 5,
                kredi INT NOT NULL DEFAULT 3,
                yariyil INT DEFAULT 1,
                donem VARCHAR(20) DEFAULT 'Güz',
                program_turu VARCHAR(20) DEFAULT 'ORTAK',
                bolum_dersi_mi TINYINT(1) DEFAULT 0,
                zorunlu_mu TINYINT(1) DEFAULT 1,
                is_active TINYINT(1) DEFAULT 1,
                aktif TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS student_courses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                course_id INT NOT NULL,
                ders_kodu VARCHAR(30) DEFAULT NULL,
                ders_adi VARCHAR(150) DEFAULT NULL,
                bolum_dersi_mi TINYINT(1) DEFAULT 0,
                akademik_yil VARCHAR(20) DEFAULT '2025-2026',
                donem VARCHAR(20) DEFAULT 'Güz',
                yariyil INT DEFAULT 1,
                deneme_sayisi INT DEFAULT 1,
                vize_notu DECIMAL(5,2) DEFAULT NULL,
                final_notu DECIMAL(5,2) DEFAULT NULL,
                butunleme_notu DECIMAL(5,2) DEFAULT NULL,
                sayisal_ortalama DECIMAL(5,2) DEFAULT NULL,
                harf_notu VARCHAR(5) DEFAULT NULL,
                katsayi DECIMAL(3,2) DEFAULT NULL,
                akts INT DEFAULT 5,
                kredi INT DEFAULT 3,
                durum VARCHAR(50) DEFAULT 'Geçti',
                onay_durumu VARCHAR(50) DEFAULT 'Onaylı',
                degisiklik_gerekcesi TEXT DEFAULT NULL,
                islem_yapan_id INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS student_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                user_id INT DEFAULT NULL,
                belge_turu VARCHAR(100) NOT NULL DEFAULT 'Transkript',
                document_type VARCHAR(100) DEFAULT 'Transkript',
                belge_adi VARCHAR(200) DEFAULT 'Güncel Transkript Belgesi',
                akademik_yil VARCHAR(20) DEFAULT '2025-2026',
                donem VARCHAR(20) DEFAULT 'Güz',
                yariyil INT DEFAULT 3,
                dosya_adi VARCHAR(255) DEFAULT 'transkript.pdf',
                file_name VARCHAR(255) DEFAULT 'transkript.pdf',
                sunucu_dosya_yolu VARCHAR(255) DEFAULT 'uploads/transkript.pdf',
                dosya_yolu VARCHAR(255) DEFAULT 'uploads/transkript.pdf',
                file_path VARCHAR(255) DEFAULT 'uploads/transkript.pdf',
                dosya_hash VARCHAR(128) DEFAULT NULL,
                dosya_boyutu INT DEFAULT 102400,
                file_size INT DEFAULT 102400,
                beyan_gano DECIMAL(4,2) DEFAULT 3.42,
                anadal_gano DECIMAL(4,2) DEFAULT 3.42,
                onay_durumu VARCHAR(50) DEFAULT 'Onaylandı',
                durum VARCHAR(50) DEFAULT 'Onaylandı',
                status VARCHAR(50) DEFAULT 'Onaylandı',
                red_veya_talep_notu TEXT DEFAULT NULL,
                red_nedeni TEXT DEFAULT NULL,
                aciklama TEXT DEFAULT NULL,
                inceleyen_id INT DEFAULT NULL,
                onaylayan_id INT DEFAULT NULL,
                incelenme_tarihi TIMESTAMP NULL DEFAULT NULL,
                onay_tarihi TIMESTAMP NULL DEFAULT NULL,
                yukleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS regulation_rules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                kural_kodu VARCHAR(50) NOT NULL,
                kural_adi VARCHAR(150) NOT NULL,
                aciklama TEXT DEFAULT NULL,
                deger VARCHAR(100) NOT NULL,
                kural_degeri VARCHAR(100) DEFAULT NULL,
                birim VARCHAR(50) DEFAULT 'Puan/AKTS',
                program_turu VARCHAR(20) DEFAULT 'GENEL',
                versiyon VARCHAR(20) DEFAULT 'v1',
                is_active TINYINT(1) DEFAULT 1,
                aktif TINYINT(1) DEFAULT 1,
                updated_by INT DEFAULT 1,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                kullanici_id INT DEFAULT NULL,
                islem_turu VARCHAR(100) NOT NULL,
                action VARCHAR(100) DEFAULT NULL,
                tablo_adi VARCHAR(100) DEFAULT NULL,
                kayit_id INT DEFAULT NULL,
                eski_deger TEXT DEFAULT NULL,
                yeni_deger TEXT DEFAULT NULL,
                aciklama TEXT DEFAULT NULL,
                detay TEXT DEFAULT NULL,
                ip_adresi VARCHAR(45) DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                tarih TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                gonderen_user_id INT NOT NULL,
                alici_user_id INT DEFAULT NULL,
                gonderen_id INT DEFAULT NULL,
                alici_id INT DEFAULT NULL,
                sender_id INT DEFAULT NULL,
                receiver_id INT DEFAULT NULL,
                konu VARCHAR(200) NOT NULL,
                mesaj TEXT NOT NULL,
                ekli_dosya_yolu VARCHAR(255) DEFAULT NULL,
                dosya_yolu VARCHAR(255) DEFAULT NULL,
                belge_talebi_mi TINYINT(1) DEFAULT 0,
                talep_durumu VARCHAR(50) DEFAULT 'Bekliyor',
                okundu_mu TINYINT(1) DEFAULT 0,
                gonderim_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            SET FOREIGN_KEY_CHECKS = 1;
        ");

        // Eğer database_setup.sql çalışmadıysa veya kullanıcılar eksikse 5 test hesabını ve verileri ekle
        $stmtCheckUsers = $db->query("SELECT COUNT(*) FROM users WHERE email = 'bolumbaskani@selcuk.edu.tr'");
        if ((int)$stmtCheckUsers->fetchColumn() === 0) {
            $defaultHash = password_hash('123456', PASSWORD_DEFAULT);

            $stmtUser = $db->prepare("
                INSERT INTO users (id, ogrenci_no, email, password_hash, password, sifre, ad_soyad, rol, role)
                VALUES (:id, :ogr_no, :email, :hash, :pass, :sifre, :ad, :rol, :role)
            ");

            // 1. Sistem Koordinatörü
            $stmtUser->execute([
                ':id' => 1, ':ogr_no' => 'koordinator', ':email' => 'koordinator@selcuk.edu.tr',
                ':hash' => $defaultHash, ':pass' => '123456', ':sifre' => '123456',
                ':ad' => 'Doç. Dr. ÇAP/YDP Koordinatörü', ':rol' => 'koordinator', ':role' => 'koordinator'
            ]);

            // 2. ÇAP Öğrencisi (203311041@selcuk.edu.tr)
            $stmtUser->execute([
                ':id' => 2, ':ogr_no' => '203311041', ':email' => '203311041@selcuk.edu.tr',
                ':hash' => $defaultHash, ':pass' => '123456', ':sifre' => '123456',
                ':ad' => 'Ahmet Yılmaz (ÇAP Öğrencisi)', ':rol' => 'ogrenci', ':role' => 'ogrenci'
            ]);

            // 3. YDP Öğrencisi (223301099@selcuk.edu.tr)
            $stmtUser->execute([
                ':id' => 3, ':ogr_no' => '223301099', ':email' => '223301099@selcuk.edu.tr',
                ':hash' => $defaultHash, ':pass' => '123456', ':sifre' => '123456',
                ':ad' => 'Zeynep Kaya (YDP Öğrencisi)', ':rol' => 'ogrenci', ':role' => 'ogrenci'
            ]);

            // 4. Bölüm Başkanı (bolumbaskani@selcuk.edu.tr)
            $stmtUser->execute([
                ':id' => 4, ':ogr_no' => 'bolumbaskani', ':email' => 'bolumbaskani@selcuk.edu.tr',
                ':hash' => $defaultHash, ':pass' => '123456', ':sifre' => '123456',
                ':ad' => 'Prof. Dr. Bölüm Başkanı', ':rol' => 'bolum_baskani', ':role' => 'bolum_baskani'
            ]);

            // 5. Öğrenci İşleri (ogrenciisleri@selcuk.edu.tr)
            $stmtUser->execute([
                ':id' => 5, ':ogr_no' => 'ogrenciisleri', ':email' => 'ogrenciisleri@selcuk.edu.tr',
                ':hash' => $defaultHash, ':pass' => '123456', ':sifre' => '123456',
                ':ad' => 'Öğrenci İşleri Birimi', ':rol' => 'ogrenci_isleri', ':role' => 'ogrenci_isleri'
            ]);

            // Öğrenci Akademik Kartları
            $db->exec("
                INSERT INTO students (id, user_id, ogrenci_no, ad_soyad, email, program_turu, anadal_bolum, anadal_bolumu, cap_bolum, anadal_gano, anadal_gano_kaynak, cap_gano, ydp_gano, mezuniyet_ortalamasi, toplam_akts, tamamlanan_akts, gano_dusme_sayisi, ust_uste_ders_almama, durum, koordinator_ozel_notu)
                VALUES
                (1, 2, '203311041', 'Ahmet Yılmaz', '203311041@selcuk.edu.tr', 'CAP', 'Elektrik-Elektronik Mühendisliği', 'Elektrik-Elektronik Mühendisliği', 'Bilgisayar Mühendisliği', 3.42, '2025-2026 Güz Onaylı Transkript', 3.25, 0.00, 82.50, 65, 65, 0, 0, 'Aktif', 'Anadal ve ÇAP ders başarısı yüksek, düzenli takip ediliyor.'),
                (2, 3, '223301099', 'Zeynep Kaya', '223301099@selcuk.edu.tr', 'YDP', 'Makine Mühendisliği', 'Makine Mühendisliği', 'Bilgisayar Mühendisliği', 2.85, '2025-2026 Güz Onaylı Transkript', 0.00, 2.90, 74.00, 30, 30, 0, 0, 'Aktif', 'YDP zorunlu derslerini başarıyla sürdürüyor.');

                INSERT INTO course_catalog (id, ders_kodu, course_code, ders_adi, course_name, akts, kredi, yariyil, donem, program_turu, bolum_dersi_mi, zorunlu_mu, is_active, aktif)
                VALUES
                (1, 'BM101', 'BM101', 'Algoritma ve Programlama I', 'Algoritma ve Programlama I', 6, 4, 1, 'Güz', 'ORTAK', 0, 1, 1, 1),
                (2, 'BM102', 'BM102', 'Algoritma ve Programlama II', 'Algoritma ve Programlama II', 6, 4, 2, 'Bahar', 'ORTAK', 0, 1, 1, 1),
                (3, 'BM201', 'BM201', 'Veri Yapıları', 'Veri Yapıları', 6, 4, 3, 'Güz', 'ORTAK', 0, 1, 1, 1),
                (4, 'BM204', 'BM204', 'Veritabanı Yönetim Sistemleri', 'Veritabanı Yönetim Sistemleri', 5, 3, 4, 'Bahar', 'ORTAK', 0, 1, 1, 1),
                (5, 'BM301', 'BM301', 'İşletim Sistemleri', 'İşletim Sistemleri', 5, 3, 5, 'Güz', 'CAP', 0, 1, 1, 1),
                (6, 'BM304', 'BM304', 'Yazılım Mühendisliği', 'Yazılım Mühendisliği', 5, 3, 6, 'Bahar', 'ORTAK', 0, 1, 1, 1),
                (7, 'EEM205', 'EEM205', 'Sayısal Tasarım (Anadal Ortak)', 'Sayısal Tasarım (Anadal Ortak)', 5, 3, 3, 'Güz', 'CAP', 1, 1, 1, 1);

                INSERT INTO student_courses (student_id, course_id, ders_kodu, ders_adi, bolum_dersi_mi, akademik_yil, donem, yariyil, deneme_sayisi, vize_notu, final_notu, sayisal_ortalama, harf_notu, katsayi, akts, durum, onay_durumu, islem_yapan_id)
                VALUES
                (1, 1, 'BM101', 'Algoritma ve Programlama I', 0, '2024-2025', 'Güz', 1, 1, 85, 90, 88.00, 'AA', 4.00, 6, 'Geçti', 'Onaylı', 1),
                (1, 2, 'BM102', 'Algoritma ve Programlama II', 0, '2024-2025', 'Bahar', 2, 1, 78, 84, 81.60, 'BA', 3.50, 6, 'Geçti', 'Onaylı', 1),
                (1, 3, 'BM201', 'Veri Yapıları', 0, '2025-2026', 'Güz', 3, 1, 75, 80, 78.00, 'BB', 3.00, 6, 'Geçti', 'Bekliyor', 1),
                (2, 1, 'BM101', 'Algoritma ve Programlama I', 0, '2025-2026', 'Güz', 1, 1, 70, 80, 76.00, 'BB', 3.00, 6, 'Geçti', 'Onaylı', 1),
                (2, 4, 'BM204', 'Veritabanı Yönetim Sistemleri', 0, '2025-2026', 'Bahar', 2, 1, 80, 85, 83.00, 'BA', 3.50, 5, 'Geçti', 'Bekliyor', 1);

                INSERT INTO regulation_rules (kural_kodu, kural_adi, aciklama, deger, kural_degeri, birim, program_turu, versiyon, is_active, aktif)
                VALUES
                ('CAP_MIN_ANADAL_GANO', 'ÇAP Başvuru ve Devam Min. Anadal GANO', 'ÇAP öğrencisinin anadal GANO alt sınırı', '2.72', '2.72', 'GANO', 'CAP', 'v1', 1, 1),
                ('CAP_TEK_SEFER_GANO_ALT_LIMIT', 'ÇAP Tek Seferlik GANO Alt Limiti', 'Tüm öğrenim süresince bir defaya mahsus inilebilecek alt sınır', '2.50', '2.50', 'GANO', 'CAP', 'v1', 1, 1),
                ('CAP_MIN_MEZUNIYET_ORT', 'ÇAP Min. Mezuniyet Ortalaması', 'ÇAP mezuniyeti için gereken minimum ortalama', '70.00', '70.00', 'Puan', 'CAP', 'v1', 1, 1),
                ('CAP_TEK_SEFER_ORT_ALT_LIMIT', 'ÇAP Tek Seferlik Ortalama Alt Limiti', 'Bir defaya mahsus düşülebilecek puan sınırı', '65.00', '65.00', 'Puan', 'CAP', 'v1', 1, 1),
                ('CAP_PROTOKOL_TOPLAM_AKTS', 'ÇAP Protokol Toplam AKTS Yükü', 'Mezuniyet için tamamlanması gereken toplam AKTS', '135.00', '135.00', 'AKTS', 'CAP', 'v1', 1, 1),
                ('YDP_MIN_ANADAL_GANO', 'YDP Devam Min. Anadal GANO', 'Yandal öğrencisinin anadal GANO alt sınırı', '2.30', '2.30', 'GANO', 'YDP', 'v1', 1, 1),
                ('YDP_MIN_AKTS', 'YDP Sertifika Min. AKTS Yükü', 'Yandal sertifikası için gereken minimum AKTS', '30.00', '30.00', 'AKTS', 'YDP', 'v1', 1, 1),
                ('YDP_MIN_DERS_SAYISI', 'YDP Min. Ders Sayısı', 'Yandal programında alınması gereken en az ders adedi', '6.00', '6.00', 'Ders', 'YDP', 'v1', 1, 1);

                INSERT INTO student_documents (student_id, user_id, belge_turu, document_type, belge_adi, akademik_yil, donem, dosya_adi, file_name, sunucu_dosya_yolu, dosya_yolu, file_path, beyan_gano, anadal_gano, onay_durumu, durum, status, aciklama)
                VALUES
                (1, 2, 'Transkript', 'Transkript', '2025-2026 Güz Anadal Transkripti', '2025-2026', 'Güz', 'transkript_203311041.pdf', 'transkript_203311041.pdf', 'uploads/transkript_203311041.pdf', 'uploads/transkript_203311041.pdf', 'uploads/transkript_203311041.pdf', 3.42, 3.42, 'Onaylandı', 'Onaylandı', 'Onaylandı', 'Öğrenci tarafından yüklendi ve koordinatörce onaylandı.'),
                (2, 3, 'Transkript', 'Transkript', '2025-2026 Güz YDP Anadal Transkripti', '2025-2026', 'Güz', 'transkript_223301099.pdf', 'transkript_223301099.pdf', 'uploads/transkript_223301099.pdf', 'uploads/transkript_223301099.pdf', 'uploads/transkript_223301099.pdf', 2.85, 2.85, 'Bekliyor', 'Bekliyor', 'Bekliyor', 'Dönem sonu güncel transkript inceleme bekliyor.');

                INSERT INTO messages (student_id, gonderen_user_id, alici_user_id, gonderen_id, alici_id, sender_id, receiver_id, konu, mesaj, belge_talebi_mi, talep_durumu, okundu_mu)
                VALUES
                (1, 1, 2, 1, 2, 1, 2, 'Bahar Dönemi Güncel Transkript Talebi', 'Lütfen güncel dönem anadal transkriptinizi PDF formatında yükleyiniz.', 1, 'Tamamlandı', 1),
                (2, 1, 3, 1, 3, 1, 3, 'YDP Ders Seçim ve Transkript Kontrolü', 'Güz dönemi sonu onaylı transkript belgenizi sisteme yüklemeniz gerekmektedir.', 1, 'Bekliyor', 0);

                INSERT INTO audit_logs (user_id, kullanici_id, islem_turu, action, tablo_adi, kayit_id, eski_deger, yeni_deger, aciklama, detay, ip_adresi)
                VALUES
                (1, 1, 'NOT_GIRIS', 'NOT_GIRIS', 'student_courses', 1, '-', 'BM101: AA (88.00)', 'Koordinatör tarafından BM101 dersi notu işlendi ve onaylandı.', 'BM101: AA', '127.0.0.1'),
                (1, 1, 'BELGE_ONAY', 'BELGE_ONAY', 'student_documents', 1, 'Bekliyor', 'Onaylandı', '203311041 numaralı öğrencinin transkript belgesi onaylandı.', 'Transkript Onayı', '127.0.0.1');
            ");
        }
    }
} catch (PDOException $setupEx) {
    // Kurulum sırasında uyarı oluşursa çalışmayı kesme
}

// CSRF, Güvenlik ve Denetim Günlüğü Yardımcı Fonksiyonları
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
            $stmt = $db->prepare("
                INSERT INTO audit_logs (user_id, kullanici_id, islem_turu, action, tablo_adi, kayit_id, eski_deger, yeni_deger, aciklama, detay, ip_adresi, ip_address)
                VALUES (:uid, :kuid, :tur, :act, :tablo, :kid, :eski, :yeni, :aciklama, :detay, :ip, :ip2)
            ");
            $stmt->execute([
                ':uid'      => $uid,
                ':kuid'     => $uid,
                ':tur'      => $islem_turu,
                ':act'      => $islem_turu,
                ':tablo'    => $tablo_adi,
                ':kid'      => $kayit_id,
                ':eski'     => $eski_deger,
                ':yeni'     => $yeni_deger,
                ':aciklama' => $aciklama,
                ':detay'    => $aciklama,
                ':ip'       => $ip,
                ':ip2'      => $ip
            ]);
        } catch (PDOException $e) {
            // Log hatası ana akışı durdurmasın
        }
    }
}
