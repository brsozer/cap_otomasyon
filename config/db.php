<?php
// config/db.php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host     = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: 'localhost');
$port     = getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: '8889');
$dbname   = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'cap_yandal_db');
$username = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root');
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : 'root');

try {
    $db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    try {
        $db = new PDO("mysql:host=$host;port=3306;dbname=$dbname;charset=utf8mb4", $username, '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $ex) {
        die("Veritabanı bağlantısı kurulamadı. Lütfen config/db.php ayarlarını veya veritabanı servisini kontrol edin.");
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}
function verify_csrf() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Güvenlik doğrulaması başarısız (CSRF Token Hatası).");
    }
}

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function log_islem($db, $islem_turu, $tablo_adi, $kayit_id, $aciklama, $eski_deger = null, $yeni_deger = null) {
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    try {
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, islem_turu, tablo_adi, kayit_id, eski_deger, yeni_deger, aciklama, ip_adresi) 
                              VALUES (:uid, :islem, :tablo, :kid, :eski, :yeni, :aciklama, :ip)");
        $stmt->execute([
            ':uid'      => $user_id,
            ':islem'    => $islem_turu,
            ':tablo'    => $tablo_adi,
            ':kid'      => $kayit_id,
            ':eski'     => $eski_deger ? json_encode($eski_deger, JSON_UNESCAPED_UNICODE) : null,
            ':yeni'     => $yeni_deger ? json_encode($yeni_deger, JSON_UNESCAPED_UNICODE) : null,
            ':aciklama' => $aciklama,
            ':ip'       => $ip
        ]);
    } catch (Exception $e) {
        error_log("Audit Log Hatası: " . $e->getMessage());
    }
}

function get_rule($db, $kod, $default = 0) {
    $stmt = $db->prepare("SELECT deger FROM regulation_rules WHERE kural_kodu = ? AND is_active = 1 ORDER BY versiyon DESC LIMIT 1");
    $stmt->execute([$kod]);
    $row = $stmt->fetch();
    return $row ? (float)$row['deger'] : $default;
}

