<?php
// includes/auth_check.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function check_access($allowed_roles = []) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php?error=unauthorized");
        exit;
    }
    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: ../index.php?error=forbidden");
        exit;
    }
}
?>
