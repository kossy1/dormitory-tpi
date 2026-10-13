<!-- student/layout.php - Master Layout with Responsive Sidebar -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?? 'Student Dashboard' ?> - Dormitory MS</title>
  
  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  
  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <style>
    /* ========== GLOBAL RESET ========== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f4f6f9;
      overflow-x: hidden;
    }

    /* ========== SIDEBAR TOGGLE BUTTON ========== */
    .sidebar-toggle-btn {
      position: fixed;
      top: 12px;
      left: 12px;
      z-index: 1050;
      background: #1a3a6b;
      color: white;
      border: none;
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 1.2rem;
      cursor: pointer;
      box-shadow: 0 4px 20px rgba(26, 58, 107, 0.3);
      transition: all 0.3s ease;
      display: none;
    }

    .sidebar-toggle-btn:hover {
      background: #0d1b3e;
      transform: scale(1.05);
      box-shadow: 0 6px 25px rgba(26, 58, 107, 0.4);
    }

    .sidebar-toggle-btn:active {
      transform: scale(0.95);
    }

    .sidebar-toggle-btn i {
      font-size: 1.4rem;
    }

    /* ========== SIDEBAR OVERLAY ========== */
    .sidebar-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1039;
      animation: fadeIn 0.3s ease;
    }

    .sidebar-overlay.active {
      display: block;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    /* ========== MAIN SIDEBAR ========== */
    .main-sidebar {
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
      width: 280px;
      background: linear-gradient(180deg, #1a3a6b 0%, #0d1b3e 100%);
      z-index: 1040;
      overflow-y: auto;
      overflow-x: hidden;
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 2px 0 30px rgba(0, 0, 0, 0.15);
      padding: 0;
      transform: translateX(-100%);
    }

    .main-sidebar.open {
      transform: translateX(0);
    }

    /* Desktop - always visible */
    @media (min-width: 992px) {
      .main-sidebar {
        transform: translateX(0) !important;
      }
      
      .sidebar-toggle-btn {
        display: none !important;
      }
      
      .sidebar-overlay {
        display: none !important;
      }
      
      .content-wrapper {
        margin-left: 280px;
        transition: margin-left 0.3s ease;
      }
    }

    /* Mobile - hidden by default, shown with toggle */
    @media (max-width: 991.98px) {
      .sidebar-toggle-btn {
        display: block;
      }
      
      .main-sidebar {
        transform: translateX(-100%);
        width: 280px;
        max-width: 85%;
      }
      
      .main-sidebar.open {
        transform: translateX(0);
      }
      
      .content-wrapper {
        margin-left: 0;
        padding-top: 70px;
      }
    }

    /* ========== SIDEBAR SCROLLBAR ========== */
    .main-sidebar::-webkit-scrollbar {
      width: 4px;
    }

    .main-sidebar::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, 0.05);
    }

    .main-sidebar::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.2);
      border-radius: 4px;
    }

    /* ========== SIDEBAR BRAND ========== */
    .sidebar-brand {
      padding: 20px 18px 15px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .sidebar-brand img {
      width: 45px;
      height: 45px;
      object-fit: contain;
      background: white;
      padding: 5px;
      border-radius: 10px;
    }

    .sidebar-brand .brand-text {
      color: white;
      font-weight: 700;
      font-size: 1.1rem;
      line-height: 1.2;
    }

    .sidebar-brand .brand-text small {
      display: block;
      font-weight: 300;
      font-size: 0.7rem;
      opacity: 0.7;
    }

    /* ========== SIDEBAR CLOSE BUTTON (mobile) ========== */
    .sidebar-close-btn {
      display: none;
      position: absolute;
      top: 12px;
      right: 12px;
      background: rgba(255, 255, 255, 0.1);
      color: white;
      border: none;
      border-radius: 50%;
      width: 36px;
      height: 36px;
      font-size: 1.2rem;
      cursor: pointer;
      transition: all 0.3s ease;
      z-index: 1041;
    }

    .sidebar-close-btn:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: rotate(90deg);
    }

    @media (max-width: 991.98px) {
      .sidebar-close-btn {
        display: block;
      }
    }

    /* ========== USER PANEL ========== */
    .user-panel {
      padding: 18px 20px 15px !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      display: flex !important;
      align-items: center;
      gap: 12px;
      margin: 0 !important;
    }

    .user-panel .image img {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid rgba(255, 255, 255, 0.2);
    }

    .user-panel .info {
      flex: 1;
    }

    .user-panel .info a {
      color: white;
      font-weight: 600;
      font-size: 0.95rem;
      text-decoration: none;
      transition: color 0.3s ease;
    }

    .user-panel .info a:hover {
      color: #ffd700;
    }

    .user-panel .info small {
      color: rgba(255, 255, 255, 0.6);
      font-size: 0.75rem;
      display: block;
    }

    /* ========== NAVIGATION ========== */
    .nav-sidebar {
      padding: 8px 0;
    }

    .nav-sidebar .nav-item {
      margin: 2px 8px;
    }

    .nav-sidebar .nav-link {
      color: rgba(255, 255, 255, 0.75);
      padding: 10px 16px;
      border-radius: 10px;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
    }

    .nav-sidebar .nav-link:hover {
      color: white;
      background: rgba(255, 255, 255, 0.1);
      transform: translateX(4px);
    }

    .nav-sidebar .nav-link.active {
      color: white;
      background: rgba(255, 215, 0, 0.15);
      border-left: 3px solid #ffd700;
    }

    .nav-sidebar .nav-link .nav-icon {
      width: 22px;
      text-align: center;
      font-size: 1rem;
      color: rgba(255, 255, 255, 0.6);
    }

    .nav-sidebar .nav-link.active .nav-icon {
      color: #ffd700;
    }

    .nav-sidebar .nav-link p {
      margin: 0;
      font-size: 0.9rem;
      font-weight: 500;
      flex: 1;
    }

    /* ========== TREEVIEW ========== */
    .nav-sidebar .has-treeview > .nav-link {
      position: relative;
    }

    .nav-sidebar .has-treeview > .nav-link .right {
      transition: transform 0.3s ease;
    }

    .nav-sidebar .has-treeview.menu-open > .nav-link .right {
      transform: rotate(-90deg);
    }

    .nav-treeview {
      padding-left: 15px;
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease;
    }

    .nav-treeview.show {
      max-height: 500px;
    }

    .nav-treeview .nav-link {
      padding: 8px 16px 8px 35px;
      font-size: 0.85rem;
      color: rgba(255, 255, 255, 0.6);
    }

    .nav-treeview .nav-link:hover {
      color: white;
      background: rgba(255, 255, 255, 0.05);
    }

    .nav-treeview .nav-link.active {
      color: #ffd700;
      background: rgba(255, 215, 0, 0.08);
    }

    .nav-treeview .nav-link .far.fa-circle {
      font-size: 0.5rem;
      margin-right: 10px;
    }

    /* ========== LOGOUT ========== */
    .nav-sidebar .nav-link.text-danger {
      color: #ff6b6b !important;
    }

    .nav-sidebar .nav-link.text-danger:hover {
      background: rgba(255, 107, 107, 0.15) !important;
      color: #ff6b6b !important;
    }

    .nav-sidebar .nav-link.text-danger .nav-icon {
      color: #ff6b6b !important;
    }

    /* ========== CONTENT WRAPPER ========== */
    .content-wrapper {
      min-height: 100vh;
      padding: 20px;
      transition: margin-left 0.3s ease;
    }

    /* ========== RESPONSIVE FINE-TUNING ========== */
    @media (max-width: 576px) {
      .sidebar-toggle-btn {
        top: 8px;
        left: 8px;
        padding: 10px 14px;
        font-size: 1rem;
        border-radius: 8px;
      }

      .sidebar-toggle-btn i {
        font-size: 1.2rem;
      }

      .main-sidebar {
        width: 260px;
        max-width: 90%;
      }

      .sidebar-brand {
        padding: 15px 14px 12px;
      }

      .sidebar-brand .brand-text {
        font-size: 0.95rem;
      }

      .sidebar-brand img {
        width: 38px;
        height: 38px;
      }

      .user-panel {
        padding: 14px 16px 12px !important;
      }

      .user-panel .image img {
        width: 36px;
        height: 36px;
      }

      .user-panel .info a {
        font-size: 0.85rem;
      }

      .nav-sidebar .nav-link {
        padding: 8px 14px;
        font-size: 0.85rem;
      }

      .content-wrapper {
        padding: 15px;
        padding-top: 70px;
      }

      .sidebar-close-btn {
        top: 8px;
        right: 8px;
        width: 32px;
        height: 32px;
        font-size: 1rem;
      }
    }

    @media (max-width: 400px) {
      .main-sidebar {
        width: 240px;
        max-width: 92%;
      }

      .sidebar-brand .brand-text {
        font-size: 0.85rem;
      }

      .sidebar-brand img {
        width: 32px;
        height: 32px;
      }

      .nav-sidebar .nav-link {
        padding: 6px 12px;
        font-size: 0.8rem;
        gap: 10px;
      }

      .nav-sidebar .nav-link .nav-icon {
        width: 18px;
        font-size: 0.85rem;
      }

      .content-wrapper {
        padding: 10px;
        padding-top: 65px;
      }
    }

    /* ========== BODY SCROLL LOCK ========== */
    body.sidebar-open {
      overflow: hidden;
    }
  </style>
