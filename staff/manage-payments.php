<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: login.php"); exit; }

// Verify a pending payment
if (isset($_GET['verify'])) {
    $pid = (int)$_GET['verify'];
    $p = $conn->query("SELECT * FROM payments WHERE id=$pid")->fetch_assoc();
    if ($p && $p['status'] === 'pending') {
        include '../config/paystack.php';
        $secret = defined('PAYSTACK_SECRET_KEY') ? PAYSTACK_SECRET_KEY : '';
        if ($secret) {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($p['reference']),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer $secret"],
            ]);
            $resp = json_decode(curl_exec($curl), true);
            curl_close($curl);

            if (($resp['data']['status'] ?? '') === 'success') {
                $conn->query("UPDATE payments SET status='completed', paid_at=NOW() WHERE id=$pid");
                $_SESSION['flash_success'] = "Payment marked as completed.";
            } else {
                $_SESSION['flash_error'] = "Paystack did not confirm this payment.";
            }
        } else {
            $_SESSION['flash_error'] = "Paystack secret key not configured.";
        }
    }
    header("Location: manage-payments.php");
    exit;
}

$page_title = 'Manage Payments';
$page_icon = 'fa-credit-card';

$filter_status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = ["1=1"];
if (in_array($filter_status, ['pending','completed','failed'])) $where[] = "p.status = '$filter_status'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where[] = "(p.reference LIKE '%$s%' OR s.full_name LIKE '%$s%' OR s.matric_no LIKE '%$s%')";
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

$payments = $conn->query("
    SELECT p.*, s.full_name, s.matric_no, s.email
    FROM payments p
    JOIN students s ON p.student_id = s.id
    $where_sql
    ORDER BY p.created_at DESC
    LIMIT 200
");

// Totals
$total_completed = (float)($conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='completed'")->fetch_assoc()['s']);
$total_pending   = (float)($conn->query("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE status='pending'")->fetch_assoc()['s']);

include 'includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-gradient-green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <h3 style="font-size:1.3rem;">₦<?= number_format($total_completed, 0) ?></h3>
                <p>Total Collected</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-gradient-orange"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-info">
                <h3 style="font-size:1.3rem;">₦<?= number_format($total_pending, 0) ?></h3>
                <p>Pending</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon bg-gradient-blue"><i class="fas fa-receipt"></i></div>
            <div class="stat-info">
                <h3><?= $payments ? $payments->num_rows : 0 ?></h3>
                <p>Records</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-credit-card"></i>Payment Records</h3>
    </div>

    <div class="card-body" style="border-bottom:2px solid #f0f2f5;">
        <form method="GET" class="row g-2">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search reference, name, matric..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="completed" <?= $filter_status==='completed'?'selected':'' ?>>Completed</option>
                    <option value="pending" <?= $filter_status==='pending'?'selected':'' ?>>Pending</option>
                    <option value="failed" <?= $filter_status==='failed'?'selected':'' ?>>Failed</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Search</button>
            </div>
            <div class="col-md-2">
                <a href="manage-payments.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if ($payments && $payments->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Reference</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($p = $payments->fetch_assoc()): 
                        $sb = ['completed'=>'success','pending'=>'warning','failed'=>'danger'][$p['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td style="font-size:0.82rem;"><?= date('d M Y H:i', strtotime($p['created_at'])) ?></td>
                        <td>
                            <strong style="color:#1a2a4a;"><?= htmlspecialchars($p['full_name']) ?></strong>
                            <div style="font-size:0.75rem;color:#6c757d;"><?= htmlspecialchars($p['matric_no']) ?></div>
                        </td>
                        <td><code style="font-size:0.78rem;"><?= htmlspecialchars($p['reference']) ?></code></td>
                        <td><strong style="color:#28a745;">₦<?= number_format((float)$p['amount'], 2) ?></strong></td>
                        <td style="font-size:0.85rem;"><?= htmlspecialchars($p['payment_method'] ?? '—') ?></td>
                        <td><span class="badge bg-<?= $sb ?>"><?= ucfirst($p['status']) ?></span></td>
                        <td>
                            <?php if ($p['status'] === 'pending'): ?>
                                <a href="?verify=<?= $p['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Verify this payment with Paystack?')">
                                    <i class="fas fa-check"></i> Verify
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-credit-card" style="font-size:4rem;color:#d1d5db;"></i>
                <h5 class="mt-3" style="color:#1a2a4a;">No payments found</h5>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>