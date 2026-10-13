<?php
/**
 * Central activity logger for admin panel.
 * 
 * Usage:
 *   log_action($conn, 'create', 'room', $id, "Added room A101");
 *   log_action($conn, 'login', 'admin', $admin_id, "Admin logged in");
 */

if (!function_exists('log_action')) {
    function log_action($conn, string $action, ?string $entity_type = null, $entity_id = null, ?string $description = null): bool
    {
        // Check if table exists (cached per request)
        static $table_exists = null;
        if ($table_exists === null) {
            $res = @$conn->query("SHOW TABLES LIKE 'activity_logs'");
            $table_exists = ($res && $res->num_rows > 0);
        }
        if (!$table_exists) return false;

        $admin_id   = $_SESSION['admin_id']   ?? $_SESSION['staff_id']   ?? null;
        $admin_name = $_SESSION['admin_name'] ?? $_SESSION['staff_name'] ?? 'System';
        $ip         = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua         = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $eid        = ($entity_id !== null) ? (int)$entity_id : null;

        $stmt = @$conn->prepare("
            INSERT INTO activity_logs 
                (admin_id, admin_name, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt) {
            error_log("log_action: prepare failed - " . $conn->error);
            return false;
        }

        $stmt->bind_param(
            "isssisss",
            $admin_id, $admin_name, $action, $entity_type,
            $eid, $description, $ip, $ua
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

// Alias for consistency with earlier code
if (!function_exists('logActivity')) {
    function logActivity($conn, string $action, ?string $entity_type = null, ?int $entity_id = null, ?string $description = null): bool {
        return log_action($conn, $action, $entity_type, $entity_id, $description);
    }
}