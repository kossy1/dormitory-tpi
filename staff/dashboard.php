<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['staff_id'])) { header("Location: login.php"); exit; }

$page_title = 'Dashboard';
$page_icon = 'fa-tachometer-alt';

// ===== STATS =====
$total_hostels    = (int)$conn->query("SELECT COUNT(*) c FROM hostels WHERE status='active'")->fetch_assoc()['c'];
$total_rooms      = (int)$conn->query("SELECT COUNT(*) c FROM rooms")->fetch_assoc()['c'];
$available_rooms  = (int)$conn->query("SELECT COUNT(*) c FROM rooms WHERE status='available'")->fetch_assoc()['c'];
$total_students   = (int)$conn->query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'];
$allocated        = (int)$conn->query("SELECT COUNT(*) c FROM allocations WHERE status='active'")->fetch_assoc()['c'];
$unallocated      = max(0, $total_students - $allocated);
$total_revenue    = (float)($conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='completed'")->fetch_assoc()['s']);
$pending_payments = (int)$conn->query("SELECT COUNT(*) c FROM payments WHERE status='pending'")->fetch_assoc()['c'];

// Occupancy rate
$total_capacity = (int)($conn->query("SELECT COALESCE(SUM(capacity),0) c FROM rooms")->fetch_assoc()['c']);
$occupancy_rate = $total_capacity > 0 ? ($allocated / $total_capacity) * 100 : 0;

// Recent allocations
$recent_allocs = $conn->query("
    SELECT a.id, a.allocation_date, s.full_name, s.matric_no, r.room_number, h.name AS hostel_name
    FROM allocations a
    JOIN students s ON a.student_id = s.id
    JOIN rooms r ON a.room_id = r.id
    LEFT JOIN hostels h ON r.hostel_id = h.id
    WHERE a.status = 'active'
    ORDER BY a.allocation_date DESC
    LIMIT 6
");

// Recent payments
$recent_payments = $conn->query("
    SELECT p.id, p.amount, p.status, p.created_at, p.reference, s.full_name, s.matric_no
    FROM payments p
    JOIN students s ON p.student_id = s.id
    ORDER BY p.created_at DESC
    LIMIT 6
");

// Room occupancy by hostel
$hostel_stats = $conn->query("
    SELECT h.id, h.name,
           COUNT(DISTINCT r.id) AS room_count,
           COALESCE(SUM(r.capacity),0) AS capacity,
           (SELECT COUNT(*) FROM allocations a 
            JOIN rooms r2 ON a.room_id = r2.id 
            WHERE r2.hostel_id = h.id AND a.status='active') AS occupied
    FROM hostels h
    LEFT JOIN rooms r ON r.hostel_id = h.id
    WHERE h.status = 'active'
    GROUP BY h.id
    ORDER BY h.name
");

include 'includes/header.php';
?>

<!-- Stat Cards Row 1 -->
<div class="row g-3 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon bg-gradient-blue"><i class="fas fa-hotel"></i></div>
            <div class="stat-info">
                <h3><?= number_format($total_hostels) ?></h3>
                <p>Active Hostels</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon bg-gradient-green"><i class="fas fa-bed"></i></div>
            <div class="stat-info">
                <h3><?= number_format($available_rooms) ?><small style="font-size:0.6em;color:#6c757d;">/<?= $total_rooms ?></small></h3>
                <p>Available Rooms</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon bg-gradient-purple"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h3><?= number_format($total_students) ?></h3>
                <p>Total Students</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon bg-gradient-orange"><i class="fas fa-clipboard-check"></i></div>
            <div class="stat-info">
                <h3><?= number_format($allocated) ?></h3>
                <p>Allocated Students</p>
            </div>
        </div>
    </div>
</div>

<!-- Stat Cards Row 2 -->
<div class="row g-3 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon bg-gradient-teal"><i class="fas fa-user-clock"></i></div>
            <div class="stat-info">
                <h3><?= number_format($unallocated) ?></h3>
                <p>Unallocated Students</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon bg-gradient-green"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-info">
                <h3 style="font-size:1.3rem;">₦<?= number_format($total_revenue, 0) ?></h3>
                <p>Total Revenue</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon bg-gradient-orange"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-info">
                <h3><?= number_format($pending_payments) ?></h3>
                <p>Pending Payments</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon bg-gradient-red"><i class="fas fa-percentage"></i></div>
            <div class="stat-info">
                <h3><?= number_format($occupancy_rate, 1) ?>%</h3>
                <p>Occupancy Rate</p>
            </div>
        </div>
    </div>
</div>

<!-- Hostel Occupancy -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-bar"></i>Occupancy by Hostel</h3>
        <a href="manage-hostels.php" class="btn btn-outline-secondary btn-sm">
            Manage Hostels <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-body">
        <?php if ($hostel_stats && $hostel_stats->num_rows > 0): ?>
            <div class="row g-3">
                <?php while ($h = $hostel_stats->fetch_assoc()): 
                    $cap = (int)$h['capacity'];
                    $occ = (int)$h['occupied'];
                    $pct = $cap > 0 ? ($occ / $cap) * 100 : 0;
                    $color = $pct >= 90 ? 'danger' : ($pct >= 60 ? 'warning' : 'success');
                ?>
                <div class="col-lg-6">
                    <div style="padding:16px;background:#f8f9fa;border-radius:12px;border-left:4px solid var(--bs-<?= $color ?>);">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong style="color:#1a2a4a;"><?= htmlspecialchars($h['name']) ?></strong>
                                <div style="font-size:0.8rem;color:#6c757d;">
                                    <?= (int)$h['room_count'] ?> rooms • <?= $cap ?> bed spaces
                                </div>
                            </div>
                            <div class="text-end">
                                <div style="font-weight:800;color:#1a2a4a;font-size:1.1rem;"><?= $occ ?> / <?= $cap ?></div>
                                <div style="font-size:0.75rem;color:#6c757d;"><?= number_format($pct, 0) ?>% full</div>
                            </div>
                        </div>
                        <div class="progress" style="height:8px;border-radius:4px;background:#e9ecef;">
                            <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%;border-radius:4px;"></div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="text-muted text-center py-4 mb-0">No hostels found. <a href="manage-hostels.php">Add one now</a>.</p>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <!-- Recent Allocations -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-clipboard-check"></i>Recent Allocations</h3>
                <a href="manage-allocations.php" class="btn btn-outline-secondary btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if ($recent_allocs && $recent_allocs->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Room</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($a = $recent_allocs->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong style="color:#1a2a4a;font-size:0.88rem;"><?= htmlspecialchars($a['full_name']) ?></strong>
                                    <div style="font-size:0.75rem;color:#6c757d;"><?= htmlspecialchars($a['matric_no']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= htmlspecialchars($a['room_number']) ?></span>
                                    <div style="font-size:0.75rem;color:#6c757d;"><?= htmlspecialchars($a['hostel_name'] ?? '') ?></div>
                                </td>
                                <td style="font-size:0.82rem;color:#6c757d;"><?= date('d M Y', strtotime($a['allocation_date'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4 mb-0">No recent allocations.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-credit-card"></i>Recent Payments</h3>
                <a href="manage-payments.php" class="btn btn-outline-secondary btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if ($recent_payments && $recent_payments->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($p = $recent_payments->fetch_assoc()): 
                                $badge = $p['status'] === 'completed' ? 'success' : ($p['status'] === 'pending' ? 'warning' : 'danger');
                            ?>
                            <tr>
                                <td>
                                    <strong style="color:#1a2a4a;font-size:0.88rem;"><?= htmlspecialchars($p['full_name']) ?></strong>
                                    <div style="font-size:0.72rem;color:#6c757d;"><code><?= htmlspecialchars(substr($p['reference'], 0, 20)) ?>…</code></div>
                                </td>
                                <td><strong style="color:#28a745;">₦<?= number_format((float)$p['amount'], 0) ?></strong></td>
                                <td>
                                    <span class="badge bg-<?= $badge ?>">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4 mb-0">No recent payments.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>