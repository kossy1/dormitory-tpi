<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';
include 'log_action.php';

$success = $error = '';
$search = trim($_GET['search'] ?? '');

// ====== CHECK TABLE STRUCTURE (no DDL) ======
$table_exists = $conn->query("SHOW TABLES LIKE 'hostels'")->num_rows > 0;

$has_status = false;
$has_location = false;
$has_phone = false;
$has_email = false;

if ($table_exists) {
    $cols = $conn->query("SHOW COLUMNS FROM hostels");
    $col_names = [];
    while ($c = $cols->fetch_assoc()) {
        $col_names[] = $c['Field'];
    }
    $has_status   = in_array('status', $col_names);
    $has_location = in_array('location', $col_names);
    $has_phone    = in_array('contact_phone', $col_names);
    $has_email    = in_array('contact_email', $col_names);
}

// ====== ADD NEW HOSTEL ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_hostel'])) {
    if (!$table_exists) {
        $error = "The hostels table does not exist. Please run the database setup SQL first.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $contact_email = trim($_POST['contact_email'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (!in_array($status, ['active', 'inactive'])) {
            $status = 'active';
        }

        if (empty($name)) {
            $error = "Hostel name is required.";
        } else {
            // Duplicate check
            $check = $conn->prepare("SELECT id FROM hostels WHERE name = ?");
            $check->bind_param("s", $name);
            $check->execute();

            if ($check->get_result()->num_rows > 0) {
                $error = "A hostel with the name '$name' already exists.";
            } else {
                // Build dynamic INSERT based on existing columns
                $insert_cols = ['name', 'description'];
                $insert_vals = [$name, $description];
                $bind_types = 'ss';

                if ($has_location) { $insert_cols[] = 'location'; $insert_vals[] = $location; $bind_types .= 's'; }
                if ($has_phone)    { $insert_cols[] = 'contact_phone'; $insert_vals[] = $contact_phone; $bind_types .= 's'; }
                if ($has_email)    { $insert_cols[] = 'contact_email'; $insert_vals[] = $contact_email; $bind_types .= 's'; }
                if ($has_status)   { $insert_cols[] = 'status'; $insert_vals[] = $status; $bind_types .= 's'; }

                $placeholders = implode(', ', array_fill(0, count($insert_cols), '?'));
                $sql = "INSERT INTO hostels (" . implode(', ', $insert_cols) . ") VALUES ($placeholders)";

                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    $error = "SQL prepare failed: " . $conn->error;
                } else {
                    $stmt->bind_param($bind_types, ...$insert_vals);
                    if ($stmt->execute()) {
                        $success = "Hostel '$name' added successfully!";
                    } else {
                        $error = "Failed to add hostel: " . $stmt->error;
                    }
                }
            }
        }
    }
}

// ====== UPDATE HOSTEL ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_hostel'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (!in_array($status, ['active', 'inactive'])) $status = 'active';

    if (empty($name)) {
        $error = "Hostel name is required.";
    } else {
        $check = $conn->prepare("SELECT id FROM hostels WHERE name = ? AND id != ?");
        $check->bind_param("si", $name, $id);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "A hostel with the name '$name' already exists.";
        } else {
            $stmt = $conn->prepare("
                UPDATE hostels 
                SET name = ?, description = ?, location = ?, contact_phone = ?, contact_email = ?, status = ? 
                WHERE id = ?
            ");
            $stmt->bind_param("ssssssi", $name, $description, $location, $contact_phone, $contact_email, $status, $id);
            if ($stmt->execute()) {
                $success = "Hostel updated successfully!";
            } else {
                $error = "Failed to update hostel: " . $stmt->error;
            }
        }
    }
}

// ====== DELETE HOSTEL ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_hostel'])) {
    $id = (int)$_POST['id'];

    $has_hostel_id = $conn->query("SHOW COLUMNS FROM rooms LIKE 'hostel_id'")->num_rows > 0;
    $room_count = 0;

    if ($has_hostel_id) {
        $rc = $conn->query("SELECT COUNT(*) as c FROM rooms WHERE hostel_id = $id")->fetch_assoc();
        $room_count = (int)($rc['c'] ?? 0);
    }

    if ($room_count > 0) {
        $error = "Cannot delete hostel. It has $room_count room(s). Delete those rooms first.";
    } else {
        if ($conn->query("DELETE FROM hostels WHERE id = $id")) {
            $success = "Hostel deleted successfully.";
        } else {
            $error = "Failed to delete hostel: " . $conn->error;
        }
    }
}

