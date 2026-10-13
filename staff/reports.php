<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: login.php"); exit; }

$page_title = 'Reports & Analytics';
$page_icon = 'fa-chart-line';

// ===== DETECT which date column exists on payments =====
$payments_date_col = 'paid_at'; // default
$check = $conn->query("SHOW COLUMNS FROM payments LIKE 'paid_at'");
if (!$check || $check->num_rows === 0) {
    // Fallback to created_at if paid_at doesn't exist
    $payments_date_col = 'created_at';
}

// ===== Revenue by month (last 6 months) =====
$revenue_by_month = $conn->query("
    SELECT DATE_FORMAT($payments_date_col, '%Y-%m') AS ym, 
           DATE_FORMAT($payments_date_col, '%b %Y') AS label,
           SUM(amount) AS total, COUNT(*) AS count
    FROM payments
    WHERE status='completed' 
      AND $payments_date_col >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
      AND $payments_date_col IS NOT NULL
    GROUP BY ym 
    ORDER BY ym ASC
");

// ===== Occupancy by hostel =====
$occupancy = $conn->query("
    SELECT h.name,
           COALESCE(SUM(r.capacity),0) AS capacity,
           (SELECT COUNT(*) FROM allocations a 
            JOIN rooms r2 ON a.room_id=r2.id 
            WHERE r2.hostel_id=h.id AND a.status='active') AS occupied
    FROM hostels h
    LEFT JOIN rooms r ON r.hostel_id=h.id
    WHERE h.status='active'
    GROUP BY h.id 
    ORDER BY h.name
");

// ===== Bed spacing distribution =====
$spacing_dist = $conn->query("
    SELECT bed_spacing, COUNT(*) AS cnt, SUM(capacity) AS total_beds
    FROM rooms 
    GROUP BY bed_spacing 
    ORDER BY cnt DESC
");

// ===== Gender distribution =====
$gender_dist = $conn->query("
    SELECT gender, COUNT(*) AS cnt FROM students GROUP BY gender
");

// ===== Totals =====
$total_collected = (float)($conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='completed'")->fetch_assoc()['s'] ?? 0);
$total_pending   = (float)($conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='pending'")->fetch_assoc()['s'] ?? 0);
$total_students  = (int)($conn->query("SELECT COUNT(*) c FROM students")->fetch_assoc()['c'] ?? 0);
$total_allocated = (int)($conn->query("SELECT COUNT(*) c FROM allocations WHERE status='active'")->fetch_assoc()['c'] ?? 0);

include 'header.php';
?>

<div class="row g-3">
    <!-- Revenue by month -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-money-bill-wave"></i>Revenue (Last 6 Months)</h3>
            </div>
            <div class="card-body p-0">
                <?php if ($revenue_by_month && $revenue_by_month->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr><th>Month</th><th>Transactions</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                        <?php $grand = 0; while ($r = $revenue_by_month->fetch_assoc()): $grand += $r['total']; ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['label']) ?></strong></td>
                            <td><?= (int)$r['count'] ?></td>
                            <td><strong style="color:#28a745;">₦<?= number_format((float)$r['total'], 0) ?></strong></td>
                        </tr>
                        <?php endwhile; ?>
                        <tr style="background:#f8f9fa;">
                            <td colspan="2"><strong>Grand Total</strong></td>
                            <td><strong style="color:#1a3a6b;font-size:1.05rem;">₦<?= number_format($grand, 0) ?></strong></td>
                        </tr>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="text-muted text-center py-4 mb-0">No revenue data yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Occupancy -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-pie"></i>Hostel Occupancy</h3>
            </div>
            <div class="card-body">
                <?php if ($occupancy && $occupancy->num_rows > 0): 
                    while ($o = $occupancy->fetch_assoc()): 
                        $cap = (int)$o['capacity'];
                        $occ = (int)$o['occupied'];
                        $pct = $cap > 0 ? ($occ / $cap) * 100 : 0;
                        $color = $pct >= 90 ? 'danger' : ($pct >= 60 ? 'warning' : 'success');
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <strong style="color:#1a2a4a;"><?= htmlspecialchars($o['name']) ?></strong>
                        <span style="font-size:0.85rem;color:#6c757d;">
                            <?= $occ ?>/<?= $cap ?> (<?= number_format($pct,0) ?>%)
                        </span>
                    </div>
                    <div class="progress" style="height:10px;border-radius:5px;background:#e9ecef;">
                        <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%;border-radius:5px;"></div>
                    </div>
                </div>
                <?php endwhile; endif; ?>
            </div>
        </div>
    </div>

    <!-- Bed spacing distribution -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-bed"></i>Bed Spacing Distribution</h3>
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr><th>Spacing</th><th>Rooms</th><th>Total Beds</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($spacing_dist && $spacing_dist->num_rows > 0): 
                            while ($s = $spacing_dist->fetch_assoc()): ?>
                        <tr>
                            <td><span class="badge" style="background:#fff4cc;color:#856404;">
                                <i class="fas fa-bed me-1"></i><?= htmlspecialchars(ucfirst($s['bed_spacing'] ?? 'Standard')) ?>
                            </span></td>
                            <td><strong><?= (int)$s['cnt'] ?></strong></td>
                            <td><?= (int)$s['total_beds'] ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="3" class="text-muted text-center py-4">No data.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Gender distribution -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-venus-mars"></i>Students by Gender</h3>
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr><th>Gender</th><th>Count</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($gender_dist && $gender_dist->num_rows > 0): 
                            while ($g = $gender_dist->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <i class="fas fa-<?= strtolower($g['gender'])==='female'?'venus':'mars' ?> me-2" style="color:#1a3a6b;"></i>
                                <strong><?= htmlspecialchars($g['gender'] ?: 'Unspecified') ?></strong>
                            </td>
                            <td><strong><?= (int)$g['cnt'] ?></strong></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="2" class="text-muted text-center py-4">No data.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>