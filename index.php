<?php
// index.php
require_once 'config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

switch ($_SESSION['role']) {
    case 'koordinator':    header("Location: admin/index.php"); break;
    case 'bolum_baskani':  header("Location: bolumbaskani/index.php"); break;
    case 'ogrenci_isleri': header("Location: ogrenciisleri/index.php"); break;
    case 'ogrenci':        header("Location: ogrenci/index.php"); break;
    default:               header("Location: login.php"); break;
}
exit;
?>
