<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: login.php"); exit; }

// Only admin can manage staff
if (($_SESSION['staff_role'] ?? 'staff') !== 'admin') {
    $_SESSION['flash_error'] = "Access denied. Only admins can manage staff.";
    header("Location: dashboard.php");
    exit;
}

// ===== HANDLE ADD =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $phone     = trim($_POST['phone']);
    $password  = $_POST['password'];
    $role      = $_POST['role'];
    $hostel_id = !empty($_POST['hostel_id']) ? (int)$_POST['hostel_id'] : null;

    if (strlen($password) < 6) {
        $_SESSION['flash_error'] = "Password must be at least 6 characters.";
    } else {
        // Check duplicate email
        $stmt = $conn->prepare("SELECT id FROM staff WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $_SESSION['flash_error'] = "Email already in use.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO staff (full_name, email, phone, password, role, hostel_id, status)
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->bind_param("sssssi", $full_name, $email, $phone, $hash, $role, $hostel_id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = "Staff account created successfully.";
            } else {
                $_SESSION['flash_error'] = "Error: " . $conn->error;
            }
        }
    }
    header("Location: manage-staff.php");
    exit;
}

// ===== HANDLE EDIT =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_staff'])) {
    $id        = (int)$_POST['id'];
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $phone     = trim($_POST['phone']);
    $role      = $_POST['role'];
    $hostel_id = !empty($_POST['hostel_id']) ? (int)$_POST['hostel_id'] : null;
    $status    = $_POST['status'];
    $new_pass  = $_POST['new_password'] ?? '';

    $fn = $conn->real_escape_string($full_name);
    $em = $conn->real_escape_string($email);
    $ph = $conn->real_escape_string($phone);
    $rl = $conn->real_escape_string($role);
    $st = $conn->real_escape_string($status);
    $hid = $hostel_id ? (int)$hostel_id : 'NULL';

    if (!empty($new_pass)) {
        if (strlen($new_pass) < 6) {
            $_SESSION['flash_error'] = "New password must be at least 6 characters.";
            header("Location: manage-staff.php");
            exit;
        }
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $conn->query("UPDATE staff SET full_name='$fn', email='$em', phone='$ph', role='$rl', hostel_id=$hid, status='$st', password='$hash' WHERE id=$id");
    } else {
        $conn->query("UPDATE staff SET full_name='$fn', email='$em', phone='$ph', role='$rl', hostel_id=$hid, status='$st' WHERE id=$id");
    }
    $_SESSION['flash_success'] = "Staff updated successfully.";
    header("Location: manage-staff.php");
    exit;
}

// ===== HANDLE TOGGLE STATUS =====
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $s = $conn->query("SELECT status FROM staff WHERE id=$id")->fetch_assoc();
    if ($s) {
        $new = $s['status'] === 'active' ? 'inactive' : 'active';
        $conn->query("UPDATE staff SET status='$new' WHERE id=$id");
        $_SESSION['flash_success'] = "Staff status changed to $new.";
    }
    header("Location: manage-staff.php");
    exit;
}

// ===== HANDLE DELETE =====
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id === (int)$_SESSION['staff_id']) {
        $_SESSION['flash_error'] = "You cannot delete your own account.";
    } else {
        $conn->query("DELETE FROM staff WHERE id=$id");
        $_SESSION['flash_success'] = "Staff account deleted.";
    }
    header("Location: manage-staff.php");
    exit;
}

$page_title = 'Manage Staff';
$page_icon = 'fa-user-tie';

