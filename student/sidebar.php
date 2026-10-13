<!-- student/sidebar.php - Fully Responsive Mobile Sidebar -->
<style>
/* ========== SIDEBAR RESPONSIVE STYLES ========== */
.sidebar-wrapper {
  position: relative;
  min-height: 100vh;
}

/* Mobile toggle button - visible only on small screens */
.sidebar-toggle-btn {
  display: none;
  position: fixed;
  top: 10px;
  left: 10px;
  z-index: 1050;
  background: #1a3a6b;
  color: white;
  border: none;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 1.2rem;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(0,0,0,0.2);
  transition: all 0.3s ease;
}

.sidebar-toggle-btn:hover {
  background: #0d1b3e;
  transform: scale(1.05);
}

.sidebar-toggle-btn i {
  font-size: 1.3rem;
}

/* Overlay for mobile */
.sidebar-overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
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

/* Sidebar container */
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
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 2px 0 20px rgba(0,0,0,0.15);
  padding: 0;
}

/* Sidebar scrollbar styling */
.main-sidebar::-webkit-scrollbar {
  width: 4px;
}

.main-sidebar::-webkit-scrollbar-track {
  background: rgba(255,255,255,0.05);
}

.main-sidebar::-webkit-scrollbar-thumb {
  background: rgba(255,255,255,0.2);
  border-radius: 4px;
}

/* Sidebar header/brand */
.sidebar-brand {
  padding: 20px 18px 15px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
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

/* User panel */
.user-panel {
  padding: 18px 20px 15px !important;
  border-bottom: 1px solid rgba(255,255,255,0.08);
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
  border: 2px solid rgba(255,255,255,0.2);
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
  color: rgba(255,255,255,0.6);
  font-size: 0.75rem;
  display: block;
}

/* Navigation */
.nav-sidebar {
  padding: 8px 0;
}

.nav-sidebar .nav-item {
  margin: 2px 8px;
}

.nav-sidebar .nav-link {
  color: rgba(255,255,255,0.75);
  padding: 10px 16px;
  border-radius: 10px;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 12px;
}

.nav-sidebar .nav-link:hover {
  color: white;
  background: rgba(255,255,255,0.1);
  transform: translateX(4px);
}

.nav-sidebar .nav-link.active {
  color: white;
  background: rgba(255,215,0,0.15);
  border-left: 3px solid #ffd700;
}

.nav-sidebar .nav-link .nav-icon {
  width: 22px;
  text-align: center;
  font-size: 1rem;
  color: rgba(255,255,255,0.6);
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

/* Treeview (submenu) */
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
  color: rgba(255,255,255,0.6);
}

.nav-treeview .nav-link:hover {
  color: white;
  background: rgba(255,255,255,0.05);
}

.nav-treeview .nav-link.active {
  color: #ffd700;
  background: rgba(255,215,0,0.08);
}

.nav-treeview .nav-link .far.fa-circle {
  font-size: 0.5rem;
  margin-right: 10px;
}

/* Logout link */
.nav-sidebar .nav-link.text-danger {
  color: #ff6b6b !important;
}

.nav-sidebar .nav-link.text-danger:hover {
  background: rgba(255,107,107,0.15) !important;
  color: #ff6b6b !important;
}

.nav-sidebar .nav-link.text-danger .nav-icon {
  color: #ff6b6b !important;
}

/* Close button for mobile */
.sidebar-close-btn {
  display: none;
  position: absolute;
  top: 12px;
  right: 12px;
  background: rgba(255,255,255,0.1);
  color: white;
  border: none;
  border-radius: 50%;
  width: 32px;
  height: 32px;
  font-size: 1.2rem;
  cursor: pointer;
  transition: all 0.3s ease;
  z-index: 1041;
}

.sidebar-close-btn:hover {
  background: rgba(255,255,255,0.2);
  transform: rotate(90deg);
}

/* ========== RESPONSIVE BREAKPOINTS ========== */

/* Mobile & Tablets (≤ 991px) - Toggle sidebar */
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

  .sidebar-close-btn {
    display: block;
  }

  .sidebar-brand {
    padding: 15px 16px 12px;
  }

  .sidebar-brand .brand-text {
    font-size: 1rem;
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
    font-size: 0.9rem;
  }

  .nav-treeview .nav-link {
    padding: 6px 14px 6px 30px;
    font-size: 0.8rem;
  }

  /* Push content when sidebar is open on mobile */
  body.sidebar-open {
    overflow: hidden;
  }
}

