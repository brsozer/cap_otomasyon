<?php
// logout.php
session_start();
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    log_islem($db, 'OTURUM_KAPATMA', 'users', $_SESSION['user_id'], "Kullanıcı güvenli çıkış yaptı.");
}

$_SESSION = [];
session_destroy();

// Safari ve tarayıcıların önbellekte tutmasını kesin olarak engelle (Geri tuşu güvenliği)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

header("Location: login.php");
exit;
?>