function degerlendir_ogrenci_kurallari($db, $student) {
    $uyarilar = [];
    $sid = $student['id'];
    $prog = $student['program_turu'];

    $stmt = $db->prepare("SELECT sc.*, cc.akts 
                          FROM student_courses sc 
                          JOIN course_catalog cc ON sc.course_id = cc.id 
                          WHERE sc.student_id = ? AND sc.onay_durumu = 'onaylandi'");
    $stmt->execute([$sid]);
    $dersler = $stmt->fetchAll();

    $tamamlanan_akts = 0;
    $planlanan_akts = 0;
    $gecilen_ders_sayisi = 0;
    $not_toplam = 0;
    $notlu_ders_sayisi = 0;

    foreach ($dersler as $d) {
        if ($d['durum'] === 'gecti' || $d['durum'] === 'muaf') {
            $tamamlanan_akts += (int)$d['akts'];
            $gecilen_ders_sayisi++;
        } elseif ($d['durum'] === 'alindi' || $d['durum'] === 'planlandi') {
            $planlanan_akts += (int)$d['akts'];
        }
        if ($d['durum'] === 'gecti' && $d['sayisal_ortalama'] !== null) {
            $not_toplam += (float)$d['sayisal_ortalama'];
            $notlu_ders_sayisi++;
        }
    }

    $bolum_ortalamasi = $notlu_ders_sayisi > 0 ? round($not_toplam / $notlu_ders_sayisi, 2) : 0;

    if ((int)$student['ust_uste_ders_almama'] >= 2) {
        $uyarilar[] = [
            'tip' => 'danger',
            'mesaj' => "KRİTİK YÖNERGE UYARISI: Öğrenci iki dönem üst üste ders almamıştır. Yönerge gereği kayıt silme değerlendirmesi yapılmalıdır."
        ];
    }

    if ($prog === 'CAP') {
        $min_gano   = get_rule($db, 'CAP_MIN_ANADAL_GANO', 2.72);
        $alt_limit  = get_rule($db, 'CAP_TEK_SEFER_GANO_ALT_LIMIT', 2.50);
        $min_ort    = get_rule($db, 'CAP_MIN_MEZUNIYET_ORT', 70.00);
        $alt_ort    = get_rule($db, 'CAP_TEK_SEFER_ORT_ALT_LIMIT', 65.00);
        $hedef_akts = get_rule($db, 'CAP_PROTOKOL_TOPLAM_AKTS', 135.00);

        if ($student['anadal_gano'] > 0 && $student['anadal_gano'] < $alt_limit) {
            $uyarilar[] = [
                'tip' => 'danger',
                'mesaj' => "Yönerge Madde 8/e: Anadal GANO ({$student['anadal_gano']}) alt limit olan {$alt_limit}'nin altına düşmüştür!"
            ];
        } elseif ($student['anadal_gano'] > 0 && $student['anadal_gano'] < $min_gano) {
            if ($student['gano_dusme_sayisi'] >= 2) {
                $uyarilar[] = [
                    'tip' => 'danger',
                    'mesaj' => "Yönerge Madde 8/e: Anadal GANO ({$student['anadal_gano']}) ikinci kez {$min_gano}'nin altına düşmüştür. Kayıt silme şartı oluşmuştur."
                ];
            } else {
                $uyarilar[] = [
                    'tip' => 'warning',
                    'mesaj' => "Yönerge Uyarısı: Anadal GANO ({$student['anadal_gano']}) {$min_gano}'nin altındadır (1 defaya mahsus hak kullanılıyor)."
                ];
            }
        }

        if ($notlu_ders_sayisi > 0 && $bolum_ortalamasi < $alt_ort) {
            $uyarilar[] = [
                'tip' => 'danger',
                'mesaj' => "Yönetmelik Madde 17/4: ÇAP başarı ortalaması ({$bolum_ortalamasi}), alt sınır olan {$alt_ort}'in altına düşmüştür!"
            ];
        } elseif ($notlu_ders_sayisi > 0 && $bolum_ortalamasi < $min_ort) {
            $uyarilar[] = [
                'tip' => 'warning',
                'mesaj' => "Yönetmelik Madde 17/4: ÇAP genel başarı ortalaması ({$bolum_ortalamasi}), mezuniyet barajı olan {$min_ort} puanın altındadır."
            ];
        }

        if ($tamamlanan_akts >= $hedef_akts && $bolum_ortalamasi >= $min_ort && $student['anadal_gano'] >= $min_gano) {
            $uyarilar[] = [
                'tip' => 'success',
                'mesaj' => "MEZUNİYETE UYGUN: Öğrenci {$tamamlanan_akts} AKTS ve GANO şartlarını sağlamıştır. Koordinatör mezuniyet kararı verebilir."
            ];
        }
    }

    if ($prog === 'YDP') {
        $ydp_min_anadal = get_rule($db, 'YDP_MIN_ANADAL_GANO', 2.30);
        $ydp_hedef_akts = get_rule($db, 'YDP_MIN_AKTS', 30.00);
        $ydp_min_ders   = get_rule($db, 'YDP_MIN_DERS_SAYISI', 6.00);

        if ($student['anadal_gano'] > 0 && $student['anadal_gano'] < $ydp_min_anadal) {
            $uyarilar[] = [
                'tip' => 'danger',
                'mesaj' => "Yandal Yönergesi Madde 7/5: Anadal GANO ({$student['anadal_gano']}), {$ydp_min_anadal} sınırının altına düşmüştür. Kayıt silme uyarısı!"
            ];
        }

        if ($tamamlanan_akts >= $ydp_hedef_akts && $gecilen_ders_sayisi >= $ydp_min_ders) {
            $uyarilar[] = [
                'tip' => 'success',
                'mesaj' => "SERTİFİKAYA UYGUN: Öğrenci en az {$ydp_min_ders} ders ve {$ydp_hedef_akts} AKTS şartını tamamlamıştır. Yandal Sertifikası kararı verilebilir."
            ];
        }
    }

    $hedef = $prog === 'CAP' ? get_rule($db, 'CAP_PROTOKOL_TOPLAM_AKTS', 135) : get_rule($db, 'YDP_MIN_AKTS', 30);
    $kalan_akts = max(0, $hedef - $tamamlanan_akts);

    return [
        'tamamlanan_akts'     => $tamamlanan_akts,
        'planlanan_akts'      => $planlanan_akts,
        'hedef_akts'          => $hedef,
        'kalan_akts'          => $kalan_akts,
        'gecilen_ders_sayisi' => $gecilen_ders_sayisi,
        'bolum_ortalamasi'    => $bolum_ortalamasi,
        'uyarilar'            => $uyarilar
    ];
}
?>
