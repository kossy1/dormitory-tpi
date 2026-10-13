<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: login.php"); exit; }

$page_title = 'Manage Rooms';
$page_icon = 'fa-bed';

// Filters
$filter_hostel = (int)($_GET['hostel'] ?? 0);
$filter_status = $_GET['status'] ?? '';
$filter_spacing = $_GET['spacing'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = ["1=1"];
if ($filter_hostel > 0) $where[] = "r.hostel_id = $filter_hostel";
if (in_array($filter_status, ['available','occupied','maintenance'])) $where[] = "r.status = '$filter_status'";
if ($filter_spacing) {
    $bs = $conn->real_escape_string($filter_spacing);
    $where[] = "r.bed_spacing = '$bs'";
}
if ($search) {
    $s = $conn->real_escape_string($search);
    $where[] = "(r.room_number LIKE '%$s%' OR h.name LIKE '%$s%')";
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

$rooms = $conn->query("
    SELECT r.*, h.name AS hostel_name,
           (SELECT COUNT(*) FROM allocations a WHERE a.room_id=r.id AND a.status='active') AS occupants,
           (SELECT COUNT(*) FROM room_images WHERE room_id=r.id) AS photo_count
    FROM rooms r
    LEFT JOIN hostels h ON r.hostel_id = h.id
    $where_sql
    ORDER BY h.name, r.room_number
");

$hostels = $conn->query("SELECT id, name FROM hostels WHERE status='active' ORDER BY name");

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-bed"></i>All Rooms (<?= $rooms ? $rooms->num_rows : 0 ?>)</h3>
        <a href="add-room.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add Room</a>
    </div>

    <div class="card-body" style="border-bottom:2px solid #f0f2f5;">
        <form method="GET" class="row g-2">
            <div class="col-lg-3 col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Search room or hostel..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-lg-2 col-md-6">
                <select name="hostel" class="form-select">
                    <option value="">All Hostels</option>
                    <?php while ($h = $hostels->fetch_assoc()): ?>
                        <option value="<?= $h['id'] ?>" <?= $filter_hostel == $h['id'] ? 'selected' : '' ?>><?= htmlspecialchars($h['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <select name="status" class="form-select">
                    <option value="">Any Status</option>
                    <option value="available" <?= $filter_status==='available'?'selected':'' ?>>Available</option>
                    <option value="occupied" <?= $filter_status==='occupied'?'selected':'' ?>>Occupied</option>
                    <option value="maintenance" <?= $filter_status==='maintenance'?'selected':'' ?>>Maintenance</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <select name="spacing" class="form-select">
                    <option value="">Any Spacing</option>
                    <?php foreach (['single','double','triple','quad','bunk','spacious','compact','standard'] as $sp): ?>
                        <option value="<?= $sp ?>" <?= $filter_spacing===$sp?'selected':'' ?>><?= ucfirst($sp) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-1 col-md-2">
                <button class="btn btn-primary w-100"><i class="fas fa-filter"></i></button>
            </div>
            <div class="col-lg-2 col-md-2">
                <a href="manage-rooms.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if ($rooms && $rooms->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Room</th>
                        <th>Hostel</th>
                        <th>Capacity</th>
                        <th>Occupancy</th>
                        <th>Bed Spacing</th>
                        <th>Price</th>
                        <th>Gender</th>
                        <th>Photos</th>
                        <th>Status</th>
                        <th style="width:180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($r = $rooms->fetch_assoc()): 
                        $occ = (int)$r['occupants'];
                        $cap = (int)$r['capacity'];
                        $pct = $cap > 0 ? ($occ / $cap) * 100 : 0;
                        $color = $pct >= 100 ? 'danger' : ($pct >= 60 ? 'warning' : 'success');
                    ?>
                    <tr>
                        <td><strong style="color:#1a2a4a;"><?= htmlspecialchars($r['room_number']) ?></strong>
                            <?php if ($r['block']): ?><div style="font-size:0.75rem;color:#6c757d;">Block <?= htmlspecialchars($r['block']) ?></div><?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['hostel_name'] ?? '—') ?></td>
                        <td><span class="badge bg-secondary"><?= $cap ?> beds</span></td>
                        <td>
                            <div style="font-size:0.82rem;font-weight:700;color:#1a2a4a;"><?= $occ ?> / <?= $cap ?></div>
                            <div class="progress" style="height:5px;width:80px;border-radius:3px;background:#e9ecef;">
                                <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
                            </div>
                        </td>
                        <td><span class="badge" style="background:#fff4cc;color:#856404;"><i class="fas fa-bed me-1"></i><?= htmlspecialchars(ucfirst($r['bed_spacing'] ?? 'Standard')) ?></span></td>
                        <td><strong style="color:#28a745;">₦<?= number_format((float)$r['price'], 0) ?></strong></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($r['gender']) ?></span></td>
                        <td>
                            <a href="room-images.php?room_id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-camera"></i> <?= (int)$r['photo_count'] ?>
                            </a>
                        </td>
                        <td>
                            <?php 
                            $sb = ['available'=>'success','occupied'=>'danger','maintenance'=>'warning'][$r['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $sb ?>"><?= ucfirst($r['status']) ?></span>
                        </td>
                        <td>
                            <a href="edit-room.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                            <a href="room-images.php?room_id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-images"></i></a>
                            <button class="btn btn-sm btn-danger" onclick="confirmDelete('delete-room.php?id=<?= $r['id'] ?>', 'Delete room <?= htmlspecialchars(addslashes($r['room_number'])) ?>?')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-bed" style="font-size:4rem;color:#d1d5db;"></i>
                <h5 class="mt-3" style="color:#1a2a4a;">No rooms found</h5>
                <p class="text-muted">Try adjusting filters or add a new room.</p>
                <a href="add-room.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add Room</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>