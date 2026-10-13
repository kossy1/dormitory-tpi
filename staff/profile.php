<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: login.php"); exit; }

$staff_id = $_SESSION['staff_id'];
$page_title = 'My Profile';
$page_icon = 'fa-user';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        $email     = trim($_POST['email']);
        $phone     = trim($_POST['phone']);

        $fn = $conn->real_escape_string($full_name);
        $em = $conn->real_escape_string($email);
        $ph = $conn->real_escape_string($phone);

        if ($conn->query("UPDATE staff SET full_name='$fn', email='$em', phone='$ph' WHERE id=$staff_id")) {
            $_SESSION['staff_name'] = $full_name;
            $_SESSION['flash_success'] = "Profile updated successfully.";
            header("Location: profile.php");
            exit;
        } else {
            $error = "Error: " . $conn->error;
        }
    } elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $staff = $conn->query("SELECT * FROM staff WHERE id=$staff_id")->fetch_assoc();

        if (!password_verify($current, $staff['password'])) {
            $error = "Current password is incorrect.";
        } elseif (strlen($new) < 6) {
            $error = "New password must be at least 6 characters.";
        } elseif ($new !== $confirm) {
            $error = "New passwords do not match.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $conn->query("UPDATE staff SET password='$hash' WHERE id=$staff_id");
            $_SESSION['flash_success'] = "Password changed successfully.";
            header("Location: profile.php");
            exit;
        }
    }
}

$staff = $conn->query("SELECT * FROM staff WHERE id=$staff_id")->fetch_assoc();
$photo = $staff['photo'] ?? '';
$avatar = ($photo && file_exists('../' . $photo)) ? '../' . htmlspecialchars($photo) : 'https://ui-avatars.com/api/?name=' . urlencode($staff['full_name']) . '&background=1a3a6b&color=fff&size=120';

include 'includes/header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <img src="<?= $avatar ?>" style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:4px solid #1a3a6b;margin-bottom:15px;">
                <h4 style="font-weight:800;color:#1a2a4a;margin-bottom:4px;"><?= htmlspecialchars($staff['full_name']) ?></h4>
                <p class="text-muted mb-3" style="font-size:0.9rem;"><?= htmlspecialchars($staff['email']) ?></p>
                <span class="badge bg-primary" style="font-size:0.8rem;padding:7px 16px;text-transform:uppercase;letter-spacing:0.5px;">
                    <?= htmlspecialchars($staff['role']) ?>
                </span>
                <?php if (!empty($staff['last_login'])): ?>
                    <p class="text-muted mt-3 mb-0" style="font-size:0.78rem;">
                        Last login: <?= date('d M Y, H:i', strtotime($staff['last_login'])) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-edit"></i>Profile Information</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($staff['full_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($staff['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($staff['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($staff['role']) ?>" disabled>
                        </div>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary mt-3">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-lock"></i>Change Password</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary mt-3">
                        <i class="fas fa-key me-1"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>