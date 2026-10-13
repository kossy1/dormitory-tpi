<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';

$success = $error = '';
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

// Build query with filters
$where = [];
$params = [];
$types = "";

if ($status_filter) {
    $where[] = "p.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($search) {
    $where[] = "(s.full_name LIKE ? OR s.matric_no LIKE ?)";
    $search_like = "%$search%";
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= "ss";
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$query = "
    SELECT p.*, s.full_name, s.matric_no, s.email,
           r.room_number
    FROM payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN allocations a ON p.student_id = a.student_id AND a.status = 'active'
    LEFT JOIN rooms r ON a.room_id = r.id
    $where_clause
    ORDER BY p.created_at DESC
";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Summary statistics
$total_payments = $conn->query("SELECT COUNT(*) FROM payments")->fetch_row()[0];
$successful_payments = $conn->query("SELECT COUNT(*) FROM payments WHERE status = 'success'")->fetch_row()[0];
$total_amount = $conn->query("SELECT SUM(amount) FROM payments WHERE status = 'success'")->fetch_row()[0] ?? 0;
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
        <h1>Payment Management</h1>
      </div>
    </div>

    <section class="content">
      <div class="container-fluid">

        <!-- Summary Cards -->
        <div class="row">
          <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
              <div class="inner">
                <h3><?= number_format($total_payments) ?></h3>
                <p>Total Payments</p>
              </div>
              <div class="icon"><i class="fa fa-credit-card"></i></div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
              <div class="inner">
                <h3><?= number_format($successful_payments) ?></h3>
                <p>Successful Payments</p>
              </div>
              <div class="icon"><i class="fa fa-check-circle"></i></div>
            </div>
          </div>
          <div class="col-lg-3 col-6">
            <div class="small-box bg-primary">
              <div class="inner">
                <h3>₦<?= number_format($total_amount) ?></h3>
                <p>Total Revenue</p>
              </div>
              <div class="icon"><i class="fa fa-naira-sign"></i></div>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">All Payments</h3>
            <div class="card-tools">
              <form method="GET" class="form-inline">
                <select name="status" class="form-control mr-2" onchange="this.form.submit()">
                  <option value="">All Status</option>
                  <option value="success" <?= $status_filter=='success'?'selected':'' ?>>Success</option>
                  <option value="pending" <?= $status_filter=='pending'?'selected':'' ?>>Pending</option>
                  <option value="failed"  <?= $status_filter=='failed'?'selected':'' ?>>Failed</option>
                </select>
                <input type="text" name="search" class="form-control mr-2" placeholder="Search name or matric" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-default"><i class="fa fa-search"></i></button>
              </form>
            </div>
          </div>

          <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Student</th>
                  <th>Matric No</th>
                  <th>Reference</th>
                  <th>Amount (₦)</th>
                  <th>Room</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($payments)): ?>
                <tr><td colspan="7" class="text-center py-4">No payments found.</td></tr>
                <?php else: ?>
                  <?php foreach ($payments as $p): ?>
                  <tr>
                    <td><?= date('d M Y H:i', strtotime($p['created_at'])) ?></td>
                    <td><?= htmlspecialchars($p['full_name']) ?></td>
                    <td><strong><?= htmlspecialchars($p['matric_no']) ?></strong></td>
                    <td><?= htmlspecialchars($p['reference']) ?></td>
                    <td><strong>₦<?= number_format($p['amount'], 2) ?></strong></td>
                    <td><?= htmlspecialchars($p['room_number'] ?? 'N/A') ?></td>
                    <td>
                      <span class="badge bg-<?= $p['status'] === 'success' ? 'success' : ($p['status'] === 'pending' ? 'warning' : 'danger') ?>">
                        <?= ucfirst($p['status']) ?>
                      </span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>
  </div>

<?php include 'footer.php'; ?>