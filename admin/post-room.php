<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
include '../config/db.php';

// ===== DEBUG: Show errors =====
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$success = $error = '';
$errors = [];
$debug_log = [];

// ===== CHECK REQUIRED COLUMNS =====
$column_check = $conn->query("SHOW COLUMNS FROM rooms");
$existing_cols = [];
while ($col = $column_check->fetch_assoc()) {
    $existing_cols[] = $col['Field'];
}
$has_hostel_id = in_array('hostel_id', $existing_cols);
$has_description = in_array('description', $existing_cols);

$debug_log[] = "Rooms columns: " . implode(', ', $existing_cols);

// Check room_images table
$img_table_exists = $conn->query("SHOW TABLES LIKE 'room_images'")->num_rows > 0;
$debug_log[] = "room_images table exists: " . ($img_table_exists ? 'YES' : 'NO');

// ===== FETCH HOSTELS =====
$hostels = [];
if ($has_hostel_id) {
    $res = $conn->query("SELECT id, name, location FROM hostels WHERE status='active' ORDER BY name");
    if ($res) $hostels = $res->fetch_all(MYSQLI_ASSOC);
    $debug_log[] = "Fetched " . count($hostels) . " hostels";
}

// ===== HANDLE POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_room'])) {

    $debug_log[] = "=== FORM SUBMITTED ===";

    $room_number    = strtoupper(trim($_POST['room_number'] ?? ''));
    $hostel_id      = (int)($_POST['hostel_id'] ?? 0);
    $block          = trim($_POST['block'] ?? '');
    $capacity       = (int)($_POST['capacity'] ?? 0);
    $gender         = $_POST['gender'] ?? '';
    $price          = (float)($_POST['price'] ?? 0);
    $description    = trim($_POST['description'] ?? '');

    $debug_log[] = "room_number: '$room_number'";
    $debug_log[] = "hostel_id: '$hostel_id'";
    $debug_log[] = "capacity: '$capacity'";
    $debug_log[] = "gender: '$gender'";
    $debug_log[] = "price: '$price'";

    // Validation
    if (empty($room_number)) $errors['room_number'] = "Room number is required.";
    if ($has_hostel_id && $hostel_id <= 0) $errors['hostel_id'] = "Please select a hostel.";
    if ($capacity < 1) $errors['capacity'] = "Capacity must be at least 1.";
    if (!in_array($gender, ['Male', 'Female', 'Mixed'])) $errors['gender'] = "Please select a valid gender.";
    if ($price <= 0) $errors['price'] = "Price must be greater than 0.";

    // Duplicate check
    if (empty($errors['room_number']) && $has_hostel_id && $hostel_id > 0) {
        $check = $conn->prepare("SELECT id FROM rooms WHERE room_number = ? AND hostel_id = ?");
        $check->bind_param("si", $room_number, $hostel_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors['room_number'] = "Room '$room_number' already exists in this hostel.";
        }
    }

    // Insert room
    if (empty($errors)) {
        $insert_cols = ['room_number', 'block', 'capacity', 'gender', 'price', 'status'];
        $insert_vals = [$room_number, $block, $capacity, $gender, $price, 'available'];
        $bind_types  = 'ssisds';

        if ($has_hostel_id) {
            $insert_cols[] = 'hostel_id';
            $insert_vals[] = $hostel_id;
            $bind_types .= 'i';
        }
        if ($has_description) {
            $insert_cols[] = 'description';
            $insert_vals[] = $description;
            $bind_types .= 's';
        }

        $placeholders = implode(', ', array_fill(0, count($insert_cols), '?'));
        $sql = "INSERT INTO rooms (" . implode(', ', $insert_cols) . ") VALUES ($placeholders)";

        $debug_log[] = "SQL: $sql";
        $debug_log[] = "Bind types: $bind_types";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $error = "SQL prepare failed: " . $conn->error;
            $debug_log[] = "❌ " . $error;
        } else {
            $stmt->bind_param($bind_types, ...$insert_vals);

            if ($stmt->execute()) {
                $new_room_id = $conn->insert_id;
                $debug_log[] = "✅ Room inserted with ID: $new_room_id";

                // ===== HANDLE PHOTO UPLOADS =====
                $uploaded = 0;
                if (!empty($_FILES['photos']['name'][0])) {
                    $debug_log[] = "Processing " . count($_FILES['photos']['name']) . " file(s)";

                    $upload_dir = '../uploads/rooms/';
                    if (!is_dir($upload_dir)) {
                        $debug_log[] = "Creating directory: $upload_dir";
                        @mkdir($upload_dir, 0755, true);
                    }

                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    $count = count($_FILES['photos']['name']);

                    for ($i = 0; $i < $count; $i++) {
                        if (empty($_FILES['photos']['name'][$i])) continue;

                        $ext = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
                        $size = $_FILES['photos']['size'][$i];
                        $tmp = $_FILES['photos']['tmp_name'][$i];

                        $debug_log[] = "File $i: {$ext}, " . ($size / 1024) . " KB";

                        if (!in_array($ext, $allowed)) {
                            $debug_log[] = "  ❌ Invalid extension";
                            continue;
                        }
                        if ($size > 3 * 1024 * 1024) {
                            $debug_log[] = "  ❌ Too large";
                            continue;
                        }

                        $filename = 'room_' . $new_room_id . '_' . time() . '_' . $i . '.' . $ext;
                        $dest = $upload_dir . $filename;
                        $rel_path = 'uploads/rooms/' . $filename;

                        if (move_uploaded_file($tmp, $dest)) {
                            $debug_log[] = "  ✅ File moved to $dest";

                            if ($img_table_exists) {
                                $is_primary = ($i === 0) ? 1 : 0;
                                $img_stmt = $conn->prepare("
                                    INSERT INTO room_images (room_id, image_path, is_primary, sort_order)
                                    VALUES (?, ?, ?, ?)
                                ");
                                $img_stmt->bind_param("isii", $new_room_id, $rel_path, $is_primary, $i);

                                if ($img_stmt->execute()) {
                                    $uploaded++;
                                    $debug_log[] = "  ✅ Image record inserted (primary: $is_primary)";
                                } else {
                                    $debug_log[] = "  ❌ Image insert failed: " . $img_stmt->error;
                                }
                            } else {
                                $debug_log[] = "  ⚠️ room_images table doesn't exist - file saved but not linked";
                            }
                        } else {
                            $debug_log[] = "  ❌ move_uploaded_file failed";
                        }
                    }
                }

                $success = "Room '$room_number' posted successfully!" . ($uploaded > 0 ? " ($uploaded photo(s) uploaded)" : "");
                $_POST = [];
            } else {
                $error = "Failed to insert room: " . $stmt->error;
                $debug_log[] = "❌ INSERT failed: " . $stmt->error;
            }
        }
    } else {
        $debug_log[] = "Validation errors: " . print_r($errors, true);
    }
}