// ====== FETCH HOSTELS ======
$hostels = [];
if ($table_exists) {
    $query = "SELECT * FROM hostels";
    if ($search) {
        $s = $conn->real_escape_string($search);
        $query .= " WHERE name LIKE '%$s%' 
                    OR description LIKE '%$s%' 
                    OR location LIKE '%$s%'";
    }
    $query .= " ORDER BY name ASC";
    $result = $conn->query($query);
    if ($result) $hostels = $result->fetch_all(MYSQLI_ASSOC);
}

// ====== STATISTICS ======
$total_hostels = count($hostels);
$active_count = 0;
$inactive_count = 0;
$total_rooms = 0;
$available_rooms = 0;
$occupied_rooms = 0;

$has_hostel_id = $conn->query("SHOW COLUMNS FROM rooms LIKE 'hostel_id'")->num_rows > 0;

foreach ($hostels as $hostel) {
    $status = $hostel['status'] ?? 'active';
    if ($status === 'active') $active_count++;
    if ($status === 'inactive') $inactive_count++;

    if ($has_hostel_id) {
        $hid = (int)$hostel['id'];
        $rs = $conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied
            FROM rooms WHERE hostel_id = $hid
        ")->fetch_assoc();
        if ($rs) {
            $total_rooms += (int)($rs['total'] ?? 0);
            $available_rooms += (int)($rs['available'] ?? 0);
            $occupied_rooms += (int)($rs['occupied'] ?? 0);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Hostels - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#f4f6f9; overflow-x:hidden; }
        .sidebar-toggle-btn { position:fixed; top:12px; left:12px; z-index:1050; background:#1a3a6b; color:white; border:none; border-radius:10px; padding:12px 16px; display:none; }
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1039; }
        .sidebar-overlay.active { display:block; }
        .main-sidebar { position:fixed; top:0; left:0; height:100vh; width:280px; background:linear-gradient(180deg,#1a3a6b 0%,#0d1b3e 100%); z-index:1040; overflow-y:auto; transition:transform 0.3s ease; transform:translateX(-100%); }
        .main-sidebar.open { transform:translateX(0); }
        .sidebar-brand { padding:20px 18px 15px; border-bottom:1px solid rgba(255,255,255,0.08); display:flex; align-items:center; gap:12px; }
        .sidebar-brand img { width:45px; height:45px; background:white; padding:5px; border-radius:10px; }
        .sidebar-brand .brand-text { color:white; font-weight:700; font-size:1.1rem; }
        .sidebar-brand .brand-text small { display:block; font-size:0.7rem; opacity:0.7; font-weight:300; }
        .sidebar-close-btn { display:none; position:absolute; top:12px; right:12px; background:rgba(255,255,255,0.1); color:white; border:none; border-radius:50%; width:36px; height:36px; }
        .user-panel { padding:18px 20px; border-bottom:1px solid rgba(255,255,255,0.08); display:flex; align-items:center; gap:12px; }
        .user-panel img { width:42px; height:42px; border-radius:50%; }
        .user-panel a { color:white; font-weight:600; text-decoration:none; }
        .user-panel small { color:rgba(255,255,255,0.6); display:block; font-size:0.75rem; }
        .nav-sidebar { padding:8px 0; }
        .nav-sidebar .nav-item { margin:2px 8px; }
        .nav-sidebar .nav-link { color:rgba(255,255,255,0.75); padding:10px 16px; border-radius:10px; display:flex; align-items:center; gap:12px; text-decoration:none; }
        .nav-sidebar .nav-link:hover { color:white; background:rgba(255,255,255,0.1); }
        .nav-sidebar .nav-link.active { color:white; background:rgba(255,215,0,0.15); border-left:3px solid #ffd700; }
        .nav-sidebar .nav-icon { width:22px; text-align:center; color:rgba(255,255,255,0.6); }
        .nav-sidebar .nav-link.active .nav-icon { color:#ffd700; }
        .nav-treeview { padding-left:15px; max-height:0; overflow:hidden; transition:max-height 0.3s ease; }
        .nav-treeview.show { max-height:500px; }
        .nav-treeview .nav-link { padding:8px 16px 8px 35px; font-size:0.85rem; color:rgba(255,255,255,0.6); }
        .content-wrapper { min-height:100vh; padding:20px; transition:margin-left 0.3s ease; }
        .content-header { padding-bottom:15px; border-bottom:2px solid #e9ecef; margin-bottom:20px; }
        .content-header h1 { font-weight:700; font-size:1.8rem; color:#1a2a4a; }
        .stat-card { border:none; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,0.06); margin-bottom:20px; }
        .stat-card .stat-body { padding:20px 25px; display:flex; align-items:center; justify-content:space-between; }
        .stat-card .stat-icon { width:55px; height:55px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.6rem; color:white; }
        .stat-card .stat-info h3 { font-size:1.8rem; font-weight:700; margin:0; color:#1a2a4a; }
        .stat-card .stat-info p { margin:0; color:#6c757d; font-size:0.9rem; }
        .stat-card.bg-primary .stat-icon { background:linear-gradient(135deg,#1a3a6b,#0d1b3e); }
        .stat-card.bg-success .stat-icon { background:linear-gradient(135deg,#28a745,#20c997); }
        .stat-card.bg-danger .stat-icon { background:linear-gradient(135deg,#dc3545,#e74c3c); }
        .stat-card.bg-info .stat-icon { background:linear-gradient(135deg,#0d6efd,#0a58ca); }
        .stat-card.bg-warning .stat-icon { background:linear-gradient(135deg,#ffc107,#fd7e14); }
        .card { border:none; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,0.06); margin-bottom:20px; }
        .card-header { background:white; border-bottom:2px solid #f0f2f5; padding:18px 25px; border-radius:16px 16px 0 0 !important; }
        .card-body { padding:25px; }
        .form-label { font-weight:600; font-size:0.9rem; color:#1a2a4a; }
        .form-control, .form-select { border:2px solid #e9ecef; border-radius:10px; padding:10px 16px; }
        .form-control:focus, .form-select:focus { border-color:#1a3a6b; box-shadow:0 0 0 4px rgba(26,58,107,0.12); }
        .table thead th { background:#f8f9fa; color:#1a2a4a; font-weight:600; font-size:0.85rem; text-transform:uppercase; border-bottom:2px solid #e9ecef; padding:12px 15px; }
        .table tbody td { padding:12px 15px; border-bottom:1px solid #f0f2f5; font-size:0.9rem; }
        .badge { padding:6px 14px; border-radius:20px; font-weight:600; font-size:0.75rem; }
        .btn-primary { background:linear-gradient(135deg,#1a3a6b,#0d1b3e); border:none; border-radius:10px; padding:10px 24px; font-weight:600; color:white; }
        .btn-success { background:linear-gradient(135deg,#28a745,#20c997); border:none; border-radius:10px; padding:10px 24px; font-weight:600; color:white; }
        .btn-info { border-radius:8px; padding:4px 12px; font-size:0.8rem; }
        .btn-danger { border-radius:8px; padding:4px 12px; font-size:0.8rem; }
        .btn-outline-secondary { border:2px solid #e9ecef; border-radius:10px; padding:8px 20px; font-weight:600; }
        .alert { border-radius:12px; border:none; padding:16px 22px; }
        .alert-success { background:#d4edda; color:#155724; border-left:5px solid #28a745; }
        .alert-danger { background:#fde8e8; color:#721c24; border-left:5px solid #dc3545; }
        .alert-warning { background:#fff3cd; color:#856404; border-left:5px solid #ffc107; }
        .modal-content { border-radius:16px; border:none; }
        .modal-header { background:linear-gradient(135deg,#1a3a6b,#0d1b3e); color:white; border-radius:16px 16px 0 0 !important; }
        .modal-header .btn-close { filter:brightness(0) invert(1); }
        .main-footer { background:white; padding:15px 20px; border-top:1px solid #e9ecef; text-align:center; font-size:0.9rem; color:#6c757d; margin-left:280px; }
        .status-indicator { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; }
        .status-indicator.active { background:#28a745; }
        .status-indicator.inactive { background:#dc3545; }
        .empty-state { padding:60px 20px; text-align:center; }
        .empty-state .empty-icon { font-size:4rem; color:#d1d5db; margin-bottom:20px; }
        @media (min-width:992px) {
            .main-sidebar { transform:translateX(0) !important; }
            .sidebar-toggle-btn, .sidebar-overlay { display:none !important; }
            .content-wrapper { margin-left:280px; }
        }
        @media (max-width:991.98px) {
            .sidebar-toggle-btn { display:block; }
            .main-sidebar.open { transform:translateX(0); }
            .content-wrapper { margin-left:0; padding-top:70px; }
            .sidebar-close-btn { display:block; }
            .main-footer { margin-left:0; }
        }
        @media (max-width:576px) {
            .card-header { padding:15px 18px; flex-wrap:wrap; }
            .card-body { padding:18px 15px; }
            .btn-primary, .btn-success { width:100%; }
            .btn-outline-secondary.ms-2 { margin-left:0 !important; margin-top:8px; }
            .stat-card .stat-info h3 { font-size:1.4rem; }
            .stat-card .stat-icon { width:45px; height:45px; font-size:1.3rem; }
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
    <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column">
            <li class="nav-item"><a href="index.php" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
            <li class="nav-item"><a href="students.php" class="nav-link"><i class="nav-icon fas fa-users"></i><p>Students</p></a></li>
            <li class="nav-item"><a href="bulk-students.php" class="nav-link"><i class="nav-icon fas fa-file-upload"></i><p>Bulk Upload</p></a></li>
           <li class="nav-item">
    <a href="manage-staff.php" class="nav-link">
        <i class="nav-icon fas fa-user-tie"></i> Manage Staff
    </a>
</li>
            <li class="nav-item"><a href="rooms.php" class="nav-link"><i class="nav-icon fas fa-bed"></i><p>Rooms</p></a></li>
        <li class="nav-item"><a href="post-room.php" class="nav-link"><i class="nav-icon fas fa-plus-square"></i><p>Post Room</p></a></li>
        <li class="nav-item"><a href="hostels.php" class="nav-link active"><i class="nav-icon fas fa-building"></i><p>Hostels</p></a></li>
            <li class="nav-item"><a href="allocations.php" class="nav-link"><i class="nav-icon fas fa-door-open"></i><p>Allocations</p></a></li>
            <li class="nav-item"><a href="payments.php" class="nav-link"><i class="nav-icon fas fa-credit-card"></i><p>Payments</p></a></li>
             <li class="nav-item has-treeview">
                <a href="#" class="nav-link">
                    <i class="nav-icon fas fa-clipboard-list"></i>
                    <p>
                        Requests
                        <i class="right fas fa-angle-left"></i>
                    </p>
                </a>
                <ul class="nav nav-treeview">
                    <li class="nav-item">
                        <a href="complaints.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Complaints</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="maintenance.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Maintenance</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="refunds.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Refunds</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="room-change.php" class="nav-link">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Room Changes</p>
                        </a>
                    </li>
                </ul>
            </li>   
            <li class="nav-item">
                <a href="reports.php" class="nav-link">
                    <i class="nav-icon fas fa-chart-line"></i><p>Reports</p>
                </a>
            </li>

        <li class="nav-item">
            <a href="logs.php" class="nav-link">
                <i class="nav-icon fas fa-history"></i><p>Activity Logs</p>
            </a>
        </li>
            <li class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="nav-icon fas fa-sign-out-alt"></i><p>Logout</p></a></li>
        </ul>
    </nav>
</aside>

<div class="content-wrapper">
    <section class="content-header">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <h1><i class="fas fa-building me-2"></i>Manage Hostels</h1>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addHostelModal">
                <i class="fas fa-plus me-2"></i> Add New Hostel
            </button>
        </div>
    </section>

   

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stat-card bg-primary">
                    <div class="stat-body">
                        <div class="stat-info"><h3><?= $total_hostels ?></h3><p>Total Hostels</p></div>
                        <div class="stat-icon"><i class="fas fa-building"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stat-card bg-success">
                    <div class="stat-body">
                        <div class="stat-info"><h3><?= $active_count ?></h3><p>Active</p></div>
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stat-card bg-danger">
                    <div class="stat-body">
                        <div class="stat-info"><h3><?= $inactive_count ?></h3><p>Inactive</p></div>
                        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stat-card bg-info">
                    <div class="stat-body">
                        <div class="stat-info"><h3><?= $total_rooms ?></h3><p>Total Rooms</p></div>
                        <div class="stat-icon"><i class="fas fa-door-open"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stat-card bg-success">
                    <div class="stat-body">
                        <div class="stat-info"><h3><?= $available_rooms ?></h3><p>Available</p></div>
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6">
                <div class="stat-card bg-warning">
                    <div class="stat-body">
                        <div class="stat-info"><h3><?= $occupied_rooms ?></h3><p>Occupied</p></div>
                        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hostels Table -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="mb-0"><i class="fas fa-list me-2"></i>All Hostels (<?= $total_hostels ?>)</h3>
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control" placeholder="Search hostels..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                        <?php if ($search): ?>
                            <a href="hostels.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Hostel Name</th>
                            <th>Location</th>
                            <th>Contact</th>
                            <th>Rooms</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($hostels)): ?>
                            <tr><td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-building empty-icon"></i>
                                    <h4>No Hostels Found</h4>
                                    <p><?= $search ? 'No results match your search.' : 'Start by adding your first hostel.' ?></p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($hostels as $hostel): 
                                $status = $hostel['status'] ?? 'active';
                                $room_count = 0;
                                if ($has_hostel_id) {
                                    $hid = (int)$hostel['id'];
                                    $rc = $conn->query("SELECT COUNT(*) as c FROM rooms WHERE hostel_id = $hid")->fetch_assoc();
                                    $room_count = (int)($rc['c'] ?? 0);
                                }
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($hostel['name']) ?></strong>
                                        <?php if (!empty($hostel['description'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars(substr($hostel['description'], 0, 50)) ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($hostel['location'] ?? '-') ?></td>
                                    <td>
                                        <?php if (!empty($hostel['contact_phone'])): ?>
                                            <div><i class="fas fa-phone me-1"></i><?= htmlspecialchars($hostel['contact_phone']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($hostel['contact_email'])): ?>
                                            <div><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($hostel['contact_email']) ?></div>
                                        <?php endif; ?>
                                        <?php if (empty($hostel['contact_phone']) && empty($hostel['contact_email'])): ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-info"><?= $room_count ?> Rooms</span></td>
                                    <td>
                                        <span class="badge bg-<?= $status === 'active' ? 'success' : 'danger' ?>">
                                            <span class="status-indicator <?= $status ?>"></span><?= ucfirst($status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info edit-btn"
                                                data-id="<?= $hostel['id'] ?>"
                                                data-name="<?= htmlspecialchars($hostel['name']) ?>"
                                                data-description="<?= htmlspecialchars($hostel['description'] ?? '') ?>"
                                                data-location="<?= htmlspecialchars($hostel['location'] ?? '') ?>"
                                                data-phone="<?= htmlspecialchars($hostel['contact_phone'] ?? '') ?>"
                                                data-email="<?= htmlspecialchars($hostel['contact_email'] ?? '') ?>"
                                                data-status="<?= $status ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger delete-btn"
                                                data-id="<?= $hostel['id'] ?>"
                                                data-name="<?= htmlspecialchars($hostel['name']) ?>"
                                                data-room-count="<?= $room_count ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<footer class="main-footer">
    <strong>Dormitory Management System</strong> &copy; <?= date('Y') ?> | The Polytechnic Ibadan
</footer>

<!-- ADD MODAL -->
<div class="modal fade" id="addHostelModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><i class="fas fa-plus-circle"></i> Add New Hostel</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Hostel Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Main Hostel">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. North Campus">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Brief description..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Phone</label>
                            <input type="text" name="contact_phone" class="form-control" placeholder="08012345678">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Email</label>
                            <input type="email" name="contact_email" class="form-control" placeholder="hostel@example.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_hostel" class="btn btn-primary"><i class="fas fa-save me-2"></i>Add Hostel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editHostelModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><i class="fas fa-edit"></i> Edit Hostel</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id" id="editId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Hostel Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="editName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" id="editLocation" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="editDescription" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Phone</label>
                            <input type="text" name="contact_phone" id="editPhone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Email</label>
                            <input type="email" name="contact_email" id="editEmail" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_hostel" class="btn btn-primary"><i class="fas fa-save me-2"></i>Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#dc3545,#e74c3c); color:white;">
                <h4 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong id="deleteHostelName"></strong>?</p>
                <p class="text-danger"><strong>Cannot be undone.</strong></p>
                <div id="deleteWarning" class="alert alert-warning" style="display:none;">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This hostel has <strong id="deleteRoomCount">0</strong> room(s). Delete those first.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="id" id="deleteHostelId">
                    <button type="submit" name="delete_hostel" class="btn btn-danger" id="confirmDeleteBtn">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

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

    toggleBtn?.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
    });
    closeBtn?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    // Edit modal
    const editModal = new bootstrap.Modal(document.getElementById('editHostelModal'));
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('editId').value = this.dataset.id;
            document.getElementById('editName').value = this.dataset.name;
            document.getElementById('editDescription').value = this.dataset.description;
            document.getElementById('editLocation').value = this.dataset.location;
            document.getElementById('editPhone').value = this.dataset.phone;
            document.getElementById('editEmail').value = this.dataset.email;
            document.getElementById('editStatus').value = this.dataset.status;
            editModal.show();
        });
    });

    // Delete modal
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const roomCount = parseInt(this.dataset.roomCount) || 0;
            document.getElementById('deleteHostelName').textContent = this.dataset.name;
            document.getElementById('deleteHostelId').value = this.dataset.id;
            document.getElementById('deleteRoomCount').textContent = roomCount;

            const warn = document.getElementById('deleteWarning');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            if (roomCount > 0) {
                warn.style.display = 'block';
                confirmBtn.disabled = true;
                confirmBtn.style.opacity = '0.5';
            } else {
                warn.style.display = 'none';
                confirmBtn.disabled = false;
                confirmBtn.style.opacity = '1';
            }
            deleteModal.show();
        });
    });
});
</script>
</body>
</html>