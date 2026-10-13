<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: login.php"); exit; }

$page_title = 'Add New Room';
$page_icon = 'fa-plus-circle';

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

    if ($hostel_id <= 0 || empty($room_number) || $capacity <= 0) {
        $_SESSION['flash_error'] = "Please fill all required fields.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO rooms (hostel_id, room_number, block, capacity, price, gender, bed_spacing, room_type, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issidssss", $hostel_id, $rn, $bl, $capacity, $price, $gn, $bs, $rt, $st);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Room $room_number added successfully.";
            header("Location: manage-rooms.php");
            exit;
        } else {
            $_SESSION['flash_error'] = "Error: " . $conn->error;
        }
    }
}

$hostels = $conn->query("SELECT id, name FROM hostels WHERE status='active' ORDER BY name");

include 'includes/header.php';
?>

<div class="card" style="max-width:900px;">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-plus-circle"></i>New Room Details</h3>
        <a href="manage-rooms.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Hostel *</label>
                    <select name="hostel_id" class="form-select" required>
                        <option value="">-- Select Hostel --</option>
                        <?php while ($h = $hostels->fetch_assoc()): ?>
                            <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Room Number *</label>
                    <input type="text" name="room_number" class="form-control" placeholder="e.g. A101" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Block</label>
                    <input type="text" name="block" class="form-control" placeholder="e.g. A">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Room Type</label>
                    <input type="text" name="room_type" class="form-control" placeholder="e.g. Double Room">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Capacity (Beds) *</label>
                    <input type="number" name="capacity" class="form-control" min="1" max="20" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Price (₦) *</label>
                    <input type="number" name="price" class="form-control" step="0.01" min="0" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Mixed">Mixed</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Bed Spacing</label>
                    <select name="bed_spacing" class="form-select">
                        <option value="standard">Standard</option>
                        <option value="single">Single</option>
                        <option value="double">Double</option>
                        <option value="triple">Triple</option>
                        <option value="quad">Quad</option>
                        <option value="bunk">Bunk Beds</option>
                        <option value="spacious">Spacious</option>
                        <option value="compact">Compact</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Save Room</button>
                <a href="manage-rooms.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>