/* Small Mobile (≤ 576px) */
@media (max-width: 576px) {
  .sidebar-toggle-btn {
    top: 8px;
    left: 8px;
    padding: 8px 12px;
    font-size: 1rem;
    border-radius: 6px;
  }

  .sidebar-toggle-btn i {
    font-size: 1.1rem;
  }

  .main-sidebar {
    width: 260px;
    max-width: 90%;
  }

  .sidebar-brand {
    padding: 12px 14px 10px;
  }

  .sidebar-brand .brand-text {
    font-size: 0.9rem;
  }

  .sidebar-brand img {
    width: 32px;
    height: 32px;
  }

  .user-panel {
    padding: 12px 14px 10px !important;
    gap: 10px;
  }

  .user-panel .image img {
    width: 32px;
    height: 32px;
  }

  .user-panel .info a {
    font-size: 0.8rem;
  }

  .user-panel .info small {
    font-size: 0.65rem;
  }

  .nav-sidebar .nav-item {
    margin: 1px 6px;
  }

  .nav-sidebar .nav-link {
    padding: 7px 12px;
    font-size: 0.85rem;
    border-radius: 8px;
    gap: 10px;
  }

  .nav-sidebar .nav-link .nav-icon {
    width: 18px;
    font-size: 0.9rem;
  }

  .nav-sidebar .nav-link p {
    font-size: 0.85rem;
  }

  .nav-treeview {
    padding-left: 10px;
  }

  .nav-treeview .nav-link {
    padding: 5px 12px 5px 25px;
    font-size: 0.75rem;
  }

  .sidebar-close-btn {
    top: 8px;
    right: 8px;
    width: 28px;
    height: 28px;
    font-size: 1rem;
  }
}

/* Extra Small Mobile (≤ 400px) */
@media (max-width: 400px) {
  .main-sidebar {
    width: 240px;
    max-width: 92%;
  }

  .sidebar-brand .brand-text {
    font-size: 0.8rem;
  }

  .sidebar-brand img {
    width: 28px;
    height: 28px;
  }

  .user-panel .info a {
    font-size: 0.75rem;
  }

  .nav-sidebar .nav-link {
    padding: 6px 10px;
    font-size: 0.8rem;
    gap: 8px;
  }

  .nav-sidebar .nav-link .nav-icon {
    width: 16px;
    font-size: 0.8rem;
  }

  .nav-sidebar .nav-link p {
    font-size: 0.8rem;
  }
}

/* Desktop (≥ 992px) - Always show sidebar */
@media (min-width: 992px) {
  .main-sidebar {
    transform: translateX(0) !important;
  }

  .sidebar-toggle-btn {
    display: none !important;
  }

  .sidebar-close-btn {
    display: none !important;
  }

  .sidebar-overlay {
    display: none !important;
  }

  /* Adjust main content margin when sidebar is visible */
  .content-wrapper {
    margin-left: 280px;
    transition: margin-left 0.3s ease;
  }
}

/* ========== UTILITY ========== */
.sidebar-hidden {
  overflow: hidden;
}

/* Smooth scroll behavior */
.main-sidebar {
  scroll-behavior: smooth;
}
</style>

<!-- ====== SIDEBAR TOGGLE BUTTON (Mobile) ====== -->
<button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle Sidebar">
  <i class="fas fa-bars"></i>
</button>

