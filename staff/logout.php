<?php
session_start();
include '../config/db.php';

if (isset($_SESSION['staff_id'])) {
    $staff_id = (int)$_SESSION['staff_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    @$conn->query("INSERT INTO staff_activity_log (staff_id, action, details, ip_address) 
                   VALUES ($staff_id, 'logout', 'Staff logged out', '$ip')");
}

session_unset();
session_destroy();
header("Location: login.php");
exit;