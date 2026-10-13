<?php
session_start();
include '../config/db.php';

$ref = trim($_GET['ref'] ?? '');
$hash_prefix = trim($_GET['hash'] ?? '');

$valid = false;
$record = null;
$error = '';

if (empty($ref)) {
    $error = 'No clearance reference provided.';
} else {
    // Extract student ID from reference (e.g., TPI-HST-2026-0001-ABCD)
    if (preg_match('/TPI-HST-\d{4}-(\d+)-/', $ref, $m)) {
        $student_id = (int)$m[1];

        $student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();
        if ($student) {
            $allocation = $conn->query("
                SELECT a.*, r.room_number, r.block, h.name AS hostel_name
                FROM allocations a
                JOIN rooms r ON a.room_id = r.id
                LEFT JOIN hostels h ON r.hostel_id = h.id
                WHERE a.student_id = $student_id AND a.status = 'active'
                ORDER BY a.allocation_date DESC
                LIMIT 1
            ")->fetch_assoc();

            $payment = $conn->query("
                SELECT * FROM payments 
                WHERE student_id = $student_id AND status = 'completed'
                ORDER BY created_at DESC LIMIT 1
            ")->fetch_assoc();

            if ($allocation && $payment) {
                // Recreate hash
                $verification_string = implode('|', [
                    $ref,
                    $student['matric_no'],
                    $student['full_name'],
                    $allocation['hostel_name'] ?? 'N/A',
                    $allocation['room_number'],
                    $allocation['block'] ?? 'N/A',
                    $payment['reference'] ?? 'N/A',
                    $payment['amount'] ?? '0',
                    $allocation['allocation_date']
                ]);
                $expected_hash = hash('sha256', $verification_string);

                // Check if the hash prefix matches
                if ($hash_prefix && strpos($expected_hash, $hash_prefix) === 0) {
                    $valid = true;
                    $record = [
                        'student' => $student,
                        'allocation' => $allocation,
                        'payment' => $payment,
                        'hash' => $expected_hash
                    ];
                } else {
                    $error = 'Hash mismatch. This clearance slip may be forged or altered.';
                }
            } else {
                $error = 'No active allocation or payment record found.';
            }
        } else {
            $error = 'Student record not found.';
        }
    } else {
        $error = 'Invalid clearance reference format.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clearance Verification</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background: #f4f6f9; padding: 40px 20px; font-family: 'Inter', sans-serif; }
        .verify-card { max-width: 600px; margin: 0 auto; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .verify-header { padding: 30px; border-radius: 20px 20px 0 0; color: white; text-align: center; }
        .verify-header.success { background: linear-gradient(135deg, #198754, #20c997); }
        .verify-header.danger { background: linear-gradient(135deg, #dc3545, #e74c3c); }
        .verify-header i { font-size: 4rem; margin-bottom: 10px; }
        .verify-body { padding: 30px; background: white; border-radius: 0 0 20px 20px; }
        .data-row { display: flex; padding: 10px 0; border-bottom: 1px solid #f0f2f5; }
        .data-row .label { font-weight: 600; color: #6c757d; min-width: 140px; }
        .data-row .value { font-weight: 700; color: #1a2a4a; }
    </style>
</head>
<body>

<div class="verify-card">
    <?php if ($valid && $record): ?>
        <div class="verify-header success">
            <i class="fas fa-check-circle"></i>
            <h2 class="mb-1">VERIFIED</h2>
            <p class="mb-0">This accommodation clearance slip is authentic.</p>
        </div>
        <div class="verify-body">
            <h5 class="mb-3"><i class="fas fa-user-graduate"></i> Student</h5>
            <div class="data-row"><span class="label">Full Name:</span><span class="value"><?= htmlspecialchars($record['student']['full_name']) ?></span></div>
            <div class="data-row"><span class="label">Matric No:</span><span class="value"><?= htmlspecialchars($record['student']['matric_no']) ?></span></div>

            <h5 class="mt-4 mb-3"><i class="fas fa-bed"></i> Allocation</h5>
            <div class="data-row"><span class="label">Hall:</span><span class="value"><?= htmlspecialchars($record['allocation']['hostel_name'] ?? 'N/A') ?></span></div>
            <div class="data-row"><span class="label">Room:</span><span class="value"><?= htmlspecialchars($record['allocation']['room_number']) ?></span></div>
            <div class="data-row"><span class="label">Block:</span><span class="value"><?= htmlspecialchars($record['allocation']['block'] ?? 'N/A') ?></span></div>

            <h5 class="mt-4 mb-3"><i class="fas fa-money-bill"></i> Payment</h5>
            <div class="data-row"><span class="label">Reference:</span><span class="value"><?= htmlspecialchars($record['payment']['reference']) ?></span></div>
            <div class="data-row"><span class="label">Amount:</span><span class="value">₦<?= number_format((float)$record['payment']['amount'], 2) ?></span></div>

            <div class="alert alert-success mt-4 mb-0">
                <i class="fas fa-shield-alt"></i> <strong>Hash verified:</strong> <code><?= substr($record['hash'], 0, 24) ?>...</code>
            </div>
        </div>
    <?php else: ?>
        <div class="verify-header danger">
            <i class="fas fa-times-circle"></i>
            <h2 class="mb-1">NOT VERIFIED</h2>
            <p class="mb-0"><?= htmlspecialchars($error ?: 'Invalid clearance slip.') ?></p>
        </div>
        <div class="verify-body">
            <p class="text-muted mb-0">This document could not be verified. Please report this to the Student Affairs Unit immediately.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>