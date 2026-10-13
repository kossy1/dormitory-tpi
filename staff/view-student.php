<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: login.php"); exit; }

$id = (int)($_GET['id'] ?? 0);
$student = $conn->query("SELECT * FROM students WHERE id=$id")->fetch_assoc();
if (!$student) { $_SESSION['flash_error'] = "Student not found."; header("Location: manage-students.php"); exit; }

$allocation = $conn->query("
    SELECT a.*, r.room_number, r.block, r.price, r.capacity, r.bed_spacing,
           h.name AS hostel_name, h.location AS hostel_location
    FROM allocations a
    JOIN rooms r ON a.room_id = r.id
    LEFT JOIN hostels h ON r.hostel_id = h.id
    WHERE a.student_id=$id AND a.status='active'
    LIMIT 1
")->fetch_assoc();

$payments = $conn->query("SELECT * FROM payments WHERE student_id=$id ORDER BY created_at DESC LIMIT 20");

$photo = $student['photo'] ?? '';
$avatar = ($photo && file_exists('../' . $photo)) ? '../' . htmlspecialchars($photo) : 'https://ui-avatars.com/api/?name=' . urlencode($student['full_name']) . '&background=1a3a6b&color=fff&size=120';

$page_title = 'Student Profile';
$page_icon = 'fa-user';

include 'includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <img src="<?= $avatar ?>" style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:4px solid #1a3a6b;margin-bottom:15px;">
                <h4 style="font-weight:800;color:#1a2a4a;margin-bottom:5px;"><?= htmlspecialchars($student['full_name']) ?></h4>
                <p class="text-muted mb-3"><?= htmlspecialchars($student['matric_no']) ?></p>
                <span class="badge bg-primary" style="font-size:0.85rem;padding:8px 16px;">
                    <i class="fas fa-<?= strtolower($student['gender']) === 'female' ? 'venus' : 'mars' ?> me-1"></i>
                    <?= htmlspecialchars($student['gender'] ?? 'N/A') ?>
                </span>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle"></i>Contact Information</h3>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div style="padding:14px;background:#f8f9fa;border-radius:10px;border-left:4px solid #1a3a6b;">
                            <div style="font-size:0.75rem;color:#6c757d;text-transform:uppercase;font-weight:700;">Email</div>
                            <div style="font-weight:600;color:#1a2a4a;"><?= htmlspecialchars($student['email'] ?? '—') ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div style="padding:14px;background:#f8f9fa;border-radius:10px;border-left:4px solid #1a3a6b;">
                            <div style="font-size:0.75rem;color:#6c757d;text-transform:uppercase;font-weight:700;">Phone</div>
                            <div style="font-weight:600;color:#1a2a4a;"><?= htmlspecialchars($student['phone'] ?? '—') ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div style="padding:14px;background:#f8f9fa;border-radius:10px;border-left:4px solid #1a3a6b;">
                            <div style="font-size:0.75rem;color:#6c757d;text-transform:uppercase;font-weight:700;">Department</div>
                            <div style="font-weight:600;color:#1a2a4a;"><?= htmlspecialchars($student['department'] ?? '—') ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div style="padding:14px;background:#f8f9fa;border-radius:10px;border-left:4px solid #1a3a6b;">
                            <div style="font-size:0.75rem;color:#6c757d;text-transform:uppercase;font-weight:700;">Level</div>
                            <div style="font-weight:600;color:#1a2a4a;"><?= htmlspecialchars($student['level'] ?? '—') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($allocation): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-bed"></i>Current Allocation</h3>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div style="padding:14px;background:#d4edda;border-radius:10px;border-left:4px solid #28a745;">
                            <div style="font-size:0.75rem;color:#155724;text-transform:uppercase;font-weight:700;">Room</div>
                            <div style="font-weight:800;color:#155724;font-size:1.1rem;"><?= htmlspecialchars($allocation['room_number']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div style="padding:14px;background:#f8f9fa;border-radius:10px;">
                            <div style="font-size:0.75rem;color:#6c757d;text-transform:uppercase;font-weight:700;">Hostel</div>
                            <div style="font-weight:700;color:#1a2a4a;"><?= htmlspecialchars($allocation['hostel_name'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div style="padding:14px;background:#fff4cc;border-radius:10px;border-left:4px solid #ffd700;">
                            <div style="font-size:0.75rem;color:#856404;text-transform:uppercase;font-weight:700;">Bed Spacing</div>
                            <div style="font-weight:700;color:#856404;"><?= htmlspecialchars(ucfirst($allocation['bed_spacing'] ?? 'Standard')) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div style="padding:14px;background:#f8f9fa;border-radius:10px;">
                            <div style="font-size:0.75rem;color:#6c757d;text-transform:uppercase;font-weight:700;">Block</div>
                            <div style="font-weight:700;color:#1a2a4a;"><?= htmlspecialchars($allocation['block'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div style="padding:14px;background:#f8f9fa;border-radius:10px;">
                            <div style="font-size:0.75rem;color:#6c757d;text-transform:uppercase;font-weight:700;">Semester</div>
                            <div style="font-weight:700;color:#1a2a4a;"><?= htmlspecialchars($allocation['semester'] ?? '—') ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div style="padding:14px;background:#f8f9fa;border-radius:10px;">
                            <div style="font-size:0.75rem;color:#6c757d;text-transform:uppercase;font-weight:700;">Allocated On</div>
                            <div style="font-weight:700;color:#1a2a4a;"><?= date('d M Y', strtotime($allocation['allocation_date'])) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>No active allocation.</strong> This student has not been allocated a room.
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-credit-card"></i>Payment History</h3>
    </div>
    <div class="card-body p-0">
        <?php if ($payments && $payments->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Method</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($p = $payments->fetch_assoc()): 
                        $sb = ['completed'=>'success','pending'=>'warning','failed'=>'danger'][$p['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td><?= date('d M Y H:i', strtotime($p['created_at'])) ?></td>
                        <td><code style="font-size:0.8rem;"><?= htmlspecialchars($p['reference']) ?></code></td>
                        <td><strong style="color:#28a745;">₦<?= number_format((float)$p['amount'], 2) ?></strong></td>
                        <td><span class="badge bg-<?= $sb ?>"><?= ucfirst($p['status']) ?></span></td>
                        <td><?= htmlspecialchars($p['payment_method'] ?? '—') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="text-muted text-center py-4 mb-0">No payment records.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>