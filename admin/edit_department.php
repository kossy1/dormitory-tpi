<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';

$dept_id = (int)($_GET['id'] ?? 0);
$success = $error = '';

if ($dept_id <= 0) {
    header("Location: add_department.php");
    exit;
}

$dept = $conn->query("
    SELECT d.*, f.name as faculty_name 
    FROM departments d 
    JOIN faculties f ON d.faculty_id = f.id 
    WHERE d.id = $dept_id
")->fetch_assoc();

if (!$dept) {
    header("Location: add_department.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['department_name']);
    $code = trim($_POST['department_code'] ?? '');

    if (empty($name)) {
        $error = "Department name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE departments SET name = ?, code = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $code, $dept_id);

        if ($stmt->execute()) {
            $success = "Department updated successfully!";
            $dept = $conn->query("SELECT d.*, f.name as faculty_name FROM departments d JOIN faculties f ON d.faculty_id = f.id WHERE d.id = $dept_id")->fetch_assoc();
        } else {
            $error = "Failed to update department.";
        }
    }
}
?>

<?php include 'header.php'; ?>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

 <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <div class="sidebar">
      <?php include 'sidebar.php'; ?>
    </div>
  </aside>
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <h1>Edit Department</h1>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">
        <div class="card card-warning">
          <div class="card-header">
            <h3 class="card-title">Update Department</h3>
          </div>
          <div class="card-body">

            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <form method="POST">
              <div class="form-group">
                <label>Faculty</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($dept['faculty_name']) ?>" disabled>
              </div>

              <div class="form-group">
                <label>Department Name <span class="text-danger">*</span></label>
                <input type="text" name="department_name" class="form-control" value="<?= htmlspecialchars($dept['name']) ?>" required>
              </div>

              <div class="form-group">
                <label>Department Code</label>
                <input type="text" name="department_code" class="form-control" value="<?= htmlspecialchars($dept['code'] ?? '') ?>">
              </div>

              <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-lg">Update Department</button>
                <a href="add_department.php" class="btn btn-secondary btn-lg">Cancel</a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </section>
  </div>

<?php include 'footer.php'; ?>