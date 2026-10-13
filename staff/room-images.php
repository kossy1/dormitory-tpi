<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: login.php"); exit; }

$room_id = (int)($_GET['room_id'] ?? 0);
if ($room_id <= 0) { header("Location: manage-rooms.php"); exit; }

// Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {
    $upload_dir = '../uploads/rooms/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $files = $_FILES['images'];
    $count = count($files['name']);
    $uploaded = 0;

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;

        $filename = 'room_' . $room_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $upload_dir . $filename;

        if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
            $db_path = 'uploads/rooms/' . $filename;
            $is_primary = (int)($_POST['set_primary'][$i] ?? 0);
            $conn->query("INSERT INTO room_images (room_id, image_path, is_primary, sort_order) 
                          VALUES ($room_id, '$db_path', $is_primary, $i)");
            $uploaded++;
        }
    }

    $_SESSION['flash_success'] = "$uploaded image(s) uploaded successfully.";
    header("Location: room-images.php?room_id=$room_id");
    exit;
}

// Delete
if (isset($_GET['delete'])) {
    $img_id = (int)$_GET['delete'];
    $img = $conn->query("SELECT * FROM room_images WHERE id=$img_id AND room_id=$room_id")->fetch_assoc();
    if ($img) {
        @unlink('../' . $img['image_path']);
        $conn->query("DELETE FROM room_images WHERE id=$img_id");
        $_SESSION['flash_success'] = "Image deleted.";
    }
    header("Location: room-images.php?room_id=$room_id");
    exit;
}

// Set primary
if (isset($_GET['primary'])) {
    $img_id = (int)$_GET['primary'];
    $conn->query("UPDATE room_images SET is_primary=0 WHERE room_id=$room_id");
    $conn->query("UPDATE room_images SET is_primary=1 WHERE id=$img_id AND room_id=$room_id");
    $_SESSION['flash_success'] = "Primary image updated.";
    header("Location: room-images.php?room_id=$room_id");
    exit;
}

$room = $conn->query("
    SELECT r.*, h.name AS hostel_name 
    FROM rooms r LEFT JOIN hostels h ON r.hostel_id=h.id 
    WHERE r.id=$room_id
")->fetch_assoc();
if (!$room) { $_SESSION['flash_error'] = "Room not found."; header("Location: manage-rooms.php"); exit; }

$images = $conn->query("SELECT * FROM room_images WHERE room_id=$room_id ORDER BY is_primary DESC, sort_order ASC");

$page_title = 'Room Images: ' . $room['room_number'];
$page_icon = 'fa-images';

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-images"></i>Images for Room <?= htmlspecialchars($room['room_number']) ?> (<?= htmlspecialchars($room['hostel_name'] ?? '') ?>)</h3>
        <a href="manage-rooms.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Select Image(s) — jpg, png, webp, gif</label>
                    <input type="file" name="images[]" class="form-control" accept="image/*" multiple required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100"><i class="fas fa-upload me-1"></i> Upload</button>
                </div>
            </div>
        </form>

        <?php if ($images && $images->num_rows > 0): ?>
            <div class="row g-3">
                <?php while ($img = $images->fetch_assoc()): 
                    $src = '../' . htmlspecialchars($img['image_path']);
                ?>
                <div class="col-lg-3 col-md-4 col-6">
                    <div style="border:2px solid <?= $img['is_primary'] ? '#28a745' : '#e9ecef' ?>;border-radius:12px;overflow:hidden;position:relative;">
                        <img src="<?= $src ?>" style="width:100%;height:160px;object-fit:cover;display:block;">
                        <?php if ($img['is_primary']): ?>
                            <span class="badge bg-success" style="position:absolute;top:8px;left:8px;">
                                <i class="fas fa-star me-1"></i>Primary
                            </span>
                        <?php endif; ?>
                        <div style="padding:8px;background:#f8f9fa;display:flex;gap:6px;">
                            <?php if (!$img['is_primary']): ?>
                                <a href="?room_id=<?= $room_id ?>&primary=<?= $img['id'] ?>" class="btn btn-sm btn-outline-secondary flex-fill" title="Set primary">
                                    <i class="fas fa-star"></i>
                                </a>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-danger flex-fill" onclick="confirmDelete('?room_id=<?= $room_id ?>&delete=<?= $img['id'] ?>', 'Delete this image?')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-image" style="font-size:4rem;color:#d1d5db;"></i>
                <h5 class="mt-3" style="color:#1a2a4a;">No images yet</h5>
                <p class="text-muted">Upload your first image above.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>