$staff_list = $conn->query("
    SELECT s.*, h.name AS hostel_name
    FROM staff s
    LEFT JOIN hostels h ON s.hostel_id = h.id
    ORDER BY s.role, s.full_name
");

$hostels = $conn->query("SELECT id, name FROM hostels WHERE status='active' ORDER BY name");

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-user-tie"></i>Staff Accounts (<?= $staff_list ? $staff_list->num_rows : 0 ?>)</h3>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#staffModal" onclick="openAdd()">
            <i class="fas fa-plus me-1"></i> Add Staff
        </button>
    </div>

    <div class="card-body p-0">
        <?php if ($staff_list && $staff_list->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Assigned Hostel</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th style="width:200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($s = $staff_list->fetch_assoc()): 
                        $role_badge = ['admin'=>'danger','warden'=>'warning','staff'=>'info'][$s['role']] ?? 'secondary';
                        $is_self = ($s['id'] == $_SESSION['staff_id']);
                    ?>
                    <tr>
                        <td>
                            <strong style="color:#1a2a4a;"><?= htmlspecialchars($s['full_name']) ?></strong>
                            <?php if ($is_self): ?><span class="badge bg-primary ms-1">You</span><?php endif; ?>
                        </td>
                        <td style="font-size:0.85rem;"><?= htmlspecialchars($s['email']) ?></td>
                        <td style="font-size:0.85rem;"><?= htmlspecialchars($s['phone'] ?: '—') ?></td>
                        <td><span class="badge bg-<?= $role_badge ?>"><?= ucfirst($s['role']) ?></span></td>
                        <td style="font-size:0.85rem;"><?= htmlspecialchars($s['hostel_name'] ?: '—') ?></td>
                        <td style="font-size:0.82rem;color:#6c757d;">
                            <?= $s['last_login'] ? date('d M Y, H:i', strtotime($s['last_login'])) : 'Never' ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($s['status']) ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary" onclick='openEdit(<?= json_encode([
                                "id" => $s["id"],
                                "full_name" => $s["full_name"],
                                "email" => $s["email"],
                                "phone" => $s["phone"],
                                "role" => $s["role"],
                                "hostel_id" => $s["hostel_id"],
                                "status" => $s["status"]
                            ]) ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if (!$is_self): ?>
                                <a href="?toggle=<?= $s['id'] ?>" class="btn btn-sm btn-outline-warning" title="Toggle status">
                                    <i class="fas fa-power-off"></i>
                                </a>
                                <button class="btn btn-sm btn-danger" onclick="confirmDelete('?delete=<?= $s['id'] ?>', 'Delete staff account for <?= htmlspecialchars(addslashes($s['full_name'])) ?>?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-user-tie" style="font-size:4rem;color:#d1d5db;"></i>
                <h5 class="mt-3" style="color:#1a2a4a;">No staff accounts yet</h5>
                <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#staffModal" onclick="openAdd()">
                    <i class="fas fa-plus me-1"></i> Add First Staff
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============ ADD/EDIT MODAL ============ -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <form method="POST" id="staffForm">
                <div class="modal-header" style="background:linear-gradient(135deg,#1a3a6b,#0d1b3e);color:white;border-radius:16px 16px 0 0;">
                    <h5 class="modal-title" id="modalTitle"><i class="fas fa-plus me-2"></i>Add Staff Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="staffId">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" id="fullName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" id="staffEmail" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="staffPhone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role *</label>
                            <select name="role" id="staffRole" class="form-select" required onchange="toggleHostelField()">
                                <option value="staff">Staff</option>
                                <option value="warden">Warden</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="hostelField" style="display:none;">
                            <label class="form-label">Assigned Hostel</label>
                            <select name="hostel_id" id="staffHostel" class="form-select">
                                <option value="">— None —</option>
                                <?php while ($h = $hostels->fetch_assoc()): ?>
                                    <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="statusField" style="display:none;">
                            <label class="form-label">Status</label>
                            <select name="status" id="staffStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" id="passLabel">Password *</label>
                            <input type="password" name="password" id="staffPassword" class="form-control" minlength="6">
                            <small class="text-muted" id="passHint">Minimum 6 characters</small>
                        </div>
                        <div class="col-md-6" id="newPassField" style="display:none;">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" id="staffNewPassword" class="form-control" minlength="6">
                            <small class="text-muted">Leave blank to keep current password</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save me-1"></i> <span id="submitText">Create Account</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleHostelField() {
    const role = document.getElementById('staffRole').value;
    document.getElementById('hostelField').style.display = (role === 'warden' || role === 'staff') ? '' : 'none';
}
function openAdd() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Add Staff Account';
    document.getElementById('staffForm').reset();
    document.getElementById('staffId').value = '';
    document.getElementById('staffPassword').required = true;
    document.getElementById('staffPassword').disabled = false;
    document.getElementById('passLabel').textContent = 'Password *';
    document.getElementById('passHint').textContent = 'Minimum 6 characters';
    document.getElementById('newPassField').style.display = 'none';
    document.getElementById('statusField').style.display = 'none';
    document.getElementById('submitText').textContent = 'Create Account';
    document.getElementById('staffForm').querySelector('[name="add_staff"]')?.remove();

    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'add_staff';
    hidden.value = '1';
    document.getElementById('staffForm').appendChild(hidden);

    toggleHostelField();
}
function openEdit(s) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Staff Account';
    document.getElementById('staffId').value = s.id;
    document.getElementById('fullName').value = s.full_name || '';
    document.getElementById('staffEmail').value = s.email || '';
    document.getElementById('staffPhone').value = s.phone || '';
    document.getElementById('staffRole').value = s.role || 'staff';
    document.getElementById('staffHostel').value = s.hostel_id || '';
    document.getElementById('staffStatus').value = s.status || 'active';

    document.getElementById('staffPassword').required = false;
    document.getElementById('staffPassword').disabled = true;
    document.getElementById('passLabel').textContent = 'Password';
    document.getElementById('passHint').textContent = 'Disabled during edit';
    document.getElementById('newPassField').style.display = '';
    document.getElementById('statusField').style.display = '';
    document.getElementById('submitText').textContent = 'Update Account';

    document.getElementById('staffForm').querySelector('[name="add_staff"]')?.remove();
    document.getElementById('staffForm').querySelector('[name="edit_staff"]')?.remove();

    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'edit_staff';
    hidden.value = '1';
    document.getElementById('staffForm').appendChild(hidden);

    toggleHostelField();
    new bootstrap.Modal(document.getElementById('staffModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>