<!-- admin/sidebar.php - Responsive Mobile Sidebar -->
<div class="user-panel mt-3 pb-3 mb-3 d-flex">
  <div class="image">
    <img src="../assets/images/logo.png" class="img-circle elevation-2" alt="Admin" style="width: 38px; height: 38px;">
  </div>
  <div class="info">
    <a href="#" class="d-block">Administrator</a>
    <small class="text-muted">Dormitory MS</small>
  </div>
</div>

<nav class="mt-2">
  <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

    <li class="nav-item">
      <a href="index.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
        <i class="nav-icon fas fa-tachometer-alt"></i>
        <p>Dashboard</p>
      </a>
    </li>

    <li class="nav-item">
      <a href="students.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : '' ?>">
        <i class="nav-icon fas fa-user-graduate"></i>
        <p>Students</p>
      </a>
    </li>

    <li class="nav-item">
      <a href="rooms.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'rooms.php' ? 'active' : '' ?>">
        <i class="nav-icon fas fa-bed"></i>
        <p>Manage Rooms</p>
      </a>
    </li>

    <li class="nav-item">
      <a href="allocations.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'allocations.php' ? 'active' : '' ?>">
        <i class="nav-icon fas fa-list-ul"></i>
        <p>Allocations</p>
      </a>
    </li>

    <li class="nav-item">
      <a href="allocate-room.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'allocate-room.php' ? 'active' : '' ?>">
        <i class="nav-icon fas fa-hand-point-right"></i>
        <p>Manual Allocation</p>
      </a>
    </li>

    <li class="nav-item">
      <a href="manage-payments.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'manage-payments.php' ? 'active' : '' ?>">
        <i class="nav-icon fas fa-credit-card"></i>
        <p>Payments</p>
      </a>
    </li>

    <!-- Grouped Requests -->
    <li class="nav-item has-treeview">
      <a href="#" class="nav-link">
        <i class="nav-icon fas fa-clipboard-list"></i>
        <p>
          Requests
          <i class="right fas fa-angle-left"></i>
        </p>
      </a>
      <ul class="nav nav-treeview">
        <li class="nav-item">
          <a href="complaints.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'complaints.php' ? 'active' : '' ?>">
            <i class="far fa-circle nav-icon"></i>
            <p>Complaints</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="maintenance.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'maintenance.php' ? 'active' : '' ?>">
            <i class="far fa-circle nav-icon"></i>
            <p>Maintenance</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="refunds.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'refunds.php' ? 'active' : '' ?>">
            <i class="far fa-circle nav-icon"></i>
            <p>Refunds</p>
          </a>
        </li>
        <li class="nav-item">
  <a href="add_department.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'add_department.php' ? 'active' : '' ?>">
    <i class="nav-icon fas fa-plus-circle"></i>
    <p>Add Department</p>
  </a>
</li>
        <li class="nav-item">
          <a href="room-changes.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'room-changes.php' ? 'active' : '' ?>">
            <i class="far fa-circle nav-icon"></i>
            <p>Room Changes</p>
          </a>
        </li>
      </ul>
    </li>

    <li class="nav-item mt-4">
      <a href="logout.php" class="nav-link text-danger">
        <i class="nav-icon fas fa-sign-out-alt"></i>
        <p>Logout</p>
      </a>
    </li>
  </ul>
</nav>