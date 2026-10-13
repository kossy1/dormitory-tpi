<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';

$success = $error = '';

// ====================== ADD NEW DEPARTMENT ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_department'])) {
    $faculty_id = (int)$_POST['faculty_id'];
    $name       = trim($_POST['department_name']);
    $code       = trim($_POST['department_code'] ?? '');

    if ($faculty_id <= 0 || empty($name)) {
        $error = "Faculty and Department name are required.";
    } else {
        $check = $conn->prepare("SELECT id FROM departments WHERE faculty_id = ? AND name = ?");
        $check->bind_param("is", $faculty_id, $name);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "This department already exists under the selected faculty.";
        } else {
            $stmt = $conn->prepare("INSERT INTO departments (faculty_id, name, code) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $faculty_id, $name, $code);
            if ($stmt->execute()) {
                $success = "Department added successfully!";
            } else {
                $error = "Failed to add department.";
            }
        }
    }
}

// ====================== DELETE DEPARTMENT ======================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $dept_id = (int)$_GET['delete'];

    // Check if any student is using this department
    $check_students = $conn->query("SELECT id FROM students WHERE department_id = $dept_id LIMIT 1");
    
    if ($check_students->num_rows > 0) {
        $error = "Cannot delete department. Students are still registered under it.";
    } else {
        if ($conn->query("DELETE FROM departments WHERE id = $dept_id")) {
            $success = "Department deleted successfully.";
        } else {
            $error = "Failed to delete department.";
        }
    }
}
?>

<?php include 'header.php'; ?>
<body class="hold-transition sidebar-mini layout-fixed">

<div class="wrapper">

 <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <div class="sidebar">
      <?php include 'sidebar.php'; ?>
    </div>
  </aside>

  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
        <h1>Manage Departments</h1>
      </div>
    </div>
</div></div>
    <section class="content">
      <div class="container-fluid">

        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

        <!-- Add New Department Form -->
        <div class="card card-primary">
          <div class="card-header">
            <h3 class="card-title">Add New Department</h3>
          </div>
          <div class="card-body">
            <form method="POST">
              <div class="row">
                <div class="col-md-5">
                  <div class="form-group">
                    <label>Faculty <span class="text-danger">*</span></label>
                    <select name="faculty_id" class="form-control" required>
                      <option value="">-- Select Faculty --</option>
                      <?php
                      $faculties = $conn->query("SELECT id, name FROM faculties ORDER BY name")->fetch_all(MYSQLI_ASSOC);
                      foreach ($faculties as $f):
                      ?>
                        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-5">
                  <div class="form-group">
                    <label>Department Name <span class="text-danger">*</span></label>
                    <input type="text" name="department_name" class="form-control" required placeholder="e.g. Computer Science">
                  </div>
                </div>
                <div class="col-md-2">
                  <div class="form-group">
                    <label>Code (Optional)</label>
                    <input type="text" name="department_code" class="form-control" placeholder="CSC">
                  </div>
                </div>
              </div>
              <button type="submit" name="add_department" class="btn btn-primary">Add Department</button>
            </form>
          </div>
        </div>

        <!-- Existing Departments Table with Edit & Delete -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Existing Departments</h3>
          </div>
          <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
              <thead>
                <tr>
                  <th>Faculty</th>
                  <th>Department Name</th>
                  <th>Code</th>
                  <th width="140">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $depts = $conn->query("
                    SELECT d.*, f.name as faculty_name 
                    FROM departments d 
                    JOIN faculties f ON d.faculty_id = f.id 
                    ORDER BY f.name, d.name
                ")->fetch_all(MYSQLI_ASSOC);

                foreach ($depts as $dept):
                ?>
                <tr>
                  <td><?= htmlspecialchars($dept['faculty_name']) ?></td>
                  <td><?= htmlspecialchars($dept['name']) ?></td>
                  <td><?= htmlspecialchars($dept['code'] ?? '-') ?></td>
                  <td>
                    <a href="edit_department.php?id=<?= $dept['id'] ?>" class="btn btn-xs btn-warning">
                      <i class="fa fa-edit"></i> Edit
                    </a>
                    <a href="?delete=<?= $dept['id'] ?>" 
                       class="btn btn-xs btn-danger"
                       onclick="return confirm('Delete this department? Students using it will be affected.')">
                      <i class="fa fa-trash"></i> Delete
                    </a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </section>
  </div>

<?php include 'footer.php'; ?>