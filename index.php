<?php
// index.php
require_once 'config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
// ogrenci/index.php dosyasının en üstüne ekle:
if (!isset($tamamlanan_akts)) $tamamlanan_akts = 0;
if (!isset($hedef_akts)) $hedef_akts = 60;
if (!isset($kalan_akts)) $kalan_akts = 60;
if (!isset($bolum_ortalamasi)) $bolum_ortalamasi = 0.00;
if (!isset($uyarilar) || !is_array($uyarilar)) $uyarilar = [];


switch ($_SESSION['role']) {
    case 'koordinator':    header("Location: admin/index.php"); break;
    case 'bolum_baskani':  header("Location: bolumbaskani/index.php"); break;
    case 'ogrenci_isleri': header("Location: ogrenciisleri/index.php"); break;
    case 'ogrenci':        header("Location: ogrenci/index.php"); break;
    default:               header("Location: login.php"); break;
}
exit;
?>
