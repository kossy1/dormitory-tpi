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

// Check if student has any allocation or pending payment — if not, redirect to browse
$has_allocation = $conn->query("SELECT id FROM allocations WHERE student_id = $student_id AND status='active' LIMIT 1")->num_rows > 0;
$has_pending = $conn->query("SELECT id FROM payments WHERE student_id = $student_id AND status='pending' LIMIT 1")->num_rows > 0;

if (!$has_allocation && !$has_pending) {
    $_SESSION['flash_success'] = 'Please browse and select a room first to make payment.';
    header("Location: browse-rooms.php");
    exit;
}

// Fetch all payments
$payments = $conn->query("
    SELECT * FROM payments WHERE student_id = $student_id
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Total paid
$total_paid = $conn->query("
    SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_id = $student_id AND status='completed'
")->fetch_row()[0];

// Current allocation
$allocation = $conn->query("
    SELECT a.*, r.room_number, r.block, h.name AS hostel_name
    FROM allocations a
    JOIN rooms r ON a.room_id = r.id
    LEFT JOIN hostels h ON r.hostel_id = h.id
    WHERE a.student_id = $student_id AND a.status='active'
    LIMIT 1
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; overflow-x: hidden; }

        .sidebar-toggle-btn { position: fixed; top: 12px; left: 12px; z-index: 1050; background: #1a3a6b; color: white; border: none; border-radius: 10px; padding: 12px 16px; display: none; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1039; }
        .sidebar-overlay.active { display: block; }
        .main-sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 280px; background: linear-gradient(180deg, #1a3a6b 0%, #0d1b3e 100%); z-index: 1040; overflow-y: auto; transition: transform 0.3s ease; transform: translateX(-100%); }
        .main-sidebar.open { transform: translateX(0); }
        .sidebar-brand { padding: 20px 18px 15px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 12px; }
        .sidebar-brand img { width: 45px; height: 45px; background: white; padding: 5px; border-radius: 10px; }
        .sidebar-brand .brand-text { color: white; font-weight: 700; font-size: 1.1rem; }
        .sidebar-brand .brand-text small { display: block; font-size: 0.7rem; opacity: 0.7; font-weight: 300; }
        .sidebar-close-btn { display: none; position: absolute; top: 12px; right: 12px; background: rgba(255,255,255,0.1); color: white; border: none; border-radius: 50%; width: 36px; height: 36px; }
        .user-panel { padding: 18px 20px !important; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex !important; align-items: center; gap: 12px; }
        .user-panel img { width: 42px; height: 42px; border-radius: 50%; }
        .user-panel a { color: white; font-weight: 600; text-decoration: none; font-size: 0.95rem; }
        .user-panel small { color: rgba(255,255,255,0.6); display: block; font-size: 0.75rem; }
        .nav-sidebar { padding: 8px 0; list-style: none; }
        .nav-sidebar .nav-item { margin: 2px 8px; }
        .nav-sidebar .nav-link { color: rgba(255,255,255,0.75); padding: 10px 16px; border-radius: 10px; display: flex; align-items: center; gap: 12px; text-decoration: none; transition: all 0.3s ease; }
        .nav-sidebar .nav-link:hover { color: white; background: rgba(255,255,255,0.1); }
        .nav-sidebar .nav-link.active { color: white; background: rgba(255,215,0,0.15); border-left: 3px solid #ffd700; }
        .nav-sidebar .nav-icon { width: 22px; text-align: center; color: rgba(255,255,255,0.6); }
        .nav-sidebar .nav-link.active .nav-icon { color: #ffd700; }
        .nav-sidebar .nav-link.text-danger { color: #ff6b6b !important; }

        .content-wrapper { min-height: 100vh; padding: 20px; transition: margin-left 0.3s ease; }
        .content-header { padding-bottom: 15px; border-bottom: 2px solid #e9ecef; margin-bottom: 20px; }
        .content-header h1 { font-weight: 700; font-size: 1.8rem; color: #1a2a4a; }

        .stat-card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); padding: 22px; height: 100%; animation: fadeInUp 0.6s ease; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .stat-card .stat-icon { width: 55px; height: 55px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; margin-bottom: 12px; }
        .stat-card .stat-value { font-size: 1.6rem; font-weight: 800; margin: 0; color: #1a2a4a; }
        .stat-card .stat-label { color: #6c757d; font-size: 0.85rem; margin: 5px 0 0; }
        .stat-card.bg-primary .stat-icon { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); }
        .stat-card.bg-success .stat-icon { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-card.bg-warning .stat-icon { background: linear-gradient(135deg, #ffc107, #fd7e14); }
        .stat-card.bg-info .stat-icon { background: linear-gradient(135deg, #0d6efd, #0a58ca); }

        .card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; animation: fadeInUp 0.6s ease; }
        .card-header { background: white; border-bottom: 2px solid #f0f2f5; padding: 18px 25px; border-radius: 16px 16px 0 0 !important; }
        .card-header .card-title { font-weight: 700; font-size: 1.1rem; color: #1a2a4a; margin: 0; }
        .card-body { padding: 25px; }

        .table { margin-bottom: 0; }
        .table thead th { background: #f8f9fa; color: #1a2a4a; font-weight: 600; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e9ecef; padding: 12px 15px; }
        .table tbody td { padding: 12px 15px; border-bottom: 1px solid #f0f2f5; font-size: 0.9rem; vertical-align: middle; }
        .table tbody tr:hover { background: #f8f9fa; }

        .badge { padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 0.75rem; }
        .badge.bg-success { background: #28a745 !important; }
        .badge.bg-warning { background: #ffc107 !important; color: #333; }
        .badge.bg-danger { background: #dc3545 !important; }

        .btn-success { background: linear-gradient(135deg, #28a745, #20c997); border: none; border-radius: 10px; padding: 12px 28px; font-weight: 700; color: white; }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(40,167,69,0.4); color: white; }
        .btn-primary { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); border: none; border-radius: 10px; padding: 12px 28px; font-weight: 600; color: white; }

        .alert { border-radius: 12px; border: none; padding: 16px 22px; }
        .alert-info { background: #e7f3ff; color: #084298; border-left: 5px solid #0d6efd; }

        .main-footer { background: white; padding: 15px 20px; border-top: 1px solid #e9ecef; text-align: center; font-size: 0.9rem; color: #6c757d; margin-left: 280px; }

        @media (min-width: 992px) {
            .main-sidebar { transform: translateX(0) !important; }
            .sidebar-toggle-btn, .sidebar-overlay { display: none !important; }
            .content-wrapper { margin-left: 280px; }
        }
        @media (max-width: 991.98px) {
            .sidebar-toggle-btn { display: block; }
            .main-sidebar.open { transform: translateX(0); }
            .content-wrapper { margin-left: 0; padding-top: 70px; }
            .sidebar-close-btn { display: block; }
            .main-footer { margin-left: 0; }
        }
        @media (max-width: 576px) {
            .content-header h1 { font-size: 1.4rem; }
        }
        body.sidebar-open { overflow: hidden; }
    </style>
</head>
<body>

<button class="sidebar-toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="main-sidebar" id="mainSidebar">
    <button class="sidebar-close-btn" id="sidebarClose"><i class="fas fa-times"></i></button>
    <div class="sidebar-brand">
        <img src="../assets/images/logo.png" onerror="this.src='https://via.placeholder.com/45x45/ffffff/1a3a6b?text=PI'">
        <div class="brand-text">Dormitory MS<small>The Polytechnic Ibadan</small></div>
    </div>
    <div class="user-panel">
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['full_name'] ?? 'Student') ?>&background=ffd700&color=1a3a6b&size=42">
        <div>
            <a href="profile.php"><?= htmlspecialchars($student['full_name'] ?? 'Student') ?></a>
            <small><?= htmlspecialchars($student['matric_no'] ?? '') ?></small>
        </div>
    </div>
    <ul class="nav nav-pills nav-sidebar flex-column">
        <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
        <li class="nav-item"><a href="browse-rooms.php" class="nav-link"><i class="nav-icon fas fa-search"></i><p>Browse Rooms</p></a></li>
        <li class="nav-item"><a href="my-room.php" class="nav-link"><i class="nav-icon fas fa-bed"></i><p>My Room</p></a></li>
        <li class="nav-item"><a href="profile.php" class="nav-link"><i class="nav-icon fas fa-user"></i><p>My Profile</p></a></li>
        <li class="nav-item"><a href="payment.php" class="nav-link active"><i class="nav-icon fas fa-credit-card"></i><p>Payment History</p></a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="nav-icon fas fa-sign-out-alt"></i><p>Logout</p></a></li>
    </ul>
</aside>

<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fas fa-history me-2"></i>Payment History</h1>
    </section>

    <!-- STATS -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6 col-6">
            <div class="stat-card bg-success">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <h3 class="stat-value">₦<?= number_format((float)$total_paid, 0) ?></h3>
                <p class="stat-label">Total Paid</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-6">
            <div class="stat-card bg-primary">
                <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                <h3 class="stat-value"><?= count($payments) ?></h3>
                <p class="stat-label">Transactions</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-6">
            <div class="stat-card bg-info">
                <div class="stat-icon"><i class="fas fa-bed"></i></div>
                <h3 class="stat-value" style="font-size:1.2rem;"><?= $allocation ? htmlspecialchars($allocation['room_number']) : '—' ?></h3>
                <p class="stat-label">My Room</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-6">
            <div class="stat-card bg-warning">
                <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
                <h3 class="stat-value" style="font-size:1.2rem;">
                    <?= $has_pending ? 'Pending' : 'Cleared' ?>
                </h3>
                <p class="stat-label">Payment Status</p>
            </div>
        </div>
    </div>

    <?php if ($allocation): ?>
        <div class="alert alert-info d-flex align-items-center" style="border-radius:12px;">
            <i class="fas fa-info-circle me-3" style="font-size:1.5rem;"></i>
            <div>
                <strong>You are allocated to Room <?= htmlspecialchars($allocation['room_number']) ?></strong> 
                at <?= htmlspecialchars($allocation['hostel_name'] ?? 'N/A') ?>.
                Need a different room? <a href="room-change.php" class="fw-bold">Request a room change</a>.
            </div>
        </div>
    <?php endif; ?>

    <!-- PAYMENTS TABLE -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title"><i class="fas fa-list me-2"></i>All Transactions (<?= count($payments) ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($payments)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-receipt fa-4x text-muted mb-3"></i>
                    <h5>No Payments Yet</h5>
                    <p class="text-muted">Browse rooms and make your first payment to get allocated.</p>
                    <a href="browse-rooms.php" class="btn btn-success mt-2">
                        <i class="fas fa-search me-2"></i>Browse Rooms
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $i => $p): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= date('d M Y, H:i', strtotime($p['created_at'])) ?></td>
                                    <td><code style="font-size:0.8rem;"><?= htmlspecialchars($p['reference']) ?></code></td>
                                    <td><strong>₦<?= number_format((float)$p['amount'], 2) ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?= $p['status']==='completed'?'success':($p['status']==='pending'?'warning':'danger') ?>">
                                            <i class="fas fa-<?= $p['status']==='completed'?'check-circle':($p['status']==='pending'?'clock':'times-circle') ?> me-1"></i>
                                            <?= ucfirst($p['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<footer class="main-footer">
    <strong>Dormitory Management System</strong> &copy; <?= date('Y') ?> | The Polytechnic Ibadan
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('mainSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('active'); document.body.style.overflow = ''; }
    if (toggleBtn) toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('open'); overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
    });
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);
});
</script>
</body>
</html>