<?php
/**
 * ONE-TIME SETUP SCRIPT
 * Visit: http://localhost/dormitory-tpi/staff/setup.php
 * Creates the first admin account.
 * DELETE THIS FILE AFTER RUNNING IT.
 */
include '../config/db.php';

// ===== EDIT THESE =====
$admin_name  = 'System Administrator';
$admin_email = 'admin@dormitory.com';
$admin_pass  = 'Admin@1234';  // Change after first login!
$admin_phone = '08012345678';
// =======================

$hash = password_hash($admin_pass, PASSWORD_DEFAULT);

// Check if already exists
$stmt = $conn->prepare("SELECT id FROM staff WHERE email = ?");
$stmt->bind_param("s", $admin_email);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    $stmt = $conn->prepare("UPDATE staff SET full_name=?, password=?, phone=?, role='admin', status='active' WHERE email=?");
    $stmt->bind_param("ssss", $admin_name, $hash, $admin_phone, $admin_email);
    $stmt->execute();
    $action = "updated";
} else {
    $stmt = $conn->prepare("INSERT INTO staff (full_name, email, password, phone, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
    $stmt->bind_param("ssss", $admin_name, $admin_email, $hash, $admin_phone);
    $stmt->execute();
    $action = "created";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Staff Setup</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body style="background:linear-gradient(135deg,#1a3a6b,#0d1b3e);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;font-family:Inter,sans-serif;">
<div class="card" style="max-width:520px;border-radius:20px;border:none;box-shadow:0 30px 80px rgba(0,0,0,0.4);">
    <div class="card-body p-5 text-center">
        <div style="width:70px;height:70px;background:linear-gradient(135deg,#ffd700,#ffb700);border-radius:18px;display:inline-flex;align-items:center;justify-content:center;font-size:2rem;color:#1a3a6b;margin-bottom:20px;">
            <i class="fas fa-user-shield"></i>
        </div>
        <h3 style="font-weight:800;color:#1a2a4a;">Staff Account <?= ucfirst($action) ?></h3>
        <p class="text-muted mb-4">Your admin account has been <?= $action ?> successfully.</p>

        <div style="background:#f8f9fa;border-radius:12px;padding:20px;text-align:left;margin-bottom:20px;">
            <div style="margin-bottom:10px;"><strong>Email:</strong> <code><?= htmlspecialchars($admin_email) ?></code></div>
            <div style="margin-bottom:10px;"><strong>Password:</strong> <code><?= htmlspecialchars($admin_pass) ?></code></div>
            <div><strong>Role:</strong> <span class="badge bg-primary">admin</span></div>
        </div>

        <div class="alert alert-warning text-start" style="border-radius:10px;">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Security Notice:</strong> Delete this file (<code>setup.php</code>) immediately and change your password after logging in.
        </div>

        <a href="login.php" class="btn btn-primary w-100" style="border-radius:10px;padding:12px;font-weight:700;">
            <i class="fas fa-sign-in-alt me-2"></i>Go to Login
        </a>
    </div>
</div>
</body>
</html>