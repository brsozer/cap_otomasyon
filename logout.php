<?php
// logout.php
require_once 'config/db.php';
if (isset($_SESSION['user_id'])) {
    log_islem($db, 'OTURUM_KAPATMA', 'users', $_SESSION['user_id'], "Kullanıcı güvenli çıkış yaptı.");
}
$_SESSION = [];
session_destroy();
header("Location: login.php");
exit;
?>
