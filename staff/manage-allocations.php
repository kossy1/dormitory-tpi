<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: login.php"); exit; }

// Manual allocation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate'])) {
    $student_id = (int)$_POST['student_id'];
    $room_id    = (int)$_POST['room_id'];

    // Verify room has space
    $room = $conn->query("SELECT r.*, 
                          (SELECT COUNT(*) FROM allocations a WHERE a.room_id=r.id AND a.status='active') occ
                          FROM rooms r WHERE r.id=$room_id")->fetch_assoc();

    if (!$room) {
        $_SESSION['flash_error'] = "Room not found.";
    } elseif ((int)$room['occ'] >= (int)$room['capacity']) {
        $_SESSION['flash_error'] = "Room is already full.";
    } else {
        // Check existing allocation
        $exists = (int)$conn->query("SELECT COUNT(*) c FROM allocations WHERE student_id=$student_id AND status='active'")->fetch_assoc()['c'];
        if ($exists) {
            $_SESSION['flash_error'] = "This student already has an active allocation.";
        } else {
            $sem = $conn->real_escape_string($_POST['semester'] ?? '2024/2025');
            $conn->query("INSERT INTO allocations (student_id, room_id, allocation_date, status, semester) 
                          VALUES ($student_id, $room_id, NOW(), 'active', '$sem')");
            
            // Mark room occupied if full
            $newOcc = (int)$room['occ'] + 1;
            if ($newOcc >= (int)$room['capacity']) {
                $conn->query("UPDATE rooms SET status='occupied' WHERE id=$room_id");
            }
            $_SESSION['flash_success'] = "Student allocated successfully.";
        }
    }
    header("Location: manage-allocations.php");
    exit;
}

// Vacate
if (isset($_GET['vacate'])) {
    $aid = (int)$_GET['vacate'];
    $alloc = $conn->query("SELECT * FROM allocations WHERE id=$aid")->fetch_assoc();
    if ($alloc) {
        $conn->query("UPDATE allocations SET status='vacated' WHERE id=$aid");
        $conn->query("UPDATE rooms SET status='available' WHERE id=" . (int)$alloc['room_id']);
        $_SESSION['flash_success'] = "Student vacated the room.";
    }
    header("Location: manage-allocations.php");
    exit;
}

$page_title = 'Manage Allocations';
$page_icon = 'fa-clipboard-check';

$filter = $_GET['filter'] ?? 'active';
$where = "a.status = '" . $conn->real_escape_string($filter) . "'";

$allocations = $conn->query("
    SELECT a.*, s.full_name, s.matric_no, s.email,
           r.room_number, r.block,
           h.name AS hostel_name
    FROM allocations a
    JOIN students s ON a.student_id = s.id
    JOIN rooms r ON a.room_id = r.id
    LEFT JOIN hostels h ON r.hostel_id = h.id
    WHERE $where
    ORDER BY a.allocation_date DESC
    LIMIT 200
");

$students = $conn->query("
    SELECT id, full_name, matric_no FROM students 
    WHERE NOT EXISTS(SELECT 1 FROM allocations WHERE student_id=students.id AND status='active')
    ORDER BY full_name
");

$rooms = $conn->query("
    SELECT r.id, r.room_number, r.capacity, h.name AS hostel_name,
           (SELECT COUNT(*) FROM allocations a WHERE a.room_id=r.id AND a.status='active') occ
    FROM rooms r
    LEFT JOIN hostels h ON r.hostel_id = h.id
    WHERE r.status = 'available'
    HAVING occ < r.capacity
    ORDER BY h.name, r.room_number
");

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-clipboard-check"></i>Allocations</h3>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#allocModal">
            <i class="fas fa-plus me-1"></i> New Allocation
        </button>
    </div>

    <div class="card-body" style="border-bottom:2px solid #f0f2f5;">
        <div class="d-flex gap-2 flex-wrap">
            <a href="?filter=active" class="btn btn-sm <?= $filter==='active'?'btn-primary':'btn-outline-secondary' ?>">Active</a>
            <a href="?filter=vacated" class="btn btn-sm <?= $filter==='vacated'?'btn-primary':'btn-outline-secondary' ?>">Vacated</a>
            <a href="?filter=pending" class="btn btn-sm <?= $filter==='pending'?'btn-primary':'btn-outline-secondary' ?>">Pending</a>
        </div>
    </div>

    <div class="card-body p-0">
        <?php if ($allocations && $allocations->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Room</th>
                        <th>Hostel</th>
                        <th>Semester</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; while ($a = $allocations->fetch_assoc()): 
                        $sb = ['active'=>'success','vacated'=>'secondary','pending'=>'warning'][$a['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td>
                            <strong style="color:#1a2a4a;"><?= htmlspecialchars($a['full_name']) ?></strong>
                            <div style="font-size:0.75rem;color:#6c757d;"><?= htmlspecialchars($a['matric_no']) ?></div>
                        </td>
                        <td><span class="badge bg-primary"><?= htmlspecialchars($a['room_number']) ?></span>
                            <?php if ($a['block']): ?><div style="font-size:0.72rem;color:#6c757d;">Block <?= htmlspecialchars($a['block']) ?></div><?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($a['hostel_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($a['semester'] ?? '—') ?></td>
                        <td style="font-size:0.82rem;"><?= date('d M Y', strtotime($a['allocation_date'])) ?></td>
                        <td><span class="badge bg-<?= $sb ?>"><?= ucfirst($a['status']) ?></span></td>
                        <td>
                            <?php if ($a['status'] === 'active'): ?>
                                <button class="btn btn-sm btn-danger" onclick="confirmDelete('?vacate=<?= $a['id'] ?>', 'Vacate this student from the room?')">
                                    <i class="fas fa-sign-out-alt"></i>
                                </button>
                            <?php endif; ?>
                            <a href="view-student.php?id=<?= $a['student_id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-clipboard-check" style="font-size:4rem;color:#d1d5db;"></i>
                <h5 class="mt-3" style="color:#1a2a4a;">No allocations found</h5>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Allocation Modal -->
<div class="modal fade" id="allocModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <form method="POST">
                <div class="modal-header" style="background:linear-gradient(135deg,#1a3a6b,#0d1b3e);color:white;border-radius:16px 16px 0 0;">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>New Allocation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Student *</label>
                        <select name="student_id" class="form-select" required>
                            <option value="">-- Select Student --</option>
                            <?php while ($s = $students->fetch_assoc()): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['matric_no']) ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Room *</label>
                        <select name="room_id" class="form-select" required>
                            <option value="">-- Select Room --</option>
                            <?php while ($r = $rooms->fetch_assoc()): 
                                $spaces = (int)$r['capacity'] - (int)$r['occ'];
                            ?>
                                <option value="<?= $r['id'] ?>">
                                    <?= htmlspecialchars($r['hostel_name'] ?? '') ?> — <?= htmlspecialchars($r['room_number']) ?> 
                                    (<?= $spaces ?> space<?= $spaces!=1?'s':'' ?> left)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Semester</label>
                        <input type="text" name="semester" class="form-control" value="2024/2025">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="allocate" class="btn btn-success"><i class="fas fa-check me-1"></i> Allocate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>