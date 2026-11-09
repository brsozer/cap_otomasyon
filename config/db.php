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

// Güvenli çıktı fonksiyonu
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

// CSRF fonksiyonları
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

// LOG fonksiyonu
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

// KRİTİK: Veritabanından öğrencinin gerçek derslerine bakarak AKTS ve GANO'yu canlı hesaplayan fonksiyon
if (!function_exists('degerlendir_ogrenci_kurallari')) {
    function degerlendir_ogrenci_kurallari($db, $student_id) {
        $sonuc = [
            'tamamlanan_akts' => 0,
            'hedef_akts'      => 135,
            'kalan_akts'      => 135,
            'bolum_ortalamasi' => 0.00,
            'durum'           => 'Uygun',
            'mesaj'           => 'Akademik durum hesaplanıyor.'
        ];

        try {
            // Tamamlanan AKTS'leri öğrencinin ders tablosundan ve katalogdan topla
            $stmt = $db->prepare("
                SELECT SUM(COALESCE(sc.akts, cc.akts, 0)) as toplam 
                FROM student_courses sc 
                LEFT JOIN course_catalog cc ON sc.course_id = cc.id 
                WHERE sc.student_id = ? AND (sc.durum = 'Geçti' OR sc.durum = 'GEÇTİ' OR sc.onay_durumu = 'Onaylı' OR sc.harf_notu IN ('AA','BA','BB','CB','CC'))
            ");
            $stmt->execute([$student_id]);
            $row = $stmt->fetch();
            if ($row && isset($row['toplam'])) {
                $sonuc['tamamlanan_akts'] = (int)$row['toplam'];
            }

            $sonuc['kalan_akts'] = max(0, $sonuc['hedef_akts'] - $sonuc['tamamlanan_akts']);

            // Ortalama hesapla
            $stmt2 = $db->prepare("
                SELECT AVG(sayisal_ortalama) as ort 
                FROM student_courses 
                WHERE student_id = ? AND sayisal_ortalama > 0
            ");
            $stmt2->execute([$student_id]);
            $row2 = $stmt2->fetch();
            if ($row2 && isset($row2['ort'])) {
                $sonuc['bolum_ortalamasi'] = round((float)$row2['ort'], 2);
            }
        } catch (Exception $e) {
            // Veritabanı sorgu hatasında varsayılan kalır
        }

        return $sonuc;
    }
}
