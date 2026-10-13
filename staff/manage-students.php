<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: login.php"); exit; }

$page_title = 'Manage Students';
$page_icon = 'fa-users';

$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? '';

$where = ["1=1"];
if ($search) {
    $s = $conn->real_escape_string($search);
    $where[] = "(s.full_name LIKE '%$s%' OR s.matric_no LIKE '%$s%' OR s.email LIKE '%$s%')";
}
if ($filter_status === 'allocated') {
    $where[] = "EXISTS(SELECT 1 FROM allocations a WHERE a.student_id=s.id AND a.status='active')";
} elseif ($filter_status === 'unallocated') {
    $where[] = "NOT EXISTS(SELECT 1 FROM allocations a WHERE a.student_id=s.id AND a.status='active')";
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

$students = $conn->query("
    SELECT s.*,
           (SELECT r.room_number FROM allocations a JOIN rooms r ON a.room_id=r.id 
            WHERE a.student_id=s.id AND a.status='active' LIMIT 1) AS room_number,
           (SELECT h.name FROM allocations a JOIN rooms r ON a.room_id=r.id 
            LEFT JOIN hostels h ON r.hostel_id=h.id
            WHERE a.student_id=s.id AND a.status='active' LIMIT 1) AS hostel_name
    FROM students s
    $where_sql
    ORDER BY s.full_name
    LIMIT 300
");

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-users"></i>Students (<?= $students ? $students->num_rows : 0 ?>)</h3>
    </div>

    <div class="card-body" style="border-bottom:2px solid #f0f2f5;">
        <form method="GET" class="row g-2">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search name, matric no, or email..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Students</option>
                    <option value="allocated" <?= $filter_status==='allocated'?'selected':'' ?>>Allocated</option>
                    <option value="unallocated" <?= $filter_status==='unallocated'?'selected':'' ?>>Unallocated</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Search</button>
            </div>
            <div class="col-md-2">
                <a href="manage-students.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if ($students && $students->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Matric No</th>
                        <th>Email</th>
                        <th>Gender</th>
                        <th>Room</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($s = $students->fetch_assoc()): 
                        $photo = $s['photo'] ?? '';
                        $avatar = ($photo && file_exists('../' . $photo)) ? '../' . htmlspecialchars($photo) : 'https://ui-avatars.com/api/?name=' . urlencode($s['full_name']) . '&background=1a3a6b&color=fff&size=40';
                    ?>
                    <tr>
                        <td><img src="<?= $avatar ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;"></td>
                        <td><strong style="color:#1a2a4a;"><?= htmlspecialchars($s['full_name']) ?></strong></td>
                        <td><code style="font-size:0.82rem;"><?= htmlspecialchars($s['matric_no']) ?></code></td>
                        <td style="font-size:0.85rem;"><?= htmlspecialchars($s['email'] ?? '—') ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($s['gender'] ?? '—') ?></span></td>
                        <td>
                            <?php if ($s['room_number']): ?>
                                <span class="badge bg-success"><?= htmlspecialchars($s['room_number']) ?></span>
                                <div style="font-size:0.72rem;color:#6c757d;"><?= htmlspecialchars($s['hostel_name'] ?? '') ?></div>
                            <?php else: ?>
                                <span class="badge bg-warning">Not allocated</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="view-student.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-users" style="font-size:4rem;color:#d1d5db;"></i>
                <h5 class="mt-3" style="color:#1a2a4a;">No students found</h5>
                <p class="text-muted">Try a different search or filter.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>