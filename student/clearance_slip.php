<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}
include '../config/db.php';

$student_id = $_SESSION['student_id'];

// Fetch student
$student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();
if (!$student) {
    header("Location: login.php");
    exit;
}

// Fetch active allocation with room & hostel info
$allocation = $conn->query("
    SELECT a.*, 
           r.room_number, r.block, r.price,
           h.name AS hostel_name,
           h.location AS hostel_location
    FROM allocations a
    JOIN rooms r ON a.room_id = r.id
    LEFT JOIN hostels h ON r.hostel_id = h.id
    WHERE a.student_id = $student_id AND a.status = 'active'
    ORDER BY a.allocation_date DESC
    LIMIT 1
")->fetch_assoc();

// Fetch latest successful payment
$payment = $conn->query("
    SELECT * FROM payments 
    WHERE student_id = $student_id AND status = 'completed'
    ORDER BY created_at DESC
    LIMIT 1
")->fetch_assoc();

// If no allocation or no payment, redirect
if (!$allocation || !$payment) {
    header("Location: dashboard.php");
    exit;
}

// ====== GENERATE CLEARANCE REFERENCE ======
$clearance_ref = 'TPI-HST-' . date('Y') . '-' . str_pad($student_id, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5($allocation['id'] . $student_id), 0, 4));

// ====== GENERATE SHA-256 HASH FOR VERIFICATION ======
$verification_string = implode('|', [
    $clearance_ref,
    $student['matric_no'],
    $student['full_name'],
    $allocation['hostel_name'] ?? 'N/A',
    $allocation['room_number'],
    $allocation['block'] ?? 'N/A',
    $payment['reference'] ?? 'N/A',
    $payment['amount'] ?? '0',
    $allocation['allocation_date']
]);
$verification_hash = hash('sha256', $verification_string);

// ====== GENERATE BED SPACE (simple assignment based on student id) ======
$bed_number = str_pad(($student_id % 4) + 1, 2, '0', STR_PAD_LEFT);

// ====== FORMAT LEVEL ======
$level_display = 'N/A';
$level_map = ['100' => 'ND I', '200' => 'ND II', '300' => 'HND I', '400' => 'HND II'];
if (isset($student['level']) && isset($level_map[$student['level']])) {
    $level_display = $level_map[$student['level']];
}

// ====== FORMAT DATES ======
$clearance_date = date('d-M-Y');
$payment_date = $payment['created_at'] ? date('d-M-Y', strtotime($payment['created_at'])) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accommodation Clearance Slip - <?= htmlspecialchars($student['full_name']) ?></title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- QR Code Generator -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Courier New', 'Consolas', monospace;
            background: #e9ecef;
            padding: 20px;
            color: #000;
        }

        .slip-wrapper {
            max-width: 900px;
            margin: 0 auto;
        }

        .slip {
            background: white;
            padding: 30px 35px;
            border: 3px double #1a3a6b;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
        }

        /* Watermark */
        .slip::before {
            content: 'THE POLYTECHNIC IBADAN';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 5rem;
            font-weight: 900;
            color: rgba(26, 58, 107, 0.05);
            white-space: nowrap;
            pointer-events: none;
            letter-spacing: 8px;
            z-index: 0;
        }

        .slip > * { position: relative; z-index: 1; }

        /* Header */
        .slip-header {
            text-align: center;
            border-bottom: 3px double #1a3a6b;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .school-logo {
            width: 70px;
            height: 70px;
            object-fit: contain;
            margin-bottom: 5px;
        }

        .school-name {
            font-size: 1.3rem;
            font-weight: 800;
            color: #1a3a6b;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            line-height: 1.2;
        }

        .school-dept {
            font-size: 0.85rem;
            color: #444;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 3px;
        }

        .slip-title {
            font-size: 1rem;
            font-weight: 700;
            margin-top: 10px;
            padding: 6px 20px;
            background: #1a3a6b;
            color: white;
            display: inline-block;
            letter-spacing: 3px;
            border-radius: 4px;
        }

        .ref-line {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            font-size: 0.85rem;
            font-weight: 700;
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px dashed #999;
        }

        /* Section headers */
        .section-title {
            background: #f0f4fa;
            color: #1a3a6b;
            font-weight: 800;
            font-size: 0.8rem;
            padding: 6px 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin: 18px 0 10px 0;
            border-left: 4px solid #1a3a6b;
        }

        /* Data rows */
        .data-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 30px;
            font-size: 0.88rem;
        }

        .data-grid.single { grid-template-columns: 1fr; }

        .data-row {
            display: flex;
            align-items: baseline;
            gap: 8px;
            padding: 4px 0;
            border-bottom: 1px dotted #ccc;
        }

        .data-row .label {
            font-weight: 700;
            min-width: 110px;
            color: #333;
        }

        .data-row .value {
            font-weight: 600;
            color: #000;
            flex: 1;
            word-break: break-word;
        }

        .data-row .value.highlight {
            color: #1a3a6b;
            font-size: 0.95rem;
        }

        .data-row .value.success {
            color: #198754;
        }

        /* Verification section */
        .verification-section {
            display: flex;
            gap: 25px;
            align-items: center;
            margin-top: 20px;
            padding: 15px;
            border: 2px dashed #1a3a6b;
            border-radius: 8px;
            background: #f8fafd;
            flex-wrap: wrap;
        }

        .qr-box {
            text-align: center;
            flex-shrink: 0;
        }

        .qr-box #qrcode {
            width: 130px;
            height: 130px;
            padding: 8px;
            background: white;
            border: 2px solid #1a3a6b;
            border-radius: 6px;
            display: inline-block;
        }

        .qr-box .qr-caption {
            font-size: 0.7rem;
            font-weight: 700;
            color: #1a3a6b;
            margin-top: 6px;
            letter-spacing: 1px;
        }

        .hash-box {
            flex: 1;
            min-width: 250px;
        }

        .hash-box .hash-label {
            font-size: 0.72rem;
            font-weight: 800;
            color: #1a3a6b;
            letter-spacing: 1.5px;
            margin-bottom: 4px;
        }

        .hash-box .hash-value {
            font-family: 'Courier New', monospace;
            font-size: 0.7rem;
            background: white;
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            word-break: break-all;
            color: #333;
            line-height: 1.4;
        }

        .hash-box .verify-note {
            font-size: 0.72rem;
            color: #666;
            margin-top: 8px;
            line-height: 1.4;
        }

        /* Signature section */
        .signature-section {
            display: flex;
            justify-content: space-between;
            gap: 40px;
            margin-top: 45px;
            padding-top: 10px;
            flex-wrap: wrap;
        }

        .signature-block {
            flex: 1;
            min-width: 200px;
            text-align: center;
        }

        .signature-block .sig-line {
            border-top: 1.5px solid #333;
            margin-bottom: 6px;
            height: 30px;
        }

        .signature-block .sig-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Footer */
        .slip-footer {
            text-align: center;
            font-size: 0.7rem;
            color: #888;
            margin-top: 25px;
            padding-top: 12px;
            border-top: 1px dashed #ccc;
            line-height: 1.6;
        }

        .slip-footer strong { color: #1a3a6b; }

        /* Action buttons (screen only) */
        .action-bar {
            max-width: 900px;
            margin: 20px auto 0;
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .action-bar .btn {
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        /* Status badge */
        .badge-verified {
            display: inline-block;
            padding: 3px 10px;
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #198754;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 1px;
        }

        /* ========== PRINT STYLES ========== */
        @media print {
            body { background: white; padding: 0; }

            .slip {
                box-shadow: none;
                border: 2px solid #000;
                padding: 20px 25px;
                page-break-inside: avoid;
            }

            .slip::before {
                color: rgba(0, 0, 0, 0.06);
            }

            .action-bar { display: none !important; }

            .slip-header { padding-bottom: 10px; }

            .school-name { font-size: 1.1rem; }

            .slip-title {
                background: #1a3a6b !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .section-title {
                background: #f0f4fa !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            @page { size: A4 portrait; margin: 10mm; }
        }

        /* Mobile */
        @media (max-width: 576px) {
            body { padding: 10px; }
            .slip { padding: 18px 15px; }
            .data-grid { grid-template-columns: 1fr; gap: 4px; }
            .school-name { font-size: 1rem; }
            .slip-title { font-size: 0.75rem; letter-spacing: 1.5px; padding: 5px 12px; }
            .ref-line { flex-direction: column; gap: 5px; }
            .verification-section { flex-direction: column; }
            .signature-section { flex-direction: column; gap: 30px; }
            .slip::before { font-size: 2.5rem; }
        }
    </style>
</head>
<body>

<div class="slip-wrapper">
    <div class="slip">

        <!-- ============ HEADER ============ -->
        <div class="slip-header">
            <img src="../assets/images/logo.png" alt="Logo" class="school-logo" onerror="this.style.display='none'">
            <div class="school-name">The Polytechnic, Ibadan</div>
            <div class="school-dept">Student Affairs Unit • Hostel Management</div>
            <div class="slip-title">Official Accommodation Clearance Slip</div>

            <div class="ref-line">
                <span>CLEARANCE REF: <strong><?= htmlspecialchars($clearance_ref) ?></strong></span>
                <span>DATE ISSUED: <strong><?= $clearance_date ?></strong></span>
            </div>
        </div>

        <!-- ============ STUDENT INFORMATION ============ -->
        <div class="section-title">Student Information</div>
        <div class="data-grid">
            <div class="data-row">
                <span class="label">Full Name:</span>
                <span class="value highlight"><?= strtoupper(htmlspecialchars($student['full_name'])) ?></span>
            </div>
            <div class="data-row">
                <span class="label">Matric No:</span>
                <span class="value highlight"><?= htmlspecialchars($student['matric_no']) ?></span>
            </div>
            <div class="data-row">
                <span class="label">Gender:</span>
                <span class="value"><?= htmlspecialchars($student['gender'] ?? 'N/A') ?></span>
            </div>
            <div class="data-row">
                <span class="label">Level:</span>
                <span class="value"><?= $level_display ?></span>
            </div>
            <div class="data-row">
                <span class="label">Phone:</span>
                <span class="value"><?= htmlspecialchars($student['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="data-row">
                <span class="label">Email:</span>
                <span class="value"><?= htmlspecialchars($student['email'] ?? 'N/A') ?></span>
            </div>
        </div>

        <!-- ============ ALLOCATION DETAILS ============ -->
        <div class="section-title">Allocation Details</div>
        <div class="data-grid">
            <div class="data-row">
                <span class="label">Hall Assigned:</span>
                <span class="value highlight"><?= strtoupper(htmlspecialchars($allocation['hostel_name'] ?? 'N/A')) ?></span>
            </div>
            <div class="data-row">
                <span class="label">Block:</span>
                <span class="value"><?= htmlspecialchars($allocation['block'] ?? 'N/A') ?></span>
            </div>
            <div class="data-row">
                <span class="label">Room Number:</span>
                <span class="value highlight"><?= htmlspecialchars($allocation['room_number']) ?></span>
            </div>
            <div class="data-row">
                <span class="label">Bed Space:</span>
                <span class="value">Bed <?= $bed_number ?></span>
            </div>
            <div class="data-row">
                <span class="label">Allocated On:</span>
                <span class="value"><?= date('d-M-Y', strtotime($allocation['allocation_date'])) ?></span>
            </div>
            <div class="data-row">
                <span class="label">Semester:</span>
                <span class="value"><?= ($allocation['semester'] ?? '1') == '1' ? 'First' : 'Second' ?> Semester</span>
            </div>
        </div>

        <!-- ============ PAYMENT RECONCILIATION ============ -->
        <div class="section-title">Payment Reconciliation</div>
        <div class="data-grid">
            <div class="data-row">
                <span class="label">Payment Ref:</span>
                <span class="value"><?= htmlspecialchars($payment['reference'] ?? 'N/A') ?></span>
            </div>
            <div class="data-row">
                <span class="label">Amount Paid:</span>
                <span class="value success">₦<?= number_format((float)($payment['amount'] ?? 0), 2) ?></span>
            </div>
            <div class="data-row">
                <span class="label">Payment Date:</span>
                <span class="value"><?= $payment_date ?></span>
            </div>
            <div class="data-row">
                <span class="label">Status:</span>
                <span class="value">
                    <span class="badge-verified">
                        <i class="fas fa-check-circle"></i> VERIFIED
                    </span>
                </span>
            </div>
        </div>

        <!-- ============ VERIFICATION & AUTHENTICATION ============ -->
        <div class="section-title">Verification &amp; Authentication</div>
        <div class="verification-section">
            <div class="qr-box">
                <div id="qrcode"></div>
                <div class="qr-caption">SCAN TO VERIFY</div>
            </div>
            <div class="hash-box">
                <div class="hash-label">SHA-256 VERIFICATION HASH</div>
                <div class="hash-value" id="hashValue"><?= $verification_hash ?></div>
                <div class="verify-note">
                    <i class="fas fa-shield-alt"></i>
                    This document is digitally signed. Scan the QR code at the Porter's Lodge for authenticity verification.
                </div>
            </div>
        </div>

        <!-- ============ SIGNATURES ============ -->
        <div class="signature-section">
            <div class="signature-block">
                <div class="sig-line"></div>
                <div class="sig-label">Student Signature &amp; Date</div>
            </div>
            <div class="signature-block">
                <div class="sig-line"></div>
                <div class="sig-label">Hall Warden Stamp &amp; Signature</div>
            </div>
        </div>

        <!-- ============ FOOTER ============ -->
        <div class="slip-footer">
            <strong>The Polytechnic, Ibadan</strong> • Student Affairs Unit<br>
            This slip must be presented at the Hall of Residence. Non-transferable.<br>
            Generated on <?= date('d-M-Y H:i') ?> • Document ID: <?= substr($verification_hash, 0, 16) ?>
        </div>

    </div>
</div>

<!-- ============ ACTION BUTTONS ============ -->
<div class="action-bar">
    <button onclick="window.print()" class="btn btn-primary">
        <i class="fas fa-print"></i> Print Clearance Slip
    </button>
    <a href="dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
    <a href="my-room.php" class="btn btn-outline-primary">
        <i class="fas fa-bed"></i> View My Room
    </a>
</div>

<!-- ============ SCRIPTS ============ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ====== QR CODE GENERATION ======
    // The QR contains a verification URL that the porter can scan
    const verifyData = {
        ref: '<?= addslashes($clearance_ref) ?>',
        matric: '<?= addslashes($student['matric_no']) ?>',
        name: '<?= addslashes($student['full_name']) ?>',
        room: '<?= addslashes($allocation['room_number']) ?>',
        hostel: '<?= addslashes($allocation['hostel_name'] ?? 'N/A') ?>',
        hash: '<?= $verification_hash ?>'
    };

    // Build a verification URL (adjust to your domain)
    const baseUrl = window.location.origin + window.location.pathname.replace('clearance_slip.php', '');
    const verifyUrl = baseUrl + 'verify_clearance.php?ref=' + encodeURIComponent(verifyData.ref) 
                    + '&hash=' + encodeURIComponent(verifyData.hash.substring(0, 16));

    // Generate QR code
    new QRCode(document.getElementById("qrcode"), {
        text: verifyUrl,
        width: 130,
        height: 130,
        colorDark: "#1a3a6b",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });

    // ====== COPY HASH TO CLIPBOARD ON CLICK ======
    const hashEl = document.getElementById('hashValue');
    if (hashEl) {
        hashEl.style.cursor = 'pointer';
        hashEl.title = 'Click to copy';
        hashEl.addEventListener('click', function() {
            navigator.clipboard.writeText(this.textContent).then(() => {
                const original = this.style.background;
                this.style.background = '#d1e7dd';
                setTimeout(() => { this.style.background = original; }, 800);
            });
        });
    }

    console.log('✅ Clearance Slip loaded');
});
</script>

</body>
</html>