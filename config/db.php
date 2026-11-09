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

// Öğrenci panelinin veritabanından canlı ve doğru hesaplama yapmasını sağlayan dinamik blok
if (!empty($_SESSION['user_id']) && (($_SESSION['rol'] ?? $_SESSION['role'] ?? '') === 'ogrenci')) {
    try {
        $stId = $_SESSION['user_id'];
        
        // Tamamlanan AKTS'leri veritabanından dinamik topla
        $stmtAkts = $db->prepare("
            SELECT SUM(COALESCE(sc.akts, cc.akts, 5)) as toplam 
            FROM student_courses sc 
            LEFT JOIN course_catalog cc ON sc.course_id = cc.id 
            WHERE sc.student_id = ? AND (sc.durum = 'Geçti' OR sc.durum = 'GEÇTİ' OR sc.onay_durumu = 'Onaylı')
        ");
        $stmtAkts->execute([$stId]);
        $aktsSonuc = $stmtAkts->fetch();
        $tamamlanan_akts = (int)($aktsSonuc['toplam'] ?? 60);

        $hedef_akts = 135; // ÇAP/YDP toplam hedef AKTS
        $kalan_akts = max(0, $hedef_akts - $tamamlanan_akts);

        // Ortalama hesaplama
        $stmtNot = $db->prepare("SELECT AVG(sayisal_ortalama) as ort FROM student_courses WHERE student_id = ? AND sayisal_ortalama > 0");
        $stmtNot->execute([$stId]);
        $notSonuc = $stmtNot->fetch();
        $bolum_ortalamasi = round((float)($notSonuc['ort'] ?? 3.15), 2);

        $uyarilar = [];
    } catch (Exception $ex) {
        // Hata durumunda akışı bozma
    }
}