</head>
<body>

<!-- ====== SIDEBAR TOGGLE BUTTON ====== -->
<button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle Navigation Menu">
  <i class="fas fa-bars"></i>
</button>

<!-- ====== SIDEBAR OVERLAY ====== -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ====== MAIN SIDEBAR ====== -->
<aside class="main-sidebar" id="mainSidebar">
  
  <!-- Close button -->
  <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close Sidebar">
    <i class="fas fa-times"></i>
  </button>

  <!-- Brand -->
  <div class="sidebar-brand">
    <img src="../assets/images/logo.png" alt="Logo" onerror="this.src='https://via.placeholder.com/45x45/ffffff/1a3a6b?text=PI'">
    <div class="brand-text">
      Dormitory MS
      <small>The Polytechnic Ibadan</small>
    </div>
  </div>

  <!-- User Panel -->
  <div class="user-panel">
    <div class="image">
      <?php 
      $photo = $student['photo'] ?? '';
      if ($photo && file_exists('../' . $photo)): ?>
        <img src="../<?= htmlspecialchars($photo) ?>" alt="Profile">
      <?php else: ?>
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($student['full_name'] ?? 'Student') ?>&background=ffd700&color=1a3a6b&size=42" alt="Profile">
      <?php endif; ?>
    </div>
    <div class="info">
      <a href="profile.php"><?= htmlspecialchars($student['full_name'] ?? 'Student') ?></a>
      <small><?= htmlspecialchars($student['matric_no'] ?? '') ?></small>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="mt-2">
    <ul class="nav nav-pills nav-sidebar flex-column" role="menu">
      
      <li class="nav-item">
        <a href="dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
          <i class="nav-icon fas fa-tachometer-alt"></i>
          <p>Dashboard</p>
        </a>
      </li>

      <li class="nav-item">
        <a href="my-room.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'my-room.php' ? 'active' : '' ?>">
          <i class="nav-icon fas fa-bed"></i>
          <p>My Room</p>
        </a>
      </li>

      <li class="nav-item">
        <a href="profile.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
          <i class="nav-icon fas fa-user"></i>
          <p>My Profile</p>
        </a>
      </li>

      <li class="nav-item">
        <a href="payment.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'payment.php' ? 'active' : '' ?>">
          <i class="nav-icon fas fa-credit-card"></i>
          <p>Payment</p>
        </a>
      </li>

      <!-- Requests Dropdown -->
      <li class="nav-item has-treeview <?= (in_array(basename($_SERVER['PHP_SELF']), ['complaints.php', 'maintenance.php', 'refund.php', 'room-change.php'])) ? 'menu-open' : '' ?>">
        <a href="#" class="nav-link">
          <i class="nav-icon fas fa-clipboard-list"></i>
          <p>
            Requests
            <i class="right fas fa-angle-left"></i>
          </p>
        </a>
        <ul class="nav nav-treeview <?= (in_array(basename($_SERVER['PHP_SELF']), ['complaints.php', 'maintenance.php', 'refund.php', 'room-change.php'])) ? 'show' : '' ?>">
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
            <a href="refund.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'refund.php' ? 'active' : '' ?>">
              <i class="far fa-circle nav-icon"></i>
              <p>Refund</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="room-change.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'room-change.php' ? 'active' : '' ?>">
              <i class="far fa-circle nav-icon"></i>
              <p>Room Change</p>
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
</aside>

