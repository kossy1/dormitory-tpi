<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';
include '../config/functions.php';   // for allocateRoom()

$success = $error = '';

// Handle manual room allocation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate'])) {
    $student_id = (int)$_POST['student_id'];
    $room_id    = (int)$_POST['room_id'];
    $semester   = $_POST['semester'];

    if ($student_id <= 0 || $room_id <= 0) {
        $error = "Please select both student and room.";
    } else {
        // Check if room is still available
        $room_check = $conn->query("SELECT status FROM rooms WHERE id = $room_id")->fetch_assoc();
        
        if (!$room_check || $room_check['status'] !== 'available') {
            $error = "Selected room is no longer available.";
        } else {
            // Check if student already has active allocation
            $exists = $conn->query("
                SELECT id FROM allocations 
                WHERE student_id = $student_id 
                  AND semester = '$semester' 
                  AND status = 'active'
            ")->num_rows;

            if ($exists > 0) {
                $error = "Student already has an active room for this semester.";
            } else {
                // Allocate room
                $conn->query("UPDATE rooms SET status = 'occupied' WHERE id = $room_id");

                $stmt = $conn->prepare("
                    INSERT INTO allocations (student_id, room_id, semester, status, allocated_by, admin_id)
                    VALUES (?, ?, ?, 'active', 'manual', ?)
                ");
                $admin_id = $_SESSION['admin_id'];
                $stmt->bind_param("iisi", $student_id, $room_id, $semester, $admin_id);

                if ($stmt->execute()) {
                    $success = "Room allocated successfully to the student!";
                } else {
                    $error = "Allocation failed: " . $conn->error;
                }
            }
        }
    }
}

// Fetch unallocated students (for current semester)
$unallocated = $conn->query("
    SELECT s.id, s.matric_no, s.full_name, s.gender, s.level 
    FROM students s
    LEFT JOIN allocations a ON s.id = a.student_id 
        AND a.status = 'active' 
        AND a.semester = '1'
    WHERE a.id IS NULL AND s.status = 'active'
    ORDER BY s.matric_no
")->fetch_all(MYSQLI_ASSOC);

// Fetch available rooms
$available_rooms = $conn->query("
    SELECT id, room_number, block, capacity, gender, price 
    FROM rooms 
    WHERE status = 'available' 
    ORDER BY room_number
")->fetch_all(MYSQLI_ASSOC);
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
        <h1>Manual Room Allocation</h1>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">

        <?php if ($success): ?>
          <div class="alert alert-success alert-dismissible"><?= $success ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="alert alert-danger alert-dismissible"><?= $error ?></div>
        <?php endif; ?>

        <div class="card card-primary">
          <div class="card-header">
            <h3 class="card-title">Allocate Room to Student</h3>
          </div>
          <div class="card-body">

            <form method="POST">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Select Unallocated Student</label>
                    <select name="student_id" class="form-control select2" required>
                      <option value="">-- Choose Student --</option>
                      <?php foreach ($unallocated as $stu): ?>
                        <option value="<?= $stu['id'] ?>">
                          <?= htmlspecialchars($stu['matric_no']) ?> - 
                          <?= htmlspecialchars($stu['full_name']) ?> 
                          (<?= $stu['gender'] ?>, <?= $stu['level'] ?> Level)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="form-group">
                    <label>Select Available Room</label>
                    <select name="room_id" class="form-control select2" required>
                      <option value="">-- Choose Room --</option>
                      <?php foreach ($available_rooms as $rm): ?>
                        <option value="<?= $rm['id'] ?>">
                          <?= htmlspecialchars($rm['room_number']) ?> 
                          (<?= htmlspecialchars($rm['block'] ?? 'N/A') ?>) — 
                          <?= $rm['gender'] ?> — 
                          Capacity: <?= $rm['capacity'] ?> — 
                          ₦<?= number_format($rm['price'], 2) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label>Semester</label>
                <select name="semester" class="form-control" required>
                  <option value="1">First Semester</option>
                  <option value="2">Second Semester</option>
                </select>
              </div>

              <button type="submit" name="allocate" class="btn btn-primary btn-lg">
                <i class="fa fa-check"></i> Allocate Room Now
              </button>
            </form>
          </div>
        </div>

        <!-- Quick Stats -->
        <div class="row">
          <div class="col-md-6">
            <div class="small-box bg-info">
              <div class="inner">
                <h3><?= count($unallocated) ?></h3>
                <p>Students Waiting for Room</p>
              </div>
              <div class="icon"><i class="fas fa-users"></i></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="small-box bg-success">
              <div class="inner">
                <h3><?= count($available_rooms) ?></h3>
                <p>Rooms Currently Available</p>
              </div>
              <div class="icon"><i class="fas fa-bed"></i></div>
            </div>
          </div>
        </div>

      </div>
    </section>
  </div>

 <?php include 'footer.php'; ?>


<script>
$(document).ready(function() {
    $('.select2').select2({
        placeholder: "Select an option",
        allowClear: true
    });
});
</script>
</body>
</html>