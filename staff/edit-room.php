<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: login.php"); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: manage-rooms.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hostel_id   = (int)$_POST['hostel_id'];
    $room_number = trim($_POST['room_number']);
    $block       = trim($_POST['block']);
    $capacity    = (int)$_POST['capacity'];
    $price       = (float)$_POST['price'];
    $gender      = $_POST['gender'];
    $bed_spacing = $_POST['bed_spacing'];
    $room_type   = trim($_POST['room_type']);
    $status      = $_POST['status'];

    $rn = $conn->real_escape_string($room_number);
    $bl = $conn->real_escape_string($block);
    $bs = $conn->real_escape_string($bed_spacing);
    $rt = $conn->real_escape_string($room_type);
    $gn = $conn->real_escape_string($gender);
    $st = $conn->real_escape_string($status);

    $stmt = $conn->prepare("
        UPDATE rooms SET hostel_id=?, room_number=?, block=?, capacity=?, price=?, 
                         gender=?, bed_spacing=?, room_type=?, status=?
        WHERE id=?
    ");
    $stmt->bind_param("issidssssi", $hostel_id, $rn, $bl, $capacity, $price, $gn, $bs, $rt, $st, $id);
    if ($stmt->execute()) {
        $_SESSION['flash_success'] = "Room updated successfully.";
        header("Location: manage-rooms.php");
        exit;
    } else {
        $_SESSION['flash_error'] = "Error: " . $conn->error;
    }
}

$room = $conn->query("SELECT * FROM rooms WHERE id = $id LIMIT 1")->fetch_assoc();
if (!$room) { $_SESSION['flash_error'] = "Room not found."; header("Location: manage-rooms.php"); exit; }

$page_title = 'Edit Room ' . $room['room_number'];
$page_icon = 'fa-edit';
$hostels = $conn->query("SELECT id, name FROM hostels WHERE status='active' ORDER BY name");

include 'includes/header.php';
?>

<div class="card" style="max-width:900px;">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-edit"></i>Edit Room: <?= htmlspecialchars($room['room_number']) ?></h3>
        <a href="manage-rooms.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Hostel *</label>
                    <select name="hostel_id" class="form-select" required>
                        <?php while ($h = $hostels->fetch_assoc()): ?>
                            <option value="<?= $h['id'] ?>" <?= $room['hostel_id'] == $h['id'] ? 'selected' : '' ?>><?= htmlspecialchars($h['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Room Number *</label>
                    <input type="text" name="room_number" class="form-control" value="<?= htmlspecialchars($room['room_number']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Block</label>
                    <input type="text" name="block" class="form-control" value="<?= htmlspecialchars($room['block'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Room Type</label>
                    <input type="text" name="room_type" class="form-control" value="<?= htmlspecialchars($room['room_type'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Capacity *</label>
                    <input type="number" name="capacity" class="form-control" min="1" max="20" value="<?= (int)$room['capacity'] ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Price (₦) *</label>
                    <input type="number" name="price" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($room['price']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <?php foreach (['Male','Female','Mixed'] as $g): ?>
                            <option value="<?= $g ?>" <?= $room['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Bed Spacing</label>
                    <select name="bed_spacing" class="form-select">
                        <?php foreach (['standard','single','double','triple','quad','bunk','spacious','compact'] as $sp): ?>
                            <option value="<?= $sp ?>" <?= ($room['bed_spacing'] ?? '') === $sp ? 'selected' : '' ?>><?= ucfirst($sp) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['available','occupied','maintenance'] as $st): ?>
                            <option value="<?= $st ?>" <?= $room['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Update Room</button>
                <a href="manage-rooms.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>