<!-- ====== SIDEBAR OVERLAY (Mobile) ====== -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ====== MAIN SIDEBAR ====== -->
<aside class="main-sidebar" id="mainSidebar">
  
  <!-- Close button (mobile only) -->
  <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close Sidebar">
    <i class="fas fa-times"></i>
  </button>

  <!-- Brand/Logo -->
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
      if ($photo && file_exists('../'.$photo)): ?>
        <img src="../<?= htmlspecialchars($photo) ?>" class="img-circle elevation-2" alt="Profile" style="width:38px; height:38px; object-fit:cover;">
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
    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

      <!-- Dashboard -->
      <li class="nav-item">
        <a href="dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
          <i class="nav-icon fas fa-tachometer-alt"></i>
          <p>Dashboard</p>
        </a>
      </li>

      <li class="nav-item">
    <a href="browse-rooms.php" class="nav-link">
        <i class="nav-icon fas fa-search"></i>
        <p>Browse Rooms</p>
    </a>
</li>

      <!-- My Room -->
      <li class="nav-item">
        <a href="my-room.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'my-room.php' ? 'active' : '' ?>">
          <i class="nav-icon fas fa-bed"></i>
          <p>My Room</p>
        </a>
      </li>

      <!-- My Profile -->
      <li class="nav-item">
        <a href="profile.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
          <i class="nav-icon fas fa-user"></i>
          <p>My Profile</p>
        </a>
      </li>

      <!-- Payment -->
      <li class="nav-item">
        <a href="payment.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'payment.php' ? 'active' : '' ?>">
          <i class="nav-icon fas fa-credit-card"></i>
          <p>Payment</p>
        </a>
      </li>

      <!-- Requests Group -->
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

      <!-- Logout -->
      <li class="nav-item mt-4">
        <a href="logout.php" class="nav-link text-danger">
          <i class="nav-icon fas fa-sign-out-alt"></i>
          <p>Logout</p>
        </a>
      </li>
    </ul>
  </nav>
</aside>

<!-- ====== JAVASCRIPT ====== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // ====== ELEMENTS ======
  const sidebar = document.getElementById('mainSidebar');
  const toggleBtn = document.getElementById('sidebarToggle');
  const closeBtn = document.getElementById('sidebarClose');
  const overlay = document.getElementById('sidebarOverlay');
  const body = document.body;

  // ====== OPEN SIDEBAR ======
  function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('active');
    body.classList.add('sidebar-open');
    body.style.overflow = 'hidden';
    
    // Optional: change toggle icon
    if (toggleBtn) {
      toggleBtn.innerHTML = '<i class="fas fa-times"></i>';
    }
  }

  // ====== CLOSE SIDEBAR ======
  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    body.classList.remove('sidebar-open');
    body.style.overflow = '';
    
    // Restore toggle icon
    if (toggleBtn) {
      toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
    }
  }

  // ====== TOGGLE SIDEBAR ======
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
  const treeviewLinks = document.querySelectorAll('.has-treeview > .nav-link');
  treeviewLinks.forEach(function(link) {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      
      const parent = this.closest('.has-treeview');
      const submenu = parent.querySelector('.nav-treeview');
      
      // Close other open treeviews
      const allParents = document.querySelectorAll('.has-treeview');
      allParents.forEach(function(item) {
        if (item !== parent) {
          item.classList.remove('menu-open');
          const otherSubmenu = item.querySelector('.nav-treeview');
          if (otherSubmenu) {
            otherSubmenu.classList.remove('show');
          }
        }
      });
      
      // Toggle current
      parent.classList.toggle('menu-open');
      if (submenu) {
        submenu.classList.toggle('show');
      }
    });
  });

  // ====== CLOSE SIDEBAR ON NAVIGATION (Mobile) ======
  const navLinks = document.querySelectorAll('.nav-sidebar .nav-link:not(.has-treeview > .nav-link)');
  navLinks.forEach(function(link) {
    link.addEventListener('click', function() {
      // Only close on mobile
      if (window.innerWidth <= 991.98) {
        setTimeout(closeSidebar, 300);
      }
    });
  });

  // ====== HANDLE WINDOW RESIZE ======
  let resizeTimer;
  window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
      // If window becomes desktop size, ensure sidebar is visible
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

  // ====== KEYBOARD SHORTCUT (ESC to close) ======
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && sidebar.classList.contains('open')) {
      closeSidebar();
    }
  });

  // ====== PREVENT SCROLLING ON MOBILE WHEN SIDEBAR OPEN ======
  // Already handled via body overflow hidden

  console.log('✅ Responsive Sidebar initialized successfully');
});
</script>