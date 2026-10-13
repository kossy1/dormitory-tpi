<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}
include '../config/db.php';

$student_id = $_SESSION['student_id'];
$success = $error = '';

// Current room
$current_room = $conn->query("
    SELECT r.id, r.room_number, r.block 
    FROM allocations a 
    JOIN rooms r ON a.room_id = r.id 
    WHERE a.student_id = $student_id AND a.status = 'active' 
    LIMIT 1
")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issue_type = $_POST['issue_type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $room_id = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;

    if (empty($issue_type) || empty($description)) {
        $error = "Issue type and description are required.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO maintenance_requests (student_id, room_id, issue_type, description, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("iiss", $student_id, $room_id, $issue_type, $description);
        if ($stmt->execute()) {
            $success = "Maintenance request submitted successfully!";
        } else {
            $error = "Failed to submit: " . $stmt->error;
        }
    }
}

$requests = $conn->query("
    SELECT * FROM maintenance_requests WHERE student_id = $student_id ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Dormitory MS</title>
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

        .card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; animation: fadeInUp 0.6s ease; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .card-header { background: white; border-bottom: 2px solid #f0f2f5; padding: 18px 25px; border-radius: 16px 16px 0 0 !important; }
        .card-header .card-title { font-weight: 700; font-size: 1.1rem; color: #1a2a4a; margin: 0; }
        .card-body { padding: 25px; }
        .card-warning .card-header { background: linear-gradient(135deg, #fd7e14, #ffc107); color: white; }
        .card-warning .card-header .card-title { color: white; }

        .form-label { font-weight: 600; font-size: 0.9rem; color: #1a2a4a; }
        .form-control, .form-select { border: 2px solid #e9ecef; border-radius: 10px; padding: 10px 16px; font-size: 0.95rem; }
        .form-control:focus, .form-select:focus { border-color: #fd7e14; box-shadow: 0 0 0 4px rgba(253,126,20,0.12); }

        .btn-warning { background: linear-gradient(135deg, #fd7e14, #ffc107); border: none; border-radius: 10px; padding: 12px 32px; font-weight: 700; color: white; }
        .btn-warning:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(253,126,20,0.4); color: white; }
        .btn-outline-secondary { border: 2px solid #e9ecef; border-radius: 10px; padding: 12px 30px; font-weight: 600; }

        .alert { border-radius: 12px; border: none; padding: 16px 22px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
        .alert-danger { background: #fde8e8; color: #721c24; border-left: 5px solid #dc3545; }

        .table { margin-bottom: 0; }
        .table thead th { background: #f8f9fa; color: #1a2a4a; font-weight: 600; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e9ecef; padding: 12px 15px; }
        .table tbody td { padding: 12px 15px; border-bottom: 1px solid #f0f2f5; font-size: 0.9rem; vertical-align: middle; }

        .badge { padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 0.75rem; }
        .badge.bg-warning { background: #ffc107 !important; color: #333; }
        .badge.bg-info { background: #17a2b8 !important; }
        .badge.bg-success { background: #28a745 !important; }

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
            .btn-warning, .btn-outline-secondary { width: 100%; }
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
        <li class="nav-item"><a href="payment.php" class="nav-link"><i class="nav-icon fas fa-credit-card"></i><p>Payment History</p></a></li>
        <li class="nav-item"><a href="complaints.php" class="nav-link"><i class="nav-icon fas fa-comments"></i><p>Complaints</p></a></li>
        <li class="nav-item"><a href="maintenance.php" class="nav-link active"><i class="nav-icon fas fa-wrench"></i><p>Maintenance</p></a></li>
        <li class="nav-item"><a href="refund.php" class="nav-link"><i class="nav-icon fas fa-money-bill-wave"></i><p>Refund</p></a></li>
        <li class="nav-item"><a href="room-change.php" class="nav-link"><i class="nav-icon fas fa-exchange-alt"></i><p>Room Change</p></a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="nav-icon fas fa-sign-out-alt"></i><p>Logout</p></a></li>
    </ul>
</aside>

<div class="content-wrapper">
    <section class="content-header">
        <h1><i class="fas fa-wrench me-2"></i>Maintenance Requests</h1>
    </section>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card card-warning">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-plus-circle me-2"></i>Report Maintenance Issue</h3>
        </div>
        <div class="card-body">
            <form method="POST" id="maintenanceForm">
                <div class="form-group mb-4">
                    <label class="form-label"><i class="fas fa-tag me-1"></i>Issue Type <span class="text-danger">*</span></label>
                    <select name="issue_type" class="form-select" required>
                        <option value="">-- Select Issue --</option>
                        <option value="Plumbing">🔧 Plumbing</option>
                        <option value="Electrical">⚡ Electrical</option>
                        <option value="Furniture">🪑 Furniture</option>
                        <option value="Cleaning">🧹 Cleaning</option>
                        <option value="Air Conditioning">❄️ Air Conditioning</option>
                        <option value="Internet/WiFi">📶 Internet/WiFi</option>
                        <option value="Other">📌 Other</option>
                    </select>
                </div>

                <div class="form-group mb-4">
                    <label class="form-label"><i class="fas fa-door-open me-1"></i>Room (if applicable)</label>
                    <select name="room_id" class="form-select">
                        <option value="">General Issue (Not room specific)</option>
                        <?php if ($current_room): ?>
                            <option value="<?= $current_room['id'] ?>" selected>
                                Room <?= htmlspecialchars($current_room['room_number']) ?> 
                                (<?= htmlspecialchars($current_room['block'] ?? 'Block') ?>)
                            </option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group mb-4">
                    <label class="form-label"><i class="fas fa-align-left me-1"></i>Detailed Description <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control" rows="6" required 
                              placeholder="Please describe the problem in detail. Include when it started, how often it occurs, etc."><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-warning" id="submitBtn">
                        <i class="fas fa-paper-plane me-2"></i>Submit Request
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($requests)): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-history me-2"></i>My Previous Requests (<?= count($requests) ?>)</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr><th>Date</th><th>Issue Type</th><th>Room</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <tr>
                            <td><?= date('d M Y H:i', strtotime($req['created_at'])) ?></td>
                            <td><?= htmlspecialchars($req['issue_type']) ?></td>
                            <td><?= $req['room_id'] ? 'Room Specific' : 'General' ?></td>
                            <td>
                                <span class="badge bg-<?= $req['status']==='pending'?'warning':($req['status']==='in-progress'?'info':'success') ?>">
                                    <?= ucfirst($req['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
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

    const form = document.getElementById('maintenanceForm');
    const submitBtn = document.getElementById('submitBtn');
    if (form) form.addEventListener('submit', function() {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
    });
});
</script>
</body>
</html>