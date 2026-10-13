<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';

// ========== AUTO-CREATE ACTIVITY LOG TABLE ==========
// NOTE: Remove this block after running once on your live server.
// Instead, run the SQL in phpMyAdmin (see instructions after this file).
$conn->query("
    CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT DEFAULT NULL,
        admin_name VARCHAR(100) DEFAULT 'System',
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50) DEFAULT NULL,
        entity_id INT DEFAULT NULL,
        description TEXT,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at),
        INDEX idx_action (action),
        INDEX idx_admin (admin_id)
    )
");

// ========== FILTERS ==========
$filter_action = $_GET['action'] ?? '';
$filter_admin = $_GET['admin'] ?? '';
$filter_date = $_GET['date'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// ========== BUILD QUERY ==========
$where = [];
$params = [];
$types = '';

if ($filter_action) {
    $where[] = "action = ?";
    $params[] = $filter_action;
    $types .= 's';
}
if ($filter_admin) {
    $where[] = "admin_name = ?";
    $params[] = $filter_admin;
    $types .= 's';
}
if ($filter_date) {
    $where[] = "DATE(created_at) = ?";
    $params[] = $filter_date;
    $types .= 's';
}
if ($search) {
    $where[] = "(description LIKE ? OR entity_type LIKE ? OR action LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term;
    $types .= 'sss';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM activity_logs $where_sql";
$stmt = $conn->prepare($count_sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total_logs = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_logs / $per_page));

// Fetch logs
$sql = "SELECT * FROM activity_logs $where_sql ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ========== DISTINCT VALUES FOR FILTERS ==========
$actions = [];
$res = $conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
if ($res) while ($r = $res->fetch_assoc()) $actions[] = $r['action'];

$admins = [];
$res = $conn->query("SELECT DISTINCT admin_name FROM activity_logs ORDER BY admin_name");
if ($res) while ($r = $res->fetch_assoc()) $admins[] = $r['admin_name'];

// ========== STATS ==========
$total_today = $conn->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetch_row()[0] ?? 0;
$total_week = $conn->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_row()[0] ?? 0;
$total_month = $conn->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_row()[0] ?? 0;

// ========== HELPER: ICON FOR ACTION ==========
function getActionIcon($action) {
    $action_lower = strtolower($action);
    $icons = [
        'login' => ['fas fa-sign-in-alt', '#28a745'],
        'logout' => ['fas fa-sign-out-alt', '#6c757d'],
        'create' => ['fas fa-plus-circle', '#28a745'],
        'add' => ['fas fa-plus-circle', '#28a745'],
        'update' => ['fas fa-edit', '#ffc107'],
        'edit' => ['fas fa-edit', '#ffc107'],
        'delete' => ['fas fa-trash', '#dc3545'],
        'remove' => ['fas fa-trash', '#dc3545'],
        'suspend' => ['fas fa-ban', '#dc3545'],
        'reactivate' => ['fas fa-check-circle', '#28a745'],
        'approve' => ['fas fa-check', '#28a745'],
        'reject' => ['fas fa-times', '#dc3545'],
        'payment' => ['fas fa-credit-card', '#0d6efd'],
        'allocate' => ['fas fa-door-open', '#17a2b8'],
        'deallocate' => ['fas fa-door-closed', '#fd7e14'],
    ];
    foreach ($icons as $key => $val) {
        if (strpos($action_lower, $key) !== false) return $val;
    }
    return ['fas fa-circle', '#6c757d'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activity Logs - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; overflow-x: hidden; }

        .sidebar-toggle-btn {
            position: fixed; top: 12px; left: 12px; z-index: 1050;
            background: #1a3a6b; color: white; border: none;
            border-radius: 10px; padding: 12px 16px; display: none;
        }
        .sidebar-toggle-btn:hover { background: #0d1b3e; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1039; }
        .sidebar-overlay.active { display: block; }

        .main-sidebar {
            position: fixed; top: 0; left: 0; height: 100vh; width: 280px;
            background: linear-gradient(180deg, #1a3a6b 0%, #0d1b3e 100%);
            z-index: 1040; overflow-y: auto; transition: transform 0.3s ease;
            transform: translateX(-100%);
        }
        .main-sidebar.open { transform: translateX(0); }
        .sidebar-brand {
            padding: 20px 18px 15px; border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex; align-items: center; gap: 12px;
        }
        .sidebar-brand img { width: 45px; height: 45px; background: white; padding: 5px; border-radius: 10px; }
        .sidebar-brand .brand-text { color: white; font-weight: 700; font-size: 1.1rem; line-height: 1.2; }
        .sidebar-brand .brand-text small { display: block; font-size: 0.7rem; opacity: 0.7; font-weight: 300; }
        .sidebar-close-btn {
            display: none; position: absolute; top: 12px; right: 12px;
            background: rgba(255,255,255,0.1); color: white; border: none;
            border-radius: 50%; width: 36px; height: 36px;
        }
        .user-panel {
            padding: 18px 20px !important; border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex !important; align-items: center; gap: 12px;
        }
        .user-panel img { width: 42px; height: 42px; border-radius: 50%; }
        .user-panel a { color: white; font-weight: 600; text-decoration: none; }
        .user-panel small { color: rgba(255,255,255,0.6); display: block; font-size: 0.75rem; }

        .nav-sidebar { padding: 8px 0; list-style: none; }
        .nav-sidebar .nav-item { margin: 2px 8px; }
        .nav-sidebar .nav-link {
            color: rgba(255,255,255,0.75); padding: 10px 16px; border-radius: 10px;
            display: flex; align-items: center; gap: 12px; text-decoration: none;
            transition: all 0.3s ease;
        }
        .nav-sidebar .nav-link:hover { color: white; background: rgba(255,255,255,0.1); }
        .nav-sidebar .nav-link.active { color: white; background: rgba(255,215,0,0.15); border-left: 3px solid #ffd700; }
        .nav-sidebar .nav-icon { width: 22px; text-align: center; color: rgba(255,255,255,0.6); }
        .nav-sidebar .nav-link.active .nav-icon { color: #ffd700; }
        .nav-treeview { padding-left: 15px; max-height: 0; overflow: hidden; transition: max-height 0.3s ease; }
        .nav-treeview.show { max-height: 500px; }
        .nav-treeview .nav-link { padding: 8px 16px 8px 35px; font-size: 0.85rem; color: rgba(255,255,255,0.6); }

        .content-wrapper { min-height: 100vh; padding: 20px; transition: margin-left 0.3s ease; }

        .content-header { padding-bottom: 15px; border-bottom: 2px solid #e9ecef; margin-bottom: 20px; }
        .content-header h1 { font-weight: 700; font-size: 1.8rem; color: #1a2a4a; }

        .card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .card-header { background: white; border-bottom: 2px solid #f0f2f5; padding: 18px 25px; border-radius: 16px 16px 0 0 !important; }
        .card-body { padding: 25px; }
        .card-title { font-weight: 700; font-size: 1.1rem; color: #1a2a4a; }

        .stat-card {
            border: none; border-radius: 16px; padding: 22px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            height: 100%;
            animation: fadeInUp 0.5s ease;
        }
        .stat-card .stat-icon {
            width: 55px; height: 55px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; color: white; margin-bottom: 12px;
        }
        .stat-card .stat-value { font-size: 1.9rem; font-weight: 800; margin: 0; color: #1a2a4a; }
        .stat-card .stat-label { color: #6c757d; font-size: 0.9rem; margin: 5px 0 0; }
        .stat-card.bg-primary .stat-icon { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); }
        .stat-card.bg-info .stat-icon { background: linear-gradient(135deg, #0d6efd, #0a58ca); }
        .stat-card.bg-success .stat-icon { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-card.bg-warning .stat-icon { background: linear-gradient(135deg, #ffc107, #fd7e14); }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .filter-bar {
            background: white; border-radius: 16px; padding: 18px 22px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px;
        }
        .filter-bar label { font-size: 0.82rem; font-weight: 600; color: #1a2a4a; margin-bottom: 5px; display: block; }
        .filter-bar .form-control, .filter-bar .form-select {
            border: 2px solid #e9ecef; border-radius: 10px; padding: 9px 14px; font-size: 0.9rem;
        }
        .filter-bar .form-control:focus, .filter-bar .form-select:focus {
            border-color: #1a3a6b; box-shadow: 0 0 0 4px rgba(26,58,107,0.12);
        }

        .btn-primary {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            border: none; border-radius: 10px; padding: 10px 22px;
            font-weight: 600; color: white;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(26,58,107,0.4); color: white; }
        .btn-outline-secondary { border: 2px solid #e9ecef; border-radius: 10px; padding: 10px 20px; font-weight: 600; }

        /* ===== LOG TIMELINE ===== */
        .log-list { padding: 0; }
        .log-item {
            display: flex; gap: 18px; padding: 18px 25px;
            border-bottom: 1px solid #f0f2f5;
            transition: all 0.3s ease;
            align-items: flex-start;
        }
        .log-item:hover { background: #f8f9fa; }
        .log-item:last-child { border-bottom: none; }

        .log-icon {
            width: 45px; height: 45px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; color: white; flex-shrink: 0;
        }
        .log-content { flex: 1; min-width: 0; }
        .log-action {
            font-weight: 700; font-size: 0.95rem; color: #1a2a4a;
            display: flex; align-items: center; flex-wrap: wrap; gap: 8px;
        }
        .log-description {
            color: #495057; font-size: 0.88rem; margin-top: 3px;
            word-break: break-word;
        }
        .log-meta {
            display: flex; flex-wrap: wrap; gap: 15px; margin-top: 8px;
            font-size: 0.78rem; color: #6c757d;
        }
        .log-meta span { display: inline-flex; align-items: center; gap: 5px; }
        .log-meta i { color: #adb5bd; }
        .log-badge {
            display: inline-block; padding: 2px 10px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .log-badge.create, .log-badge.add, .log-badge.approve, .log-badge.reactivate { background: #d4edda; color: #155724; }
        .log-badge.update, .log-badge.edit { background: #fff3cd; color: #856404; }
        .log-badge.delete, .log-badge.remove, .log-badge.suspend, .log-badge.reject { background: #f8d7da; color: #721c24; }
        .log-badge.login, .log-badge.logout { background: #d1ecf1; color: #0c5460; }
        .log-badge.payment { background: #cfe2ff; color: #084298; }
        .log-badge.allocate, .log-badge.deallocate { background: #e0d4f7; color: #4a1e8f; }
        .log-badge.default { background: #e2e3e5; color: #41464b; }

        .log-time {
            text-align: right; font-size: 0.78rem; color: #6c757d;
            flex-shrink: 0; min-width: 100px; white-space: nowrap;
        }
        .log-time strong { display: block; color: #1a2a4a; font-size: 0.85rem; }

        .pagination-wrapper { padding: 20px 25px; text-align: center; border-top: 1px solid #f0f2f5; }
        .pagination { margin: 0; display: inline-flex; gap: 5px; }
        .pagination .page-link {
            border: 2px solid #e9ecef; border-radius: 8px !important;
            color: #1a2a4a; font-weight: 600; padding: 6px 14px;
        }
        .pagination .page-link:hover { background: #f0f4fa; color: #1a3a6b; }
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #1a3a6b, #0d1b3e);
            border-color: #1a3a6b; color: white;
        }

        .empty-state { padding: 60px 20px; text-align: center; }
        .empty-state i { font-size: 4rem; color: #d1d5db; margin-bottom: 15px; }

        .main-footer {
            background: white; padding: 15px 20px; border-top: 1px solid #e9ecef;
            text-align: center; font-size: 0.9rem; color: #6c757d; margin-left: 280px;
        }

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
            .card-header, .card-body { padding: 15px 18px; }
            .log-item { padding: 15px 18px; flex-wrap: wrap; }
            .log-time { text-align: left; min-width: auto; margin-left: 63px; }
            .stat-card { padding: 18px; }
            .stat-card .stat-value { font-size: 1.4rem; }
            .stat-card .stat-icon { width: 45px; height: 45px; font-size: 1.3rem; }
        }
    </style>
</head>
<body>

<button class="sidebar-toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="main-sidebar" id="mainSidebar">
    <button class="sidebar-close-btn" id="sidebarClose"><i class="fas fa-times"></i></button>
    <div class="sidebar-brand">
        <img src="../assets/images/logo.png" onerror="this.src='https://via.placeholder.com/45x45/ffffff/1a3a6b?text=PI'">
        <div class="brand-text">Dormitory MS<small>Admin Panel</small></div>
    </div>
    <div class="user-panel">
        <img src="https://ui-avatars.com/api/?name=Admin&background=ffd700&color=1a3a6b&size=42">
        <div><a href="#">Administrator</a><small>Admin</small></div>
    </div>
    <ul class="nav nav-pills nav-sidebar flex-column">
        <li class="nav-item"><a href="index.php" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
        <li class="nav-item"><a href="students.php" class="nav-link"><i class="nav-icon fas fa-users"></i><p>Students</p></a></li>
        <li class="nav-item"><a href="rooms.php" class="nav-link"><i class="nav-icon fas fa-bed"></i><p>Rooms</p></a></li>
        <li class="nav-item"><a href="hostels.php" class="nav-link"><i class="nav-icon fas fa-building"></i><p>Hostels</p></a></li>
        <li class="nav-item"><a href="allocations.php" class="nav-link"><i class="nav-icon fas fa-door-open"></i><p>Allocations</p></a></li>
        <li class="nav-item"><a href="payments.php" class="nav-link"><i class="nav-icon fas fa-credit-card"></i><p>Payments</p></a></li>
        <li class="nav-item"><a href="reports.php" class="nav-link"><i class="nav-icon fas fa-chart-line"></i><p>Reports</p></a></li>
        <li class="nav-item"><a href="logs.php" class="nav-link active"><i class="nav-icon fas fa-history"></i><p>Activity Logs</p></a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="nav-icon fas fa-sign-out-alt"></i><p>Logout</p></a></li>
    </ul>
</aside>

<div class="content-wrapper">
    <section class="content-header">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <h1><i class="fas fa-history me-2"></i>Activity Logs</h1>
            <a href="logs.php" class="btn btn-outline-secondary">
                <i class="fas fa-redo me-2"></i>Refresh
            </a>
        </div>
    </section>

    <!-- STATS -->
    <div class="row g-3 mb-3">
        <div class="col-lg-3 col-md-6 col-6">
            <div class="stat-card bg-primary">
                <div class="stat-icon"><i class="fas fa-database"></i></div>
                <h3 class="stat-value"><?= number_format($total_logs) ?></h3>
                <p class="stat-label">Total Logs</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-6">
            <div class="stat-card bg-info">
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                <h3 class="stat-value"><?= number_format($total_today) ?></h3>
                <p class="stat-label">Today</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-6">
            <div class="stat-card bg-success">
                <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
                <h3 class="stat-value"><?= number_format($total_week) ?></h3>
                <p class="stat-label">Last 7 Days</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-6">
            <div class="stat-card bg-warning">
                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                <h3 class="stat-value"><?= number_format($total_month) ?></h3>
                <p class="stat-label">Last 30 Days</p>
            </div>
        </div>
    </div>

    <!-- FILTERS -->
    <form method="GET" class="filter-bar">
        <div class="row g-3">
            <div class="col-md-3">
                <label><i class="fas fa-search me-1"></i>Search</label>
                <input type="text" name="search" class="form-control" placeholder="Description, action, entity..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label><i class="fas fa-tag me-1"></i>Action</label>
                <select name="action" class="form-select">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>" <?= $filter_action === $a ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label><i class="fas fa-user-shield me-1"></i>Admin</label>
                <select name="admin" class="form-select">
                    <option value="">All Admins</option>
                    <?php foreach ($admins as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>" <?= $filter_admin === $a ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label><i class="fas fa-calendar me-1"></i>Date</label>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2 align-items-end">
                <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-filter me-2"></i>Filter</button>
                <a href="logs.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
            </div>
        </div>
    </form>

    <!-- LOGS LIST -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>Activity Timeline</h5>
            <span class="badge bg-secondary"><?= number_format($total_logs) ?> records</span>
        </div>

        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h5 class="text-muted">No Activity Logs Found</h5>
                    <p class="text-muted">
                        <?= ($filter_action || $filter_admin || $filter_date || $search) 
                            ? 'Try adjusting your filters.' 
                            : 'Activity will be recorded here as admins perform actions.' ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="log-list">
                    <?php foreach ($logs as $log): 
                        list($icon, $color) = getActionIcon($log['action']);
                        $action_key = strtolower(explode(' ', $log['action'])[0]);
                        $badge_class = in_array($action_key, ['create','add','approve','reactivate','update','edit','delete','remove','suspend','reject','login','logout','payment','allocate','deallocate']) 
                            ? $action_key 
                            : 'default';
                    ?>
                        <div class="log-item">
                            <div class="log-icon" style="background: <?= $color ?>;">
                                <i class="<?= $icon ?>"></i>
                            </div>
                            <div class="log-content">
                                <div class="log-action">
                                    <?= htmlspecialchars($log['action']) ?>
                                    <span class="log-badge <?= $badge_class ?>"><?= $badge_class === 'default' ? 'action' : $badge_class ?></span>
                                    <?php if ($log['entity_type']): ?>
                                        <span class="text-muted" style="font-weight: 500; font-size: 0.85rem;">
                                            • <?= htmlspecialchars($log['entity_type']) ?>
                                            <?php if ($log['entity_id']): ?> #<?= $log['entity_id'] ?><?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($log['description']): ?>
                                    <div class="log-description"><?= htmlspecialchars($log['description']) ?></div>
                                <?php endif; ?>
                                <div class="log-meta">
                                    <span><i class="fas fa-user-shield"></i> <?= htmlspecialchars($log['admin_name'] ?? 'System') ?></span>
                                    <?php if ($log['ip_address']): ?>
                                        <span><i class="fas fa-network-wired"></i> <?= htmlspecialchars($log['ip_address']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="log-time">
                                <strong><?= date('d M Y', strtotime($log['created_at'])) ?></strong>
                                <?= date('h:i A', strtotime($log['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- PAGINATION -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-wrapper">
                        <nav>
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php 
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                for ($i = $start; $i <= $end; $i++): 
                                ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <p class="text-muted mt-3 mb-0" style="font-size: 0.85rem;">
                            Page <?= $page ?> of <?= $total_pages ?> • Showing <?= count($logs) ?> of <?= number_format($total_logs) ?> logs
                        </p>
                    </div>
                <?php endif; ?>
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

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (toggleBtn) toggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
    });
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);
});
</script>
</body>
</html>