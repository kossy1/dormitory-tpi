<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: login.php"); exit; }

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $gender = $_POST['gender'] ?? 'Mixed';
    $status = $_POST['status'] ?? 'active';

    if (empty($name)) {
        $_SESSION['flash_error'] = "Hostel name is required.";
    } else {
        $name_e = $conn->real_escape_string($name);
        $loc_e  = $conn->real_escape_string($location);
        $gen_e  = $conn->real_escape_string($gender);
        $stat_e = $conn->real_escape_string($status);

        if ($id > 0) {
            $conn->query("UPDATE hostels SET name='$name_e', location='$loc_e', gender='$gen_e', status='$stat_e' WHERE id=$id");
            $_SESSION['flash_success'] = "Hostel updated successfully.";
        } else {
            $conn->query("INSERT INTO hostels (name, location, gender, status) VALUES ('$name_e', '$loc_e', '$gen_e', '$stat_e')");
            $_SESSION['flash_success'] = "Hostel added successfully.";
        }
    }
    header("Location: manage-hostels.php");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $hasRooms = (int)$conn->query("SELECT COUNT(*) c FROM rooms WHERE hostel_id=$id")->fetch_assoc()['c'];
    if ($hasRooms > 0) {
        $_SESSION['flash_error'] = "Cannot delete: this hostel has $hasRooms room(s).";
    } else {
        $conn->query("DELETE FROM hostels WHERE id=$id");
        $_SESSION['flash_success'] = "Hostel deleted.";
    }
    header("Location: manage-hostels.php");
    exit;
}

$page_title = 'Manage Hostels';
$page_icon = 'fa-hotel';

$hostels = $conn->query("
    SELECT h.*, 
           (SELECT COUNT(*) FROM rooms WHERE hostel_id = h.id) AS room_count,
           (SELECT COALESCE(SUM(capacity),0) FROM rooms WHERE hostel_id = h.id) AS total_capacity
    FROM hostels h
    ORDER BY h.name
");

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-hotel"></i>All Hostels</h3>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#hostelModal" onclick="openAdd()">
            <i class="fas fa-plus me-1"></i> Add Hostel
        </button>
    </div>
    <div class="card-body p-0">
        <?php if ($hostels && $hostels->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Gender</th>
                        <th>Rooms</th>
                        <th>Bed Spaces</th>
                        <th>Status</th>
                        <th style="width:150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; while ($h = $hostels->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong style="color:#1a2a4a;"><?= htmlspecialchars($h['name']) ?></strong></td>
                        <td><?= htmlspecialchars($h['location'] ?: '—') ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($h['gender']) ?></span></td>
                        <td><span class="badge bg-info"><?= (int)$h['room_count'] ?></span></td>
                        <td><?= (int)$h['total_capacity'] ?></td>
                        <td>
                            <span class="badge bg-<?= $h['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($h['status']) ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary" onclick='openEdit(<?= json_encode($h) ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="confirmDelete('?delete=<?= $h['id'] ?>', 'Delete hostel <?= htmlspecialchars(addslashes($h['name'])) ?>?')">
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
                <i class="fas fa-hotel" style="font-size:4rem;color:#d1d5db;"></i>
                <h5 class="mt-3" style="color:#1a2a4a;">No hostels yet</h5>
                <p class="text-muted">Add your first hostel to get started.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#hostelModal" onclick="openAdd()">
                    <i class="fas fa-plus me-1"></i> Add Hostel
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="hostelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <form method="POST">
                <div class="modal-header" style="background:linear-gradient(135deg,#1a3a6b,#0d1b3e);color:white;border-radius:16px 16px 0 0;">
                    <h5 class="modal-title" id="modalTitle"><i class="fas fa-plus me-2"></i>Add Hostel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="hostelId">
                    <div class="mb-3">
                        <label class="form-label">Hostel Name *</label>
                        <input type="text" name="name" id="hostelName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" id="hostelLocation" class="form-control" placeholder="e.g. North Campus">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" id="hostelGender" class="form-select">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Mixed">Mixed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="hostelStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAdd() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Add Hostel';
    document.getElementById('hostelId').value = '';
    document.getElementById('hostelName').value = '';
    document.getElementById('hostelLocation').value = '';
    document.getElementById('hostelGender').value = 'Mixed';
    document.getElementById('hostelStatus').value = 'active';
}
function openEdit(h) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Hostel';
    document.getElementById('hostelId').value = h.id;
    document.getElementById('hostelName').value = h.name;
    document.getElementById('hostelLocation').value = h.location || '';
    document.getElementById('hostelGender').value = h.gender || 'Mixed';
    document.getElementById('hostelStatus').value = h.status || 'active';
    new bootstrap.Modal(document.getElementById('hostelModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>