<?php
/**
 * admin/log_action.php
 * Reusable helper to log an admin action.
 * 
 * Usage:
 *   include 'log_action.php';
 *   log_action($conn, 'Create Hostel', 'hostel', $new_id, "Added hostel: $name");
 */

if (!function_exists('log_action')) {
    function log_action($conn, $action, $entity_type = null, $entity_id = null, $description = '') {
        // Detect admin info
        $admin_id   = $_SESSION['admin_id'] ?? 0;
        $admin_name = $_SESSION['admin_name'] ?? 'Administrator';

        // Get IP address
        $ip = 'Unknown';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $ip = substr(trim($ip), 0, 45);

        // User agent (truncated)
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        // Insert log
        $stmt = $conn->prepare("
            INSERT INTO activity_logs 
            (admin_id, admin_name, action, entity_type, entity_id, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) return false;

        $entity_id = $entity_id ?: null;
        $stmt->bind_param(
            "isssisss",
            $admin_id, $admin_name, $action, $entity_type,
            $entity_id, $description, $ip, $ua
        );
        return $stmt->execute();
    }
}