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

// Güvenli Log İşlem Fonksiyonu (Dizi parametrelerini stringe çevirerek çakışmaları önler)
if (!function_exists('log_islem')) {
    function log_islem($db, $islem_turu, $tablo_adi = null, $kayit_id = null, $aciklama = null, $eski_deger = null, $yeni_deger = null) {
        try {
            $uid = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
            $ip  = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            if (is_array($eski_deger)) { $eski_deger = json_encode($eski_deger, JSON_UNESCAPED_UNICODE); }
            if (is_array($yeni_deger)) { $yeni_deger = json_encode($yeni_deger, JSON_UNESCAPED_UNICODE); }
            if (is_array($aciklama))   { $aciklama   = json_encode($aciklama, JSON_UNESCAPED_UNICODE); }

            $stmt = $db->prepare("INSERT INTO audit_logs (user_id, islem_turu, tablo_adi, kayit_id, eski_deger, yeni_deger, aciklama, ip_adresi) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$uid, $islem_turu, $tablo_adi, $kayit_id, $eski_deger, $yeni_deger, $aciklama, $ip]);
        } catch (PDOException $ex) {}
    }
}

if (!function_exists('degerlendir_ogrenci_kurallari')) {
    function degerlendir_ogrenci_kurallari($db, $student_arg) {
        $sonuc = [
            'tamamlanan_akts'  => 0,
            'hedef_akts'       => 135,
            'kalan_akts'       => 135,
            'bolum_ortalamasi' => 0.00,
            'durum'            => 'Uygun',
            'mesaj'            => 'Yönergeye Uygun',
            'uyarilar'         => []
        ];

        $student_id = null;
        $gano = 0.0;
        $prog = 'CAP';

        if (is_array($student_arg)) {
            $student_id = $student_arg['id'] ?? null;
            $gano = (float)($student_arg['anadal_gano'] ?? 0);
            $prog = strtoupper($student_arg['program_turu'] ?? 'CAP');
        } else {
            $student_id = $student_arg;
            $stStmt = $db->prepare("SELECT * FROM students WHERE id = ?");
            $stStmt->execute([$student_id]);
            $stData = $stStmt->fetch();
            if ($stData) {
                $gano = (float)($stData['anadal_gano'] ?? 0);
                $prog = strtoupper($stData['program_turu'] ?? 'CAP');
            }
        }

        // Koordinatörün yönerge ayarlarından son belirlediği dinamik kuralları çek
        $kurallar = [];
        try {
            $kQuery = $db->query("SELECT kural_kodu, deger FROM regulation_rules WHERE is_active = 1 ORDER BY id DESC");
            if ($kQuery) {
                while ($row = $kQuery->fetch()) {
                    if (!isset($kurallar[$row['kural_kodu']])) {
                        $kurallar[$row['kural_kodu']] = (float)$row['deger'];
                    }
                }
            }
        } catch (Exception $e) {}

        // Hedef AKTS'yi veritabanındaki güncel kurala göre dinamik ata
        if ($prog === 'CAP') {
            $sonuc['hedef_akts'] = (int)($kurallar['CAP_PROTOKOL_TOPLAM_AKTS'] ?? 135);
        } elseif ($prog === 'YDP') {
            $sonuc['hedef_akts'] = (int)($kurallar['YDP_MIN_AKTS'] ?? 30);
        }

        // Öğrencinin tamamlanan ders kredilerini hesapla
        if ($student_id) {
            try {
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
            } catch (Exception $e) {}
        }

        // GANO eşik kontrolü (Koordinatörün son güncellediği değere göre dinamik kıyaslar)
        if ($prog === 'CAP') {
            $asgariGano = $kurallar['CAP_MIN_ANADAL_GANO'] ?? 2.72;
            if ($gano < $asgariGano) {
                $sonuc['durum'] = 'Uygun Değil';
                $sonuc['mesaj'] = 'Anadal GANO Asgari Eşiğin Altında!';
                $sonuc['uyarilar'][] = [
                    'tip' => 'danger',
                    'mesaj' => "Dikkat: Anadal GANO'su ($gano), koordinatörün belirlediği $asgariGano eşiğinin altında!"
                ];
            }
        } elseif ($prog === 'YDP') {
            $asgariGano = $kurallar['YDP_MIN_ANADAL_GANO'] ?? 2.30;
            if ($gano < $asgariGano) {
                $sonuc['durum'] = 'Uygun Değil';
                $sonuc['mesaj'] = 'Anadal GANO Asgari Eşiğin Altında!';
                $sonuc['uyarilar'][] = [
                    'tip' => 'danger',
                    'mesaj' => "Dikkat: Yandal Anadal GANO'su ($gano), koordinatörün belirlediği $asgariGano eşiğinin altında!"
                ];
            }
        }

        return $sonuc;
    }
}
