<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
include '../config/db.php';

$room_id = (int)($_GET['id'] ?? 0);
$success = $error = '';

if ($room_id <= 0) { header("Location: rooms.php"); exit; }

// Fetch room
$room = $conn->query("
    SELECT r.*, h.name AS hostel_name
    FROM rooms r 
    LEFT JOIN hostels h ON r.hostel_id = h.id 
    WHERE r.id = $room_id
")->fetch_assoc();
if (!$room) { header("Location: rooms.php"); exit; }

// ===== HANDLE DELETE IMAGE =====
if (isset($_GET['delete_image']) && is_numeric($_GET['delete_image'])) {
    $img_id = (int)$_GET['delete_image'];
    $img = $conn->query("SELECT * FROM room_images WHERE id = $img_id AND room_id = $room_id")->fetch_assoc();
    if ($img) {
        @unlink('../' . $img['image_path']);
        $conn->query("DELETE FROM room_images WHERE id = $img_id");
        // If deleted was primary, set first remaining as primary
        if ($img['is_primary']) {
            $first = $conn->query("SELECT id FROM room_images WHERE room_id = $room_id ORDER BY sort_order ASC LIMIT 1")->fetch_assoc();
            if ($first) $conn->query("UPDATE room_images SET is_primary = 1 WHERE id = {$first['id']}");
        }
        $success = "Image deleted successfully.";
    }
}

// ===== HANDLE SET PRIMARY =====
if (isset($_GET['set_primary']) && is_numeric($_GET['set_primary'])) {
    $img_id = (int)$_GET['set_primary'];
    $conn->query("UPDATE room_images SET is_primary = 0 WHERE room_id = $room_id");
    $conn->query("UPDATE room_images SET is_primary = 1 WHERE id = $img_id AND room_id = $room_id");
    $success = "Primary image updated.";
}

// ===== HANDLE UPDATE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_room'])) {
    $room_number = strtoupper(trim($_POST['room_number'] ?? ''));
    $block       = trim($_POST['block'] ?? '');
    $capacity    = (int)($_POST['capacity'] ?? 0);
    $gender      = $_POST['gender'] ?? '';
    $price       = (float)($_POST['price'] ?? 0);
    $status      = $_POST['status'] ?? 'available';
    $description = trim($_POST['description'] ?? '');

    if (empty($room_number) || $capacity < 1 || $price <= 0) {
        $error = "Please fill in all required fields correctly.";
    } else {
        $stmt = $conn->prepare("
            UPDATE rooms SET room_number = ?, block = ?, capacity = ?, gender = ?, price = ?, status = ?, description = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssisissi", $room_number, $block, $capacity, $gender, $price, $status, $description, $room_id);

        if ($stmt->execute()) {
            // Handle new image uploads
            $uploaded = 0;
            if (!empty($_FILES['new_photos']['name'][0])) {
                $upload_dir = '../uploads/rooms/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);

                $max_existing = $conn->query("SELECT COALESCE(MAX(sort_order), -1) FROM room_images WHERE room_id = $room_id")->fetch_row()[0];
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $count = count($_FILES['new_photos']['name']);

                for ($i = 0; $i < $count; $i++) {
                    if (empty($_FILES['new_photos']['name'][$i])) continue;
                    $ext = strtolower(pathinfo($_FILES['new_photos']['name'][$i], PATHINFO_EXTENSION));
                    $size = $_FILES['new_photos']['size'][$i];

                    if (!in_array($ext, $allowed) || $size > 3 * 1024 * 1024) continue;

                    $filename = 'room_' . $room_id . '_' . time() . '_' . $i . '.' . $ext;
                    if (move_uploaded_file($_FILES['new_photos']['tmp_name'][$i], $upload_dir . $filename)) {
                        $rel_path = 'uploads/rooms/' . $filename;
                        $is_primary = ($max_existing == -1 && $i == 0) ? 1 : 0;
                        $sort = $max_existing + $i + 1;

                        $img_stmt = $conn->prepare("INSERT INTO room_images (room_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)");
                        $img_stmt->bind_param("isii", $room_id, $rel_path, $is_primary, $sort);
                        if ($img_stmt->execute()) $uploaded++;
                    }
                }
            }

            $success = "Room updated!" . ($uploaded > 0 ? " $uploaded new photo(s) added." : "");

            // Refresh
            $room = $conn->query("SELECT r.*, h.name AS hostel_name FROM rooms r LEFT JOIN hostels h ON r.hostel_id = h.id WHERE r.id = $room_id")->fetch_assoc();
        } else {
            $error = "Failed to update: " . $stmt->error;
        }
    }
}

// Fetch images
$images = [];
$res = $conn->query("SELECT * FROM room_images WHERE room_id = $room_id ORDER BY is_primary DESC, sort_order ASC");
if ($res) $images = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Room - Dormitory MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; overflow-x: hidden; padding: 20px; }
        .container-main { max-width: 1200px; margin: 0 auto; }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .card-header { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); color: white; border-radius: 16px 16px 0 0 !important; padding: 18px 25px; border: none; }
        .card-header h3 { color: white; margin: 0; font-size: 1.15rem; font-weight: 700; }
        .card-body { padding: 30px; }
        .form-label { font-weight: 600; font-size: 0.9rem; color: #1a2a4a; margin-bottom: 8px; }
        .form-control, .form-select { border: 2px solid #e9ecef; border-radius: 10px; padding: 10px 16px; font-size: 0.95rem; background: #fafbfc; }
        .form-control:focus, .form-select:focus { border-color: #1a3a6b; box-shadow: 0 0 0 4px rgba(26,58,107,0.12); background: white; }
        .btn-primary { background: linear-gradient(135deg, #1a3a6b, #0d1b3e); border: none; border-radius: 12px; padding: 12px 28px; font-weight: 700; color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(26,58,107,0.4); color: white; }

        /* Image gallery */
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        .image-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            aspect-ratio: 4/3;
            border: 2px solid #e9ecef;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        .image-item:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.12); border-color: #1a3a6b; }
        .image-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .image-item .image-actions {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            padding: 20px 8px 8px;
            display: flex;
            gap: 6px;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .image-item:hover .image-actions { opacity: 1; }
        .image-item .image-actions a {
            background: white;
            color: #333;
            border: none;
            border-radius: 6px;
            padding: 5px 10px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .image-item .image-actions a:hover { transform: scale(1.05); }
        .image-item .image-actions .set-primary-btn { color: #28a745; }
        .image-item .image-actions .delete-btn { color: #dc3545; }
        .image-item .primary-badge {
            position: absolute;
            top: 8px; left: 8px;
            background: rgba(40, 167, 69, 0.95);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .image-item .primary-badge::before {
            content: '\f005';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            margin-right: 4px;
        }

        .upload-more-area {
            border: 3px dashed #cbd5e1;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafbfc;
            margin-top: 20px;
        }
        .upload-more-area:hover { border-color: #1a3a6b; background: #f0f4fa; }
        .upload-more-area i { font-size: 2.5rem; color: #1a3a6b; display: block; margin-bottom: 10px; }
        .upload-more-area input[type=file] { display: none; }
        .upload-more-area p { margin: 0; color: #6c757d; font-size: 0.9rem; }

        .new-previews { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; margin-top: 15px; }
        .new-preview-item { position: relative; border-radius: 10px; overflow: hidden; aspect-ratio: 1; border: 2px solid #28a745; }
        .new-preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .new-preview-item .remove-new {
            position: absolute; top: 4px; right: 4px;
            background: rgba(220,53,69,0.9); color: white;
            border: none; border-radius: 50%; width: 22px; height: 22px;
            font-size: 0.7rem; cursor: pointer;
        }

        .alert { border-radius: 12px; border: none; padding: 16px 22px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
        .alert-danger { background: #fde8e8; color: #721c24; border-left: 5px solid #dc3545; }

        @media (max-width: 576px) {
            body { padding: 10px; }
            .card-body { padding: 20px 16px; }
            .image-gallery { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .btn-primary, .btn-outline-secondary { width: 100%; }
        }
    </style>
</head>
<body>

<div class="container-main">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2><i class="fas fa-edit me-2"></i>Edit Room <?= htmlspecialchars($room['room_number']) ?></h2>
        <a href="rooms.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Rooms
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ROOM DETAILS FORM -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-info-circle me-2"></i>Room Details</h3>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Room Number <span class="text-danger">*</span></label>
                        <input type="text" name="room_number" class="form-control" required
                               value="<?= htmlspecialchars($room['room_number']) ?>"
                               style="text-transform:uppercase;">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Block / Wing</label>
                        <input type="text" name="block" class="form-control"
                               value="<?= htmlspecialchars($room['block'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Hostel</label>
                        <input type="text" class="form-control"
                               value="<?= htmlspecialchars($room['hostel_name'] ?? 'N/A') ?>" disabled>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Capacity <span class="text-danger">*</span></label>
                        <input type="number" name="capacity" class="form-control" required min="1" max="10"
                               value="<?= (int)$room['capacity'] ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gender <span class="text-danger">*</span></label>
                        <select name="gender" class="form-select" required>
                            <option value="Male" <?= $room['gender']=='Male'?'selected':'' ?>>Male Only</option>
                            <option value="Female" <?= $room['gender']=='Female'?'selected':'' ?>>Female Only</option>
                            <option value="Mixed" <?= $room['gender']=='Mixed'?'selected':'' ?>>Mixed</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Price (₦) <span class="text-danger">*</span></label>
                        <input type="number" name="price" class="form-control" required step="0.01" min="1"
                               value="<?= htmlspecialchars($room['price']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="available" <?= $room['status']=='available'?'selected':'' ?>>Available</option>
                            <option value="occupied" <?= $room['status']=='occupied'?'selected':'' ?>>Occupied</option>
                            <option value="maintenance" <?= $room['status']=='maintenance'?'selected':'' ?>>Maintenance</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($room['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- IMAGE UPLOAD -->
                <div class="mt-4">
                    <h5 class="mb-3" style="color:#1a2a4a;font-weight:700;">
                        <i class="fas fa-images" style="color:#0d6efd;"></i> Room Photos (<?= count($images) ?>)
                    </h5>

                    <?php if (empty($images)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>No photos yet. Upload some below.
                        </div>
                    <?php else: ?>
                        <div class="image-gallery">
                            <?php foreach ($images as $img): ?>
                                <div class="image-item">
                                    <img src="../<?= htmlspecialchars($img['image_path']) ?>" alt="Room photo">
                                    <?php if ($img['is_primary']): ?>
                                        <span class="primary-badge">Primary</span>
                                    <?php endif; ?>
                                    <div class="image-actions">
                                        <?php if (!$img['is_primary']): ?>
                                            <a href="?id=<?= $room_id ?>&set_primary=<?= $img['id'] ?>"
                                               class="set-primary-btn"
                                               onclick="return confirm('Set as primary photo?');">
                                                <i class="fas fa-star"></i> Primary
                                            </a>
                                        <?php endif; ?>
                                        <a href="?id=<?= $room_id ?>&delete_image=<?= $img['id'] ?>"
                                           class="delete-btn"
                                           onclick="return confirm('Delete this photo?');">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- ADD MORE -->
                    <div class="upload-more-area" onclick="document.getElementById('newPhotos').click()">
                        <input type="file" name="new_photos[]" id="newPhotos" accept="image/jpeg,image/png,image/webp" multiple>
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p><strong>Click to add more photos</strong> (JPG, PNG, WEBP, max 3MB each)</p>
                    </div>

                    <div class="new-previews" id="newPreviews"></div>
                </div>

                <div class="mt-4 pt-3 d-flex flex-wrap gap-2" style="border-top:2px dashed #e9ecef;">
                    <button type="submit" name="update_room" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                    <a href="rooms.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const newPhotos = document.getElementById('newPhotos');
    const previews = document.getElementById('newPreviews');
    let newFiles = [];

    newPhotos.addEventListener('change', function() {
        const files = Array.from(this.files);
        for (const file of files) {
            if (file.size > 3 * 1024 * 1024) continue;
            newFiles.push(file);
        }
        this.value = '';
        renderPreviews();
    });

    function renderPreviews() {
        previews.innerHTML = '';
        newFiles.forEach((file, idx) => {
            const url = URL.createObjectURL(file);
            const div = document.createElement('div');
            div.className = 'new-preview-item';
            div.innerHTML = `
                <img src="${url}">
                <button type="button" class="remove-new" data-idx="${idx}"><i class="fas fa-times"></i></button>
            `;
            previews.appendChild(div);
        });
        previews.querySelectorAll('.remove-new').forEach(btn => {
            btn.addEventListener('click', function() {
                newFiles.splice(parseInt(this.dataset.idx), 1);
                renderPreviews();
            });
        });
    }

    // Sync on submit
    document.querySelector('form').addEventListener('submit', function() {
        const dt = new DataTransfer();
        newFiles.forEach(f => dt.items.add(f));
        newPhotos.files = dt.files;
    });
});
</script>
</body>
</html>