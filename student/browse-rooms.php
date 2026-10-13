<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}
include '../config/db.php';
include '../config/paystack.php';

$student_id = $_SESSION['student_id'];

// Fetch student
$student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();
if (!$student) { header("Location: login.php"); exit; }

// Check if student already has an active allocation
$existing_allocation = $conn->query("
    SELECT a.id, r.room_number, h.name AS hostel_name
    FROM allocations a
    JOIN rooms r ON a.room_id = r.id
    LEFT JOIN hostels h ON r.hostel_id = h.id
    WHERE a.student_id = $student_id AND a.status = 'active'
    LIMIT 1
")->fetch_assoc();

// ===== FILTERS =====
$filter_hostel      = (int)($_GET['hostel'] ?? 0);
$filter_gender      = $_GET['gender'] ?? '';
$filter_min         = (float)($_GET['min_price'] ?? 0);
$filter_max         = (float)($_GET['max_price'] ?? 0);
$filter_bed_spacing = $_GET['bed_spacing'] ?? '';
$filter_sort        = $_GET['sort'] ?? 'room_number';
$search             = trim($_GET['search'] ?? '');

// ===== BUILD QUERY =====
$where = ["r.status = 'available'"];

// Filter by student's gender (rooms must match)
if (!empty($student['gender'])) {
    $g = $conn->real_escape_string($student['gender']);
    $where[] = "(r.gender = '$g' OR r.gender = 'Mixed')";
}

if ($filter_hostel > 0) {
    $where[] = "r.hostel_id = $filter_hostel";
}

if ($filter_gender && in_array($filter_gender, ['Male', 'Female', 'Mixed'])) {
    $fg = $conn->real_escape_string($filter_gender);
    $where[] = "r.gender = '$fg'";
}

if ($filter_min > 0) {
    $where[] = "r.price >= $filter_min";
}
if ($filter_max > 0) {
    $where[] = "r.price <= $filter_max";
}

// Bed spacing filter
$allowed_spacings = ['single', 'double', 'triple', 'quad', 'bunk', 'spacious', 'standard', 'compact'];
if ($filter_bed_spacing && in_array(strtolower($filter_bed_spacing), $allowed_spacings)) {
    $bs = $conn->real_escape_string(strtolower($filter_bed_spacing));
    $where[] = "r.bed_spacing = '$bs'";
}

if ($search) {
    $s = $conn->real_escape_string($search);
    $where[] = "(r.room_number LIKE '%$s%' OR h.name LIKE '%$s%' OR h.location LIKE '%$s%')";
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

// Sorting
$order_by = "h.name ASC, r.room_number ASC";
switch ($filter_sort) {
    case 'price_low':   $order_by = "r.price ASC"; break;
    case 'price_high':  $order_by = "r.price DESC"; break;
    case 'capacity':    $order_by = "r.capacity DESC"; break;
    case 'hostel':      $order_by = "h.name ASC"; break;
    case 'bed_spacing': $order_by = "r.bed_spacing ASC, h.name ASC"; break;
    default:            $order_by = "h.name ASC, r.room_number ASC";
}

// ===== FETCH ROOMS WITH PHOTO DATA =====
$rooms = [];
$result = $conn->query("
    SELECT r.*, 
           h.name AS hostel_name, 
           h.location AS hostel_location,
           (SELECT COUNT(*) FROM allocations a WHERE a.room_id = r.id AND a.status = 'active') as current_occupants,
           (SELECT image_path FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) AS primary_photo,
           (SELECT COUNT(*) FROM room_images WHERE room_id = r.id) AS photo_count
    FROM rooms r
    LEFT JOIN hostels h ON r.hostel_id = h.id
    $where_sql
    ORDER BY $order_by
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
}

// ===== FETCH ALL HOSTELS (for filter dropdown) =====
$hostels = [];
$res = $conn->query("SELECT id, name FROM hostels WHERE status = 'active' ORDER BY name");
if ($res) $hostels = $res->fetch_all(MYSQLI_ASSOC);

// ===== FETCH ALL IMAGES GROUPED BY ROOM =====
$room_images_map = [];
$img_res = $conn->query("
    SELECT room_id, image_path, is_primary 
    FROM room_images 
    ORDER BY room_id ASC, is_primary DESC, sort_order ASC
");
if ($img_res) {
    while ($img = $img_res->fetch_assoc()) {
        $room_images_map[$img['room_id']][] = $img['image_path'];
    }
}

// ===== STATS =====
$total_available = count($rooms);
$room_id = (int)($_GET['room_id'] ?? 0);
$selected_room = null;

if ($room_id > 0) {
    foreach ($rooms as $r) {
        if ((int)$r['id'] === $room_id) {
            $selected_room = $r;
            break;
        }
    }
}

// Build rooms data map for JS
$rooms_data_map = [];
foreach ($rooms as $r) {
    $rooms_data_map[(string)$r['id']] = $r;
}

// ===== HELPERS =====
function bedSpacingLabel($spacing) {
    switch (strtolower(trim($spacing ?? ''))) {
        case 'single':   return 'Single';
        case 'double':   return 'Double';
        case 'triple':   return 'Triple';
        case 'quad':     return 'Quad';
        case 'bunk':     return 'Bunk Beds';
        case 'spacious': return 'Spacious';
        case 'standard': return 'Standard';
        case 'compact':  return 'Compact';
        default:         return $spacing ? ucfirst($spacing) : 'Standard';
    }
}

function bedSpacingIcon($spacing) {
    switch (strtolower(trim($spacing ?? ''))) {
        case 'bunk':     return 'fa-layer-group';
        case 'spacious': return 'fa-arrows-alt';
        case 'compact':  return 'fa-compress';
        default:         return 'fa-bed';
    }
}

function bedSpacingBadgeClass($spacing) {
    switch (strtolower(trim($spacing ?? ''))) {
        case 'bunk':     return 'spacing-bunk';
        case 'spacious': return 'spacing-spacious';
        case 'compact':  return 'spacing-compact';
        case 'single':   return 'spacing-single';
        default:         return 'spacing-standard';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Browse Rooms - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; overflow-x: hidden; }

        /* Sidebar */
        .sidebar-toggle-btn { position: fixed; top: 12px; left: 12px; z-index: 1050; background: #1a3a6b; color: white; border: none; border-radius: 10px; padding: 12px 16px; display: none; box-shadow: 0 4px 20px rgba(26,58,107,0.3); }
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
        .user-panel img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; }
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

        .content-wrapper { min-height: 100vh; padding: 20px; transition: margin-left 0.3s ease; padding-bottom: 120px; }
        .content-header { padding-bottom: 15px; border-bottom: 2px solid #e9ecef; margin-bottom: 20px; }
        .content-header h1 { font-weight: 700; font-size: 1.8rem; color: #1a2a4a; }
        .content-header h1 i { color: #1a3a6b; }

        /* Filter bar */
        .filter-bar { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .filter-bar label { font-size: 0.78rem; font-weight: 700; color: #1a2a4a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; display: block; }
        .filter-bar .form-control, .filter-bar .form-select { border: 2px solid #e9ecef; border-radius: 10px; padding: 9px 14px; font-size: 0.9rem; }
        .filter-bar .form-control:focus, .filter-bar .form-select:focus { border-color: #1a3a6b; box-shadow: 0 0 0 4px rgba(26,58,107,0.12); }

        .btn-primary { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); border: none; border-radius: 10px; padding: 10px 22px; font-weight: 600; color: white; transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(26,58,107,0.4); color: white; }
        .btn-outline-secondary { border: 2px solid #e9ecef; border-radius: 10px; padding: 10px 20px; font-weight: 600; }
        .btn-success { background: linear-gradient(135deg, #28a745, #20c997); border: none; border-radius: 10px; padding: 12px 28px; font-weight: 700; color: white; transition: all 0.3s ease; }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(40,167,69,0.4); color: white; }

        /* Info banner */
        .info-banner { background: linear-gradient(135deg, #e7f3ff, #d4e4fc); border-left: 5px solid #0d6efd; padding: 16px 22px; border-radius: 12px; margin-bottom: 20px; display: flex; gap: 15px; align-items: center; }
        .info-banner i { font-size: 1.8rem; color: #0d6efd; flex-shrink: 0; }
        .info-banner .text { flex: 1; }
        .info-banner h6 { margin: 0; font-weight: 700; color: #084298; font-size: 0.95rem; }
        .info-banner p { margin: 0; color: #084298; font-size: 0.85rem; opacity: 0.85; }

        /* ========== ROOMS GRID ========== */
        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 22px;
        }
        .room-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            border: 2px solid transparent;
            animation: fadeInUp 0.5s ease;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
            border-color: #1a3a6b;
        }
        .room-card.selected {
            border-color: #28a745;
            box-shadow: 0 8px 30px rgba(40,167,69,0.25);
        }

        /* ========== PHOTO BANNER ========== */
        .room-photo-banner {
            width: 100%;
            height: 200px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #e9ecef, #dee2e6);
            cursor: pointer;
        }
        .room-photo-banner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .room-card:hover .room-photo-banner img {
            transform: scale(1.08);
        }

        .room-photo-banner::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60%;
            background: linear-gradient(to top, rgba(0,0,0,0.6) 0%, transparent 100%);
            pointer-events: none;
            z-index: 1;
        }

        /* Photo count badge */
        .photo-count-tag {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            z-index: 2;
            border: 1px solid rgba(255,255,255,0.15);
            transition: all 0.3s ease;
        }
        .room-card:hover .photo-count-tag {
            background: rgba(26, 58, 107, 0.95);
            transform: translateY(-2px);
        }
        .photo-count-tag i { font-size: 0.85rem; color: #ffd700; }

        /* Status badge */
        .room-photo-status {
            position: absolute;
            top: 12px;
            left: 12px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .room-photo-status.available { background: rgba(40, 167, 69, 0.95); color: white; }
        .room-photo-status.limited { background: rgba(253, 126, 20, 0.95); color: white; }

        /* Bed spacing badge on photo */
        .bed-spacing-photo-badge {
            position: absolute;
            top: 56px;
            left: 12px;
            background: rgba(255, 215, 0, 0.95);
            color: #856404;
            padding: 5px 12px;
            border-radius: 16px;
            font-size: 0.72rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 5px;
            z-index: 2;
            box-shadow: 0 4px 12px rgba(255, 183, 0, 0.4);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid rgba(255,255,255,0.4);
        }
        .bed-spacing-photo-badge i { font-size: 0.7rem; }

        /* Price tag */
        .room-photo-banner .price-overlay {
            position: absolute;
            bottom: 12px;
            right: 12px;
            background: linear-gradient(135deg, #ffd700, #ffb700);
            color: #1a3a6b;
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 800;
            font-size: 1rem;
            z-index: 2;
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.4);
            transition: all 0.3s ease;
        }
        .room-card:hover .price-overlay {
            transform: scale(1.08);
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.55);
        }

        .room-photo-banner .room-number-overlay {
            position: absolute;
            bottom: 12px;
            left: 12px;
            color: white;
            font-weight: 800;
            font-size: 1.35rem;
            letter-spacing: 0.5px;
            z-index: 2;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .room-photo-banner .room-number-overlay i { font-size: 1.15rem; color: #ffd700; }

        .room-photo-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            color: #adb5bd;
        }
        .room-photo-placeholder i { font-size: 4rem; margin-bottom: 10px; opacity: 0.5; }
        .room-photo-placeholder span { font-size: 0.85rem; font-weight: 600; opacity: 0.7; }

        .view-photos-btn {
            position: absolute;
            bottom: 12px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: rgba(255, 255, 255, 0.95);
            color: #1a3a6b;
            border: none;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            opacity: 0;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 3;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
            white-space: nowrap;
        }
        .room-card:hover .view-photos-btn {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        .view-photos-btn:hover {
            background: #1a3a6b;
            color: white;
            transform: translateX(-50%) translateY(-2px) !important;
        }

        /* Card body */
        .room-card-body { padding: 20px 22px; flex: 1; }

        .room-hostel-line {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .room-hostel-line i { color: #1a3a6b; margin-right: 4px; }

        .room-features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 15px;
            margin-bottom: 15px;
        }
        .feature-item { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #495057; }
        .feature-item i { color: #1a3a6b; font-size: 0.9rem; width: 18px; text-align: center; }
        .feature-item strong { color: #1a2a4a; }

        /* Feature item for bed spacing (highlighted) */
        .feature-item.bed-feature { 
            background: linear-gradient(135deg, #fffbea, #fff4cc);
            margin: -4px -8px;
            padding: 6px 8px;
            border-radius: 8px;
        }
        .feature-item.bed-feature i { color: #d4a017; }

        .capacity-bar { margin-top: 12px; padding-top: 12px; border-top: 1px dashed #e9ecef; }
        .capacity-bar .label { font-size: 0.78rem; color: #6c757d; font-weight: 600; display: flex; justify-content: space-between; margin-bottom: 6px; }
        .capacity-bar .progress { height: 8px; border-radius: 4px; background: #e9ecef; }
        .capacity-bar .progress-bar { border-radius: 4px; }

        .room-card-footer {
            padding: 15px 22px;
            background: #f8f9fa;
            border-top: 1px solid #f0f2f5;
            display: flex;
            gap: 10px;
        }
        .room-card-footer .btn {
            flex: 1;
            padding: 10px;
            font-size: 0.88rem;
            font-weight: 700;
            border-radius: 10px;
        }

        /* ========== PAYMENT PANEL ========== */
        .payment-panel {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: white;
            box-shadow: 0 -10px 40px rgba(0,0,0,0.15);
            padding: 20px 25px;
            z-index: 1030;
            transform: translateY(100%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-top: 4px solid #28a745;
        }
        .payment-panel.show { transform: translateY(0); }
        @media (min-width: 992px) { .payment-panel { left: 280px; } }

        .payment-panel .panel-inner { max-width: 1400px; margin: 0 auto; display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
        .payment-panel .selected-info { flex: 1; min-width: 200px; display: flex; align-items: center; gap: 15px; }
        .payment-panel .selected-info .icon-box {
            width: 55px; height: 55px; border-radius: 12px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white; display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; flex-shrink: 0;
        }
        .payment-panel .selected-info .details h5 { margin: 0; font-weight: 800; color: #1a2a4a; font-size: 1.1rem; }
        .payment-panel .selected-info .details p { margin: 0; color: #6c757d; font-size: 0.85rem; }
        .payment-panel .price-display { font-size: 1.8rem; font-weight: 800; color: #28a745; margin: 0; }
        .payment-panel .price-label { font-size: 0.78rem; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin: 0; text-align: right; }

        /* ========== MODAL GALLERY ========== */
        .modal-gallery-section { margin-bottom: 20px; }

        .modal-gallery-main {
            position: relative;
            width: 100%;
            height: 380px;
            border-radius: 16px;
            overflow: hidden;
            background: #000;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .modal-gallery-main img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .gallery-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            color: white;
            border: none;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all 0.3s ease;
            z-index: 3;
        }
        .gallery-nav:hover {
            background: rgba(26, 58, 107, 0.95);
            transform: translateY(-50%) scale(1.1);
        }
        .gallery-nav.prev { left: 15px; }
        .gallery-nav.next { right: 15px; }

        .gallery-counter {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            z-index: 3;
        }
        .gallery-counter i { color: #ffd700; }

        .modal-gallery-thumbs {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            overflow-x: auto;
            padding-bottom: 6px;
            scroll-behavior: smooth;
        }
        .modal-gallery-thumbs::-webkit-scrollbar { height: 6px; }
        .modal-gallery-thumbs::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

        .gallery-thumb {
            width: 85px;
            height: 65px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.25s ease;
            flex-shrink: 0;
            opacity: 0.65;
        }
        .gallery-thumb:hover { opacity: 1; transform: translateY(-2px); }
        .gallery-thumb.active {
            border-color: #1a3a6b;
            opacity: 1;
            box-shadow: 0 4px 12px rgba(26, 58, 107, 0.35);
        }

        .modal-gallery-empty {
            text-align: center;
            padding: 60px 20px;
            background: #f8f9fa;
            border-radius: 16px;
            margin-bottom: 20px;
        }
        .modal-gallery-empty i { font-size: 4rem; color: #cbd5e1; margin-bottom: 12px; }
        .modal-gallery-empty p { color: #6c757d; font-weight: 600; margin: 0; }

        .detail-box { background: #f8f9fa; padding: 12px 16px; border-radius: 10px; height: 100%; }
        .detail-box small { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; }

        /* Bed spacing detail box (highlighted) */
        .detail-box.bed-detail-box {
            background: linear-gradient(135deg, #fffbea, #fff4cc);
            border: 2px solid #ffd700;
        }
        .detail-box.bed-detail-box small { color: #856404; }
        .detail-box.bed-detail-box strong { color: #856404; }

        /* Empty state */
        .empty-state { padding: 80px 20px; text-align: center; }
        .empty-state i { font-size: 5rem; color: #d1d5db; margin-bottom: 20px; }
        .empty-state h4 { color: #1a2a4a; font-weight: 700; margin-bottom: 10px; }

        .main-footer { background: white; padding: 15px 20px; border-top: 1px solid #e9ecef; text-align: center; font-size: 0.9rem; color: #6c757d; margin-left: 280px; margin-bottom: 100px; }

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
            .rooms-grid { grid-template-columns: 1fr; gap: 18px; }
            .room-photo-banner { height: 180px; }
            .room-photo-banner .room-number-overlay { font-size: 1.1rem; }
            .room-photo-banner .price-overlay { padding: 6px 12px; font-size: 0.85rem; }
            .photo-count-tag { padding: 4px 10px; font-size: 0.7rem; }
            .room-photo-status { padding: 4px 10px; font-size: 0.65rem; }
            .bed-spacing-photo-badge { padding: 4px 10px; font-size: 0.65rem; top: 48px; }
            .view-photos-btn { opacity: 1; transform: translateX(-50%) translateY(0); }
            .payment-panel { padding: 15px; }
            .payment-panel .panel-inner { gap: 12px; }
            .payment-panel .price-display { font-size: 1.4rem; }
            .payment-panel .btn-success { width: 100%; }
            .modal-gallery-main { height: 260px; }
            .gallery-nav { width: 36px; height: 36px; font-size: 0.85rem; }
            .gallery-nav.prev { left: 10px; }
            .gallery-nav.next { right: 10px; }
            .gallery-thumb { width: 65px; height: 50px; }
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
        <?php 
        $photo = $student['photo'] ?? '';
        if ($photo && file_exists('../' . $photo)): ?>
            <img src="../<?= htmlspecialchars($photo) ?>">
        <?php else: ?>
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['full_name'] ?? 'Student') ?>&background=ffd700&color=1a3a6b&size=42">
        <?php endif; ?>
        <div>
            <a href="profile.php"><?= htmlspecialchars($student['full_name'] ?? 'Student') ?></a>
            <small><?= htmlspecialchars($student['matric_no'] ?? '') ?></small>
        </div>
    </div>
    <ul class="nav nav-pills nav-sidebar flex-column">
        <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
        <li class="nav-item"><a href="browse-rooms.php" class="nav-link active"><i class="nav-icon fas fa-search"></i><p>Browse Rooms</p></a></li>
        <li class="nav-item"><a href="my-room.php" class="nav-link"><i class="nav-icon fas fa-bed"></i><p>My Room</p></a></li>
        <li class="nav-item"><a href="profile.php" class="nav-link"><i class="nav-icon fas fa-user"></i><p>My Profile</p></a></li>
        <li class="nav-item"><a href="payment.php" class="nav-link"><i class="nav-icon fas fa-credit-card"></i><p>Payment History</p></a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="nav-icon fas fa-sign-out-alt"></i><p>Logout</p></a></li>
    </ul>
</aside>

<div class="content-wrapper">
    <section class="content-header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h1><i class="fas fa-search me-2"></i>Browse Available Rooms</h1>
            <span class="badge bg-success" style="padding: 10px 18px; font-size: 0.9rem;">
                <i class="fas fa-door-open me-1"></i> <?= $total_available ?> Rooms Available
            </span>
        </div>
    </section>

    <?php if ($existing_allocation): ?>
        <div class="alert alert-warning d-flex align-items-center" style="border-radius:12px; padding:18px 22px;">
            <i class="fas fa-info-circle me-3" style="font-size:1.5rem;"></i>
            <div>
                <strong>You already have an allocated room!</strong>
                <p class="mb-0 small">You have been allocated Room <strong><?= htmlspecialchars($existing_allocation['room_number']) ?></strong>
                at <strong><?= htmlspecialchars($existing_allocation['hostel_name'] ?? 'N/A') ?></strong>. 
                If you want to change rooms, please submit a <a href="room-change.php" class="fw-bold">room change request</a>.</p>
            </div>
        </div>
    <?php else: ?>

    <div class="info-banner">
        <i class="fas fa-info-circle"></i>
        <div class="text">
            <h6>Choose Your Preferred Room</h6>
            <p>Rooms shown are available and match your gender (<strong><?= htmlspecialchars($student['gender']) ?></strong>). 
            Use the <strong>Bed Spacing</strong> filter to find rooms that match your comfort preference. Click on a photo to see more images, then select a room to proceed with payment.</p>
        </div>
    </div>

    <!-- FILTER BAR -->
    <form method="GET" class="filter-bar">
        <div class="row g-3">
            <div class="col-lg-3 col-md-6">
                <label><i class="fas fa-search me-1"></i>Search</label>
                <input type="text" name="search" class="form-control" 
                       placeholder="Room, hostel, location..." 
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-lg-2 col-md-6">
                <label><i class="fas fa-building me-1"></i>Hostel</label>
                <select name="hostel" class="form-select">
                    <option value="">All Hostels</option>
                    <?php foreach ($hostels as $h): ?>
                        <option value="<?= $h['id'] ?>" <?= $filter_hostel == $h['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($h['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label><i class="fas fa-venus-mars me-1"></i>Gender</label>
                <select name="gender" class="form-select">
                    <option value="">Any</option>
                    <option value="Male" <?= $filter_gender === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $filter_gender === 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Mixed" <?= $filter_gender === 'Mixed' ? 'selected' : '' ?>>Mixed</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label><i class="fas fa-bed me-1"></i>Bed Spacing</label>
                <select name="bed_spacing" class="form-select">
                    <option value="">Any</option>
                    <option value="single"   <?= $filter_bed_spacing === 'single'   ? 'selected' : '' ?>>Single</option>
                    <option value="double"   <?= $filter_bed_spacing === 'double'   ? 'selected' : '' ?>>Double</option>
                    <option value="triple"   <?= $filter_bed_spacing === 'triple'   ? 'selected' : '' ?>>Triple</option>
                    <option value="quad"     <?= $filter_bed_spacing === 'quad'     ? 'selected' : '' ?>>Quad</option>
                    <option value="bunk"     <?= $filter_bed_spacing === 'bunk'     ? 'selected' : '' ?>>Bunk Beds</option>
                    <option value="spacious" <?= $filter_bed_spacing === 'spacious' ? 'selected' : '' ?>>Spacious</option>
                    <option value="compact"  <?= $filter_bed_spacing === 'compact'  ? 'selected' : '' ?>>Compact</option>
                    <option value="standard" <?= $filter_bed_spacing === 'standard' ? 'selected' : '' ?>>Standard</option>
                </select>
            </div>
            <div class="col-lg-1 col-md-4">
                <label><i class="fas fa-money-bill me-1"></i>Min ₦</label>
                <input type="number" name="min_price" class="form-control" placeholder="Min" value="<?= $filter_min ?: '' ?>">
            </div>
            <div class="col-lg-1 col-md-4">
                <label><i class="fas fa-money-bill-wave me-1"></i>Max ₦</label>
                <input type="number" name="max_price" class="form-control" placeholder="Max" value="<?= $filter_max ?: '' ?>">
            </div>
            <div class="col-lg-1 col-md-4">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100" title="Apply filters">
                    <i class="fas fa-filter"></i>
                </button>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-12 d-flex flex-wrap gap-2">
                <span class="me-2" style="font-size:0.85rem; align-self:center; color:#6c757d;">Sort by:</span>
                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'room_number'])) ?>" 
                   class="btn btn-sm <?= $filter_sort === 'room_number' ? 'btn-primary' : 'btn-outline-secondary' ?>">Room #</a>
                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'price_low'])) ?>" 
                   class="btn btn-sm <?= $filter_sort === 'price_low' ? 'btn-primary' : 'btn-outline-secondary' ?>">Price ↑</a>
                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'price_high'])) ?>" 
                   class="btn btn-sm <?= $filter_sort === 'price_high' ? 'btn-primary' : 'btn-outline-secondary' ?>">Price ↓</a>
                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'capacity'])) ?>" 
                   class="btn btn-sm <?= $filter_sort === 'capacity' ? 'btn-primary' : 'btn-outline-secondary' ?>">Capacity</a>
                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'bed_spacing'])) ?>" 
                   class="btn btn-sm <?= $filter_sort === 'bed_spacing' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <i class="fas fa-bed me-1"></i>Bed Spacing
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'hostel'])) ?>" 
                   class="btn btn-sm <?= $filter_sort === 'hostel' ? 'btn-primary' : 'btn-outline-secondary' ?>">Hostel</a>
                <a href="browse-rooms.php" class="btn btn-sm btn-outline-secondary ms-auto">
                    <i class="fas fa-redo me-1"></i>Reset
                </a>
            </div>
        </div>
    </form>

    <!-- ROOMS GRID -->
    <?php if (empty($rooms)): ?>
        <div class="empty-state">
            <i class="fas fa-bed"></i>
            <h4>No Rooms Match Your Filter</h4>
            <p class="text-muted">Try adjusting your filters or search terms.</p>
            <a href="browse-rooms.php" class="btn btn-primary mt-3">
                <i class="fas fa-redo me-2"></i>Reset Filters
            </a>
        </div>
    <?php else: ?>
        <div class="rooms-grid">
            <?php foreach ($rooms as $r): 
                $total_capacity = (int)$r['capacity'];
                $occupants = (int)$r['current_occupants'];
                $spaces_left = max(0, $total_capacity - $occupants);
                $fill_pct = $total_capacity > 0 ? ($occupants / $total_capacity) * 100 : 0;
                $fill_color = $fill_pct >= 100 ? 'bg-danger' : ($fill_pct >= 60 ? 'bg-warning' : 'bg-success');

                $has_photo = !empty($r['primary_photo']) && file_exists('../' . $r['primary_photo']);
                $photo_count = (int)($r['photo_count'] ?? 0);

                $is_available = $spaces_left > 0;
                $status_class = $is_available ? 'available' : 'limited';
                $status_text = $is_available ? 'Available' : 'Full';
                if ($is_available && $fill_pct >= 60) {
                    $status_class = 'limited';
                    $status_text = 'Filling Fast';
                }

                // Bed spacing values
                $bed_spacing_raw   = $r['bed_spacing'] ?? 'standard';
                $bed_spacing_label = bedSpacingLabel($bed_spacing_raw);
                $bed_spacing_icon  = bedSpacingIcon($bed_spacing_raw);
            ?>
                <div class="room-card" data-room-id="<?= $r['id'] ?>">

                    <!-- PHOTO BANNER -->
                    <div class="room-photo-banner" data-details-room-id="<?= (int)$r['id'] ?>">
                        <?php if ($has_photo): ?>
                            <img src="../<?= htmlspecialchars($r['primary_photo']) ?>" 
                                 alt="Room <?= htmlspecialchars($r['room_number']) ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="room-photo-placeholder">
                                <i class="fas fa-bed"></i>
                                <span>No Photo Available</span>
                            </div>
                        <?php endif; ?>

                        <!-- Status badge -->
                        <span class="room-photo-status <?= $status_class ?>">
                            <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                            <?= $status_text ?>
                        </span>

                        <!-- Bed spacing badge -->
                        <span class="bed-spacing-photo-badge">
                            <i class="fas <?= $bed_spacing_icon ?>"></i>
                            <?= htmlspecialchars($bed_spacing_label) ?>
                        </span>

                        <!-- Photo count badge -->
                        <?php if ($photo_count > 0): ?>
                            <span class="photo-count-tag">
                                <i class="fas fa-camera"></i>
                                <?= $photo_count ?> <?= $photo_count === 1 ? 'Photo' : 'Photos' ?>
                            </span>
                        <?php endif; ?>

                        <!-- Room number -->
                        <div class="room-number-overlay">
                            <i class="fas fa-door-open"></i>
                            Room <?= htmlspecialchars($r['room_number']) ?>
                        </div>

                        <!-- Price -->
                        <div class="price-overlay">
                            ₦<?= number_format((float)$r['price'], 0) ?>
                        </div>

                        <!-- View Photos button -->
                        <?php if ($photo_count > 0): ?>
                            <button type="button" class="view-photos-btn" data-room-id="<?= (int)$r['id'] ?>">
                                <i class="fas fa-images"></i>
                                View Photos
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- CARD BODY -->
                    <div class="room-card-body">
                        <div class="room-hostel-line">
                            <i class="fas fa-building"></i>
                            <?= htmlspecialchars($r['hostel_name'] ?? 'N/A') ?>
                            <?php if (!empty($r['block'])): ?>
                                <span style="color:#adb5bd;">•</span>
                                Block <?= htmlspecialchars($r['block']) ?>
                            <?php endif; ?>
                        </div>

                        <div class="room-features">
                            <div class="feature-item">
                                <i class="fas fa-users"></i>
                                <span>Capacity: <strong><?= $total_capacity ?></strong></span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-<?= $r['gender'] === 'Male' ? 'mars' : ($r['gender'] === 'Female' ? 'venus' : 'genderless') ?>"></i>
                                <span><strong><?= htmlspecialchars($r['gender']) ?></strong></span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?= htmlspecialchars(substr($r['hostel_location'] ?? 'N/A', 0, 18)) ?></span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-user-check"></i>
                                <span><?= $occupants ?> occupant<?= $occupants != 1 ? 's' : '' ?></span>
                            </div>
                            <div class="feature-item bed-feature">
                                <i class="fas <?= $bed_spacing_icon ?>"></i>
                                <span>Spacing: <strong><?= htmlspecialchars($bed_spacing_label) ?></strong></span>
                            </div>
                            <?php if (!empty($r['room_type'])): ?>
                            <div class="feature-item">
                                <i class="fas fa-home"></i>
                                <span><?= htmlspecialchars($r['room_type']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="capacity-bar">
                            <div class="label">
                                <span><i class="fas fa-user-friends me-1"></i>Occupancy</span>
                                <span><strong><?= $occupants ?></strong> / <?= $total_capacity ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar <?= $fill_color ?>" style="width: <?= $fill_pct ?>%"></div>
                            </div>
                            <small class="text-<?= $spaces_left > 0 ? 'success' : 'danger' ?> fw-bold mt-1 d-block">
                                <?php if ($spaces_left > 0): ?>
                                    <i class="fas fa-check-circle"></i> <?= $spaces_left ?> space(s) remaining
                                <?php else: ?>
                                    <i class="fas fa-times-circle"></i> Room Full
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>

                    <!-- CARD FOOTER -->
                    <div class="room-card-footer">
                        <button type="button" class="btn btn-outline-secondary view-details"
                                data-room-id="<?= (int)$r['id'] ?>">
                            <i class="fas fa-eye"></i> Details
                        </button>
                        <button type="button" class="btn btn-success select-room"
                                data-room-id="<?= (int)$r['id'] ?>"
                                data-price="<?= (float)$r['price'] ?>"
                                data-room-number="<?= htmlspecialchars($r['room_number']) ?>"
                                data-hostel="<?= htmlspecialchars($r['hostel_name'] ?? 'N/A') ?>"
                                data-block="<?= htmlspecialchars($r['block'] ?? '') ?>"
                                <?= $spaces_left <= 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-check-circle"></i> Select
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php endif; // end of existing allocation check ?>
</div>

<footer class="main-footer">
    <strong>Dormitory Management System</strong> &copy; <?= date('Y') ?> | The Polytechnic Ibadan
</footer>

<!-- FIXED PAYMENT PANEL -->
<?php if (!$existing_allocation): ?>
<div class="payment-panel" id="paymentPanel">
    <div class="panel-inner">
        <div class="selected-info">
            <div class="icon-box">
                <i class="fas fa-door-open"></i>
            </div>
            <div class="details">
                <h5 id="selectedRoomTitle">Room —</h5>
                <p id="selectedRoomMeta">Select a room to continue</p>
            </div>
        </div>
        <div class="text-end">
            <p class="price-label">Amount to Pay</p>
            <p class="price-display" id="selectedRoomPrice">₦0.00</p>
        </div>
        <div>
            <button type="button" class="btn btn-success btn-lg" id="proceedPaymentBtn" disabled>
                <i class="fas fa-credit-card me-2"></i>Proceed to Payment
            </button>
            <button type="button" class="btn btn-outline-secondary ms-2" id="cancelSelectionBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ROOM DETAILS MODAL -->
<div class="modal fade" id="roomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #1a3a6b, #0d1b3e); color: white; border-radius: 16px 16px 0 0;">
                <h5 class="modal-title"><i class="fas fa-bed me-2"></i>Room Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="roomModalBody" style="padding: 25px;">
                <!-- Filled by JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="modalSelectBtn">
                    <i class="fas fa-check-circle me-2"></i>Select This Room
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ===== Data passed from PHP =====
const roomImagesMap = <?= json_encode($room_images_map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const roomsDataMap  = <?= json_encode($rooms_data_map,  JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const studentGender = <?= json_encode($student['gender'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const studentEmail  = <?= json_encode($student['email'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const studentName   = <?= json_encode($student['full_name'] ?? 'Student', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const studentMatric = <?= json_encode($student['matric_no'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const studentId     = <?= json_encode((string)$student_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const paystackKey   = <?= json_encode(defined('PAYSTACK_PUBLIC_KEY') ? PAYSTACK_PUBLIC_KEY : '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

let currentGalleryImages = [];
let currentGalleryIndex = 0;
let selectedRoom = null;

// ===== Bed spacing helpers (mirror the PHP ones) =====
function bedSpacingLabelJS(spacing) {
    const s = String(spacing || '').toLowerCase().trim();
    switch (s) {
        case 'single':   return 'Single';
        case 'double':   return 'Double';
        case 'triple':   return 'Triple';
        case 'quad':     return 'Quad';
        case 'bunk':     return 'Bunk Beds';
        case 'spacious': return 'Spacious';
        case 'standard': return 'Standard';
        case 'compact':  return 'Compact';
        default:         return spacing ? (spacing.charAt(0).toUpperCase() + spacing.slice(1)) : 'Standard';
    }
}

function bedSpacingIconJS(spacing) {
    const s = String(spacing || '').toLowerCase().trim();
    switch (s) {
        case 'bunk':     return 'fa-layer-group';
        case 'spacious': return 'fa-arrows-alt';
        case 'compact':  return 'fa-compress';
        default:         return 'fa-bed';
    }
}

// ===== Global helper functions =====
function setMainImage(index) {
    const images = currentGalleryImages || [];
    if (!images[index]) return;

    currentGalleryIndex = index;
    const mainImg = document.getElementById('modalMainImage');
    if (mainImg) mainImg.src = '../' + images[index];

    const counter = document.querySelector('.gallery-counter');
    if (counter) counter.innerHTML = `<i class="fas fa-camera"></i> ${index + 1} / ${images.length}`;

    document.querySelectorAll('.gallery-thumb').forEach((t, i) => {
        t.classList.toggle('active', i === index);
    });
}

function changeMainImage(direction) {
    const images = currentGalleryImages || [];
    if (images.length === 0) return;
    const newIndex = (currentGalleryIndex + direction + images.length) % images.length;
    setMainImage(newIndex);
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', function() {

    // ===== SIDEBAR =====
    const sidebar = document.getElementById('mainSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    if (toggleBtn) toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
    });
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // ===== ELEMENT REFERENCES =====
    const paymentPanel = document.getElementById('paymentPanel');
    const selectedRoomTitle = document.getElementById('selectedRoomTitle');
    const selectedRoomMeta = document.getElementById('selectedRoomMeta');
    const selectedRoomPrice = document.getElementById('selectedRoomPrice');
    const proceedBtn = document.getElementById('proceedPaymentBtn');
    const cancelBtn = document.getElementById('cancelSelectionBtn');
    const roomModalEl = document.getElementById('roomModal');
    const roomModal = roomModalEl ? new bootstrap.Modal(roomModalEl) : null;
    const roomModalBody = document.getElementById('roomModalBody');
    const modalSelectBtn = document.getElementById('modalSelectBtn');

    // ===== SELECT ROOM BUTTONS =====
    document.querySelectorAll('.select-room').forEach(btn => {
        btn.addEventListener('click', function() {
            if (this.disabled) return;

            const roomId = this.dataset.roomId;
            const price = parseFloat(this.dataset.price);
            const roomNumber = this.dataset.roomNumber;
            const hostel = this.dataset.hostel;
            const block = this.dataset.block;

            document.querySelectorAll('.room-card').forEach(c => c.classList.remove('selected'));
            this.closest('.room-card').classList.add('selected');

            selectedRoom = {
                id: roomId,
                price: price,
                room_number: roomNumber,
                hostel: hostel,
                block: block
            };

            if (selectedRoomTitle) selectedRoomTitle.textContent = `Room ${roomNumber}`;
            if (selectedRoomMeta) selectedRoomMeta.textContent = `${hostel}${block ? ' • Block ' + block : ''}`;
            if (selectedRoomPrice) selectedRoomPrice.textContent = '₦' + price.toLocaleString('en-NG', { minimumFractionDigits: 2 });
            if (proceedBtn) proceedBtn.disabled = false;

            if (paymentPanel) {
                paymentPanel.classList.add('show');
                setTimeout(() => {
                    paymentPanel.scrollIntoView({ behavior: 'smooth', block: 'end' });
                }, 100);
            }
        });
    });

    // ===== CANCEL SELECTION =====
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            selectedRoom = null;
            document.querySelectorAll('.room-card').forEach(c => c.classList.remove('selected'));
            if (paymentPanel) paymentPanel.classList.remove('show');
            if (proceedBtn) proceedBtn.disabled = true;
        });
    }

    // ===== VIEW PHOTOS BUTTON =====
    document.querySelectorAll('.view-photos-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            const roomId = this.dataset.roomId;
            const detailsBtn = document.querySelector(`.view-details[data-room-id="${roomId}"]`);
            if (detailsBtn) detailsBtn.click();
        });
    });

    // ===== PHOTO BANNER CLICK =====
    document.querySelectorAll('.room-photo-banner[data-details-room-id]').forEach(banner => {
        banner.addEventListener('click', function() {
            const roomId = this.dataset.detailsRoomId;
            const detailsBtn = document.querySelector(`.view-details[data-room-id="${roomId}"]`);
            if (detailsBtn) detailsBtn.click();
        });
    });

    // ===== DETAILS BUTTON =====
    document.querySelectorAll('.view-details').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const roomId = String(this.dataset.roomId);
            const room = roomsDataMap[roomId];

            if (!room) {
                console.error('Room data not found for id:', roomId);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Could not load room details. Please refresh the page.'
                });
                return;
            }

            const occupants = parseInt(room.current_occupants || 0);
            const capacity = parseInt(room.capacity);
            const spaces = capacity - occupants;
            const fill_pct = capacity > 0 ? (occupants / capacity) * 100 : 0;
            const fill_color = fill_pct >= 100 ? 'danger' : (fill_pct >= 60 ? 'warning' : 'success');

            const bedSpacingRaw = room.bed_spacing || 'standard';
            const bedSpacingLbl = bedSpacingLabelJS(bedSpacingRaw);
            const bedSpacingIco = bedSpacingIconJS(bedSpacingRaw);

            // Build gallery
            const images = roomImagesMap[room.id] || [];
            let galleryHtml = '';
            if (images.length > 0) {
                galleryHtml = `
                    <div class="modal-gallery-section">
                        <div class="modal-gallery-main">
                            <img id="modalMainImage" src="../${images[0]}" alt="Room photo">
                            ${images.length > 1 ? `
                                <button type="button" class="gallery-nav prev" onclick="changeMainImage(-1)">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button type="button" class="gallery-nav next" onclick="changeMainImage(1)">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            ` : ''}
                            <span class="gallery-counter">
                                <i class="fas fa-camera"></i> 1 / ${images.length}
                            </span>
                        </div>
                        ${images.length > 1 ? `
                            <div class="modal-gallery-thumbs">
                                ${images.map((img, i) => `
                                    <img src="../${img}" 
                                         class="gallery-thumb ${i === 0 ? 'active' : ''}"
                                         data-index="${i}"
                                         onclick="setMainImage(${i})">
                                `).join('')}
                            </div>
                        ` : ''}
                    </div>
                `;
            } else {
                galleryHtml = `
                    <div class="modal-gallery-empty">
                        <i class="fas fa-image"></i>
                        <p>No photos available for this room</p>
                    </div>
                `;
            }

            roomModalBody.innerHTML = `
                ${galleryHtml}

                <div class="text-center mb-4 mt-3">
                    <h3 style="font-weight: 800; color: #1a2a4a;">
                        <i class="fas fa-door-open" style="color:#1a3a6b;"></i>
                        Room ${escapeHtml(room.room_number)}
                    </h3>
                    <p class="text-muted mb-0">
                        <i class="fas fa-building me-1"></i>${escapeHtml(room.hostel_name || 'N/A')}
                        ${room.block ? ' • Block ' + escapeHtml(room.block) : ''}
                    </p>
                    <h2 style="color: #28a745; font-weight: 800; margin-top: 10px;">
                        ₦${parseFloat(room.price).toLocaleString('en-NG', { minimumFractionDigits: 2 })}
                    </h2>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="detail-box bed-detail-box">
                            <small class="d-block"><i class="fas ${bedSpacingIco} me-1"></i>Bed Spacing</small>
                            <strong style="font-size:1.1rem;">${escapeHtml(bedSpacingLbl)}</strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-box">
                            <small class="text-muted d-block">Room Type</small>
                            <strong>${escapeHtml(room.room_type || 'Standard')}</strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-box">
                            <small class="text-muted d-block">Gender</small>
                            <strong>${escapeHtml(room.gender)}</strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-box">
                            <small class="text-muted d-block">Total Capacity</small>
                            <strong>${capacity} student(s)</strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-box">
                            <small class="text-muted d-block">Currently Occupied</small>
                            <strong>${occupants} student(s)</strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-box">
                            <small class="text-muted d-block">Available Spaces</small>
                            <strong class="text-${spaces > 0 ? 'success' : 'danger'}">${spaces} space(s)</strong>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="fw-bold text-muted">Occupancy Level</small>
                        <small class="fw-bold">${fill_pct.toFixed(0)}%</small>
                    </div>
                    <div class="progress" style="height: 10px; border-radius: 5px;">
                        <div class="progress-bar bg-${fill_color}" style="width: ${fill_pct}%; border-radius: 5px;"></div>
                    </div>
                </div>

                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    <strong>Note:</strong> This room matches your gender (${escapeHtml(studentGender)}). 
                    Once you complete payment, you will be allocated automatically.
                </div>
            `;

            if (modalSelectBtn) {
                modalSelectBtn.disabled = spaces <= 0;
                modalSelectBtn.innerHTML = spaces > 0 
                    ? '<i class="fas fa-check-circle me-2"></i>Select This Room'
                    : '<i class="fas fa-times-circle me-2"></i>Room Full';
                modalSelectBtn.onclick = function() {
                    if (roomModal) roomModal.hide();
                    setTimeout(() => {
                        const selectBtn = document.querySelector(`.select-room[data-room-id="${room.id}"]`);
                        if (selectBtn && !selectBtn.disabled) {
                            selectBtn.click();
                        }
                    }, 300);
                };
            }

            currentGalleryImages = images;
            currentGalleryIndex = 0;

            if (roomModal) roomModal.show();
        });
    });

    // ===== PROCEED TO PAYMENT =====
    if (proceedBtn) {
        proceedBtn.addEventListener('click', function() {
            if (!selectedRoom) return;

            Swal.fire({
                title: 'Confirm Room Selection',
                html: `
                    <div style="text-align: left; padding: 10px;">
                        <p>You are about to pay for:</p>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin: 15px 0;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>Room:</span><strong>${escapeHtml(selectedRoom.room_number)}</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>Hostel:</span><strong>${escapeHtml(selectedRoom.hostel)}</strong>
                            </div>
                            ${selectedRoom.block ? `
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span>Block:</span><strong>${escapeHtml(selectedRoom.block)}</strong>
                            </div>` : ''}
                            <div style="display: flex; justify-content: space-between; padding-top: 8px; border-top: 1px solid #dee2e6; margin-top: 8px;">
                                <span><strong>Total:</strong></span>
                                <strong style="color: #28a745; font-size: 1.2rem;">₦${selectedRoom.price.toLocaleString('en-NG', { minimumFractionDigits: 2 })}</strong>
                            </div>
                        </div>
                        <p style="color: #856404; font-size: 0.85rem;">
                            <i class="fas fa-info-circle"></i>
                            After payment, this room will be allocated to you automatically.
                        </p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-credit-card"></i> Pay Now',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isConfirmed) {
                    payWithPaystack(selectedRoom);
                }
            });
        });
    }

    // ===== PAYSTACK =====
    function payWithPaystack(room) {
        const amount = Math.round(room.price * 100);

        if (!studentEmail) {
            Swal.fire({
                icon: 'error',
                title: 'Email Required',
                text: 'Please update your profile with a valid email address before making payment.'
            });
            return;
        }

        if (!paystackKey) {
            Swal.fire({
                icon: 'error',
                title: 'Payment Error',
                text: 'Payment gateway is not configured. Please contact the administrator.'
            });
            return;
        }

        const handler = PaystackPop.setup({
            key: paystackKey,
            email: studentEmail,
            amount: amount,
            currency: 'NGN',
            ref: 'ROOM-' + Date.now() + '-' + Math.floor(Math.random() * 10000),
            metadata: {
                custom_fields: [
                    { display_name: 'Student Name', variable_name: 'student_name', value: studentName },
                    { display_name: 'Student ID', variable_name: 'student_id', value: studentId },
                    { display_name: 'Matric No', variable_name: 'matric_no', value: studentMatric },
                    { display_name: 'Room ID', variable_name: 'room_id', value: String(room.id) },
                    { display_name: 'Room Number', variable_name: 'room_number', value: room.room_number },
                    { display_name: 'Hostel', variable_name: 'hostel', value: room.hostel }
                ]
            },
            callback: function(response) {
                window.location.href = 'room-payment-callback.php?reference=' + encodeURIComponent(response.reference) + '&room_id=' + encodeURIComponent(room.id);
            },
            onClose: function() {
                Swal.fire({
                    icon: 'info',
                    title: 'Payment Cancelled',
                    text: 'You closed the payment window. You can try again anytime.',
                    confirmButtonColor: '#1a3a6b'
                });
            }
        });
        handler.openIframe();
    }
});
</script>
</body>
</html>