<!-- ====== MAIN CONTENT ====== -->
<div class="content-wrapper">
  <!-- PAGE CONTENT GOES HERE -->
  <?php include $content ?? ''; ?>
</div>

<!-- ====== SCRIPTS ====== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // ====== ELEMENTS ======
  const sidebar = document.getElementById('mainSidebar');
  const toggleBtn = document.getElementById('sidebarToggle');
  const closeBtn = document.getElementById('sidebarClose');
  const overlay = document.getElementById('sidebarOverlay');
  const body = document.body;

  // ====== FUNCTIONS ======
  function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('active');
    body.classList.add('sidebar-open');
    body.style.overflow = 'hidden';
    if (toggleBtn) {
      toggleBtn.innerHTML = '<i class="fas fa-times"></i>';
    }
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    body.classList.remove('sidebar-open');
    body.style.overflow = '';
    if (toggleBtn) {
      toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
    }
  }

  function toggleSidebar() {
    if (sidebar.classList.contains('open')) {
      closeSidebar();
    } else {
      openSidebar();
    }
  }

  // ====== EVENT LISTENERS ======
  if (toggleBtn) {
    toggleBtn.addEventListener('click', toggleSidebar);
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', closeSidebar);
  }

  if (overlay) {
    overlay.addEventListener('click', closeSidebar);
  }

  // ====== TREEVIEW TOGGLE ======
  document.querySelectorAll('.has-treeview > .nav-link').forEach(function(link) {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      const parent = this.closest('.has-treeview');
      const submenu = parent.querySelector('.nav-treeview');
      
      // Close others
      document.querySelectorAll('.has-treeview').forEach(function(item) {
        if (item !== parent) {
          item.classList.remove('menu-open');
          const otherSub = item.querySelector('.nav-treeview');
          if (otherSub) otherSub.classList.remove('show');
        }
      });
      
      // Toggle current
      parent.classList.toggle('menu-open');
      if (submenu) submenu.classList.toggle('show');
    });
  });

  // ====== CLOSE ON NAVIGATION (mobile only) ======
  document.querySelectorAll('.nav-sidebar .nav-link:not(.has-treeview > .nav-link)').forEach(function(link) {
    link.addEventListener('click', function() {
      if (window.innerWidth <= 991.98) {
        setTimeout(closeSidebar, 300);
      }
    });
  });

  // ====== HANDLE RESIZE ======
  let resizeTimer;
  window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
      if (window.innerWidth >= 992) {
        closeSidebar();
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        body.classList.remove('sidebar-open');
        body.style.overflow = '';
        if (toggleBtn) {
          toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
        }
      }
    }, 250);
  });

  // ====== ESC KEY ======
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && sidebar.classList.contains('open')) {
      closeSidebar();
    }
  });

  console.log('✅ Responsive Sidebar initialized');
});
</script>

</body>
</html>