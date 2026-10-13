<?php
session_start();
include '../config/db.php';
include 'log_action.php';

if (isset($_SESSION['admin_id'])) {
    log_action($conn, 'logout', 'admin', (int)$_SESSION['admin_id'],
        "Admin " . ($_SESSION['admin_name'] ?? 'unknown') . " logged out");
}

session_unset();
session_destroy();
header("Location: login.php");
exit;