// ===== STATS =====
$total_rooms     = $conn->query("SELECT COUNT(*) FROM rooms")->fetch_row()[0] ?? 0;
$available_rooms = $conn->query("SELECT COUNT(*) FROM rooms WHERE status='available'")->fetch_row()[0] ?? 0;
$total_photos = $img_table_exists ? ($conn->query("SELECT COUNT(*) FROM room_images")->fetch_row()[0] ?? 0) : 0;

// ===== RECENT ROOMS =====
$recent_rooms = [];
$res = $conn->query("
    SELECT r.*, h.name AS hostel_name
    FROM rooms r
    LEFT JOIN hostels h ON r.hostel_id = h.id
    ORDER BY r.id DESC
    LIMIT 6
");
if ($res) $recent_rooms = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Post New Room - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; padding: 20px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .card-header { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); color: white; border-radius: 16px 16px 0 0 !important; padding: 18px 25px; }
        .card-body { padding: 30px; }
        .debug-box { background: #1e1e1e; color: #4af626; padding: 20px; border-radius: 10px; font-family: monospace; font-size: 0.82rem; max-height: 400px; overflow-y: auto; margin-bottom: 20px; line-height: 1.6; }
        .form-control, .form-select { border: 2px solid #e9ecef; border-radius: 10px; padding: 10px 16px; margin-bottom: 15px; }
        .form-control:focus, .form-select:focus { border-color: #1a3a6b; box-shadow: 0 0 0 4px rgba(26,58,107,0.12); }
        .btn-primary { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); border: none; border-radius: 10px; padding: 12px 28px; font-weight: 600; color: white; }
        .alert { border-radius: 12px; padding: 16px 22px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
        .alert-danger { background: #fde8e8; color: #721c24; border-left: 5px solid #dc3545; }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 5px solid #ffc107; }
        .photo-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; margin-top: 15px; }
        .photo-preview-item { position: relative; border-radius: 10px; overflow: hidden; aspect-ratio: 1; border: 2px solid #e9ecef; }
        .photo-preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .photo-preview-item .remove-photo { position: absolute; top: 4px; right: 4px; background: rgba(220,53,69,0.9); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; }
        .photo-upload-area { border: 3px dashed #cbd5e1; border-radius: 12px; padding: 30px; text-align: center; cursor: pointer; background: #fafbfc; }
        .photo-upload-area:hover { border-color: #1a3a6b; background: #f0f4fa; }
        .photo-upload-area input { display: none; }
 body { font-family: 'Inter', sans-serif; background: #f4f6f9; }
        .sidebar-toggle-btn { position: fixed; top: 12px; left: 12px; z-index: 1050; background: #1a3a6b; color: white; border: none; border-radius: 10px; padding: 12px 16px; display: none; }
        .sidebar-toggle-btn:hover { background: #0d1b3e; }
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
        .user-panel a { color: white; font-weight: 600; text-decoration: none; }
        .user-panel small { color: rgba(255,255,255,0.6); display: block; font-size: 0.75rem; }
        .nav-sidebar { padding: 8px 0; }
        .nav-sidebar .nav-item { margin: 2px 8px; }
        .nav-sidebar .nav-link { color: rgba(255,255,255,0.75); padding: 10px 16px; border-radius: 10px; display: flex; align-items: center; gap: 12px; text-decoration: none; }
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
        .stat-card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .stat-card .stat-body { padding: 20px 25px; display: flex; align-items: center; justify-content: space-between; }
        .stat-card .stat-icon { width: 55px; height: 55px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; color: white; }
        .stat-card .stat-info h3 { font-size: 1.8rem; font-weight: 700; margin: 0; color: #1a2a4a; }
        .stat-card .stat-info p { margin: 0; color: #6c757d; font-size: 0.9rem; }
        .stat-card.bg-info .stat-icon { background: linear-gradient(135deg, #0d6efd, #0a58ca); }
        .stat-card.bg-success .stat-icon { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-card.bg-warning .stat-icon { background: linear-gradient(135deg, #ffc107, #fd7e14); }
        .stat-card.bg-primary .stat-icon { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .card-header { background: white; border-bottom: 2px solid #f0f2f5; padding: 18px 25px; border-radius: 16px 16px 0 0 !important; }
        .card-primary .card-header { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); color: white; }
        .card-primary .card-header .card-title { color: white; }
        .card-body { padding: 25px; }
        .form-label { font-weight: 600; font-size: 0.9rem; color: #1a2a4a; }
        .form-control, .form-select { border: 2px solid #e9ecef; border-radius: 10px; padding: 10px 16px; }
        .form-control:focus, .form-select:focus { border-color: #1a3a6b; box-shadow: 0 0 0 4px rgba(26,58,107,0.12); }
        .table thead th { background: #f8f9fa; color: #1a2a4a; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px solid #e9ecef; padding: 12px 15px; }
        .table tbody td { padding: 12px 15px; border-bottom: 1px solid #f0f2f5; font-size: 0.9rem; }
        .badge { padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 0.75rem; }
        .btn-primary { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); border: none; border-radius: 10px; padding: 10px 24px; font-weight: 600; color: white; }
        .btn-info { border-radius: 8px; padding: 4px 12px; font-size: 0.8rem; }
        .btn-danger { border-radius: 8px; padding: 4px 12px; font-size: 0.8rem; }
        .btn-outline-secondary { border: 2px solid #e9ecef; border-radius: 10px; padding: 8px 20px; font-weight: 600; }
        .alert { border-radius: 12px; border: none; padding: 16px 22px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
        .alert-danger { background: #fde8e8; color: #721c24; border-left: 5px solid #dc3545; }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 5px solid #ffc107; }
        .modal-content { border-radius: 16px; border: none; }
        .modal-header { background: linear-gradient(135deg, #dc3545, #e74c3c); color: white; border-radius: 16px 16px 0 0 !important; }
        .modal-header .btn-close { filter: brightness(0) invert(1); }
        .main-footer { background: white; padding: 15px 20px; border-top: 1px solid #e9ecef; text-align: center; font-size: 0.9rem; color: #6c757d; margin-left: 280px; }
        .hostel-badge { background: #e9ecef; color: #1a2a4a; padding: 4px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; display: inline-block; }
        .empty-state { padding: 60px 20px; text-align: center; }
        .empty-state .empty-icon { font-size: 4rem; color: #d1d5db; margin-bottom: 20px; }
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
            .card-header { padding: 15px 18px; }
            .card-body { padding: 18px 15px; }
            .btn-primary { width: 100%; }
            .btn-outline-secondary.ms-2 { margin-left: 0 !important; margin-top: 8px; }
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
           <li class="nav-item"><a href="rooms.php" class="nav-link active"><i class="nav-icon fas fa-bed"></i><p>Rooms</p></a></li>
        <li class="nav-item"><a href="post-room.php" class="nav-link"><i class="nav-icon fas fa-plus-square"></i><p>Post Room</p></a></li>
         <li class="nav-item"><a href="hostels.php" class="nav-link"><i class="nav-icon fas fa-building"></i><p>Hostels</p></a></li>
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
        <h1><i class="fas fa-bed me-2"></i>Post Rooms</h1>
    </section>

    <!-- DEBUG LOG -->
    <?php if (!empty($debug_log)): ?>
    <div class="debug-box">
        <strong>🔍 DEBUG LOG:</strong><br><br>
        <?php foreach ($debug_log as $line): ?>
            <?= htmlspecialchars($line) ?><br>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$img_table_exists): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Missing Table:</strong> The <code>room_images</code> table doesn't exist. Room will be saved, but photos won't be linked. Run this SQL:
            <pre style="background:#fff;padding:12px;border-radius:8px;margin-top:10px;overflow:auto;">CREATE TABLE room_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);</pre>
        </div>
    <?php endif; ?>

    <?php if (!$has_hostel_id): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Missing Column:</strong> The <code>rooms</code> table is missing <code>hostel_id</code>. Run:
            <pre style="background:#fff;padding:12px;border-radius:8px;margin-top:10px;">ALTER TABLE rooms ADD COLUMN hostel_id INT DEFAULT NULL;</pre>
        </div>
    <?php endif; ?>

    <!-- FORM -->
    <div class="card">
        <div class="card-header"><h3 class="mb-0"><i class="fas fa-edit"></i> Room Details</h3></div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Room Number *</label>
                        <input type="text" name="room_number" class="form-control" required
                               value="<?= htmlspecialchars($_POST['room_number'] ?? '') ?>"
                               style="text-transform: uppercase;" placeholder="e.g. A101">
                    </div>
                    <?php if ($has_hostel_id): ?>
                    <div class="col-md-4">
                        <label class="form-label">Hostel *</label>
                        <select name="hostel_id" class="form-select" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($hostels as $h): ?>
                                <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="form-label">Block</label>
                        <input type="text" name="block" class="form-control"
                               value="<?= htmlspecialchars($_POST['block'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Capacity *</label>
                        <input type="number" name="capacity" class="form-control" min="1" max="10" required
                               value="<?= htmlspecialchars($_POST['capacity'] ?? '2') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gender *</label>
                        <select name="gender" class="form-select" required>
                            <option value="">-- Select --</option>
                            <option value="Male">Male Only</option>
                            <option value="Female">Female Only</option>
                            <option value="Mixed">Mixed</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Price (₦) *</label>
                        <input type="number" name="price" class="form-control" step="0.01" required
                               placeholder="50000" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
                    </div>
                    <?php if ($has_description): ?>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                    <?php endif; ?>
                </div>

                <label class="form-label mt-3">Room Photos (up to 8, JPG/PNG, ≤3MB)</label>
                <div class="photo-upload-area" onclick="document.getElementById('photosInput').click()">
                    <input type="file" name="photos[]" id="photosInput" accept="image/*" multiple>
                    <i class="fas fa-cloud-upload-alt" style="font-size:3rem;color:#1a3a6b;"></i>
                    <p class="mt-2 mb-0"><strong>Click to select photos</strong> (or drag & drop)</p>
                </div>
                <div class="photo-preview-grid" id="previewGrid"></div>

                <div class="mt-4">
                    <button type="submit" name="post_room" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Post Room
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RECENT ROOMS -->
    <?php if (!empty($recent_rooms)): ?>
    <div class="card">
        <div class="card-header"><h3 class="mb-0"><i class="fas fa-clock"></i> Recent Rooms (<?= count($recent_rooms) ?>)</h3></div>
        <div class="card-body">
            <table class="table table-hover">
                <thead><tr><th>ID</th><th>Room #</th><th>Hostel</th><th>Price</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($recent_rooms as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><strong><?= htmlspecialchars($r['room_number']) ?></strong></td>
                        <td><?= htmlspecialchars($r['hostel_name'] ?? 'N/A') ?></td>
                        <td>₦<?= number_format((float)$r['price']) ?></td>
                        <td><span class="badge bg-<?= $r['status'] === 'available' ? 'success' : 'warning' ?>"><?= $r['status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('photosInput');
    const grid = document.getElementById('previewGrid');
    let files = [];

    input.addEventListener('change', function() {
        Array.from(this.files).forEach(f => {
            if (files.length >= 8) return;
            if (f.size > 3 * 1024 * 1024) return;
            files.push(f);
        });
        this.value = '';
        render();
    });

    function render() {
        grid.innerHTML = '';
        files.forEach((f, i) => {
            const url = URL.createObjectURL(f);
            const div = document.createElement('div');
            div.className = 'photo-preview-item';
            div.innerHTML = `<img src="${url}"><button type="button" class="remove-photo" data-i="${i}">×</button>`;
            grid.appendChild(div);
        });
        grid.querySelectorAll('.remove-photo').forEach(b => {
            b.addEventListener('click', function() {
                files.splice(parseInt(this.dataset.i), 1);
                render();
            });
        });
    }

    document.querySelector('form').addEventListener('submit', function() {
        const dt = new DataTransfer();
        files.forEach(f => dt.items.add(f));
        input.files = dt.files;
    });
});
</script>
</body>
</html>