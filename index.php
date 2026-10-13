<?php
// index.php - Public landing page
session_start(); // optional - can check if already logged in and redirect
include 'config/db.php'; // optional if you want to show stats later
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Welcome to University Dormitory Management System</title>
  <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.css">
  <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    /* ========== BASE RESET & TYPOGRAPHY ========== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      overflow-x: hidden;
      background: #f8f9fa;
    }

    /* ========== NAVBAR ========== */
    .navbar {
      background: rgba(0, 40, 85, 0.92) !important;
      backdrop-filter: blur(8px);
      padding: 12px 0;
      box-shadow: 0 2px 20px rgba(0,0,0,0.3);
    }

    .navbar-brand {
      font-weight: 700;
      font-size: 1.3rem;
      letter-spacing: 0.5px;
    }

    .navbar-brand img {
      width: 40px;
      height: 40px;
      object-fit: contain;
    }

    .navbar-nav .nav-link {
      font-weight: 500;
      padding: 8px 16px !important;
      border-radius: 8px;
      transition: all 0.3s ease;
      font-size: 0.95rem;
    }

    .navbar-nav .nav-link:hover {
      background: rgba(255,255,255,0.15);
      transform: translateY(-1px);
    }

    .navbar-toggler {
      border: 2px solid rgba(255,255,255,0.3);
      padding: 8px 10px;
    }

    .navbar-toggler:focus {
      box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.5);
    }

    /* ========== CAROUSEL ========== */
    .carousel-item {
      height: 100vh;
      min-height: 500px;
    }

    .carousel-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      filter: brightness(0.7);
    }

    .carousel-caption {
      background: rgba(0, 0, 0, 0.55);
      border-radius: 16px;
      padding: 2rem 2.5rem;
      bottom: 20%;
      max-width: 90%;
      margin: 0 auto;
      left: 5%;
      right: 5%;
      backdrop-filter: blur(4px);
      border: 1px solid rgba(255,255,255,0.1);
    }

    .carousel-caption h1 {
      font-size: clamp(1.8rem, 5vw, 4rem);
      font-weight: 800;
      margin-bottom: 0.5rem;
      text-shadow: 2px 2px 8px rgba(0,0,0,0.5);
    }

    .carousel-caption p {
      font-size: clamp(1rem, 2vw, 1.4rem);
      margin-bottom: 1.5rem;
      opacity: 0.95;
    }

    .carousel-caption .btn {
      padding: 10px 30px;
      font-weight: 600;
      border-radius: 50px;
      transition: all 0.3s ease;
      font-size: clamp(0.9rem, 1.2vw, 1.1rem);
      margin: 5px;
    }

    .carousel-caption .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(13, 110, 253, 0.4);
    }

    .carousel-caption .btn-outline-light:hover {
      background: rgba(255,255,255,0.2);
    }

    /* Carousel controls - mobile friendly */
    .carousel-control-prev,
    .carousel-control-next {
      width: 10%;
      opacity: 0.7;
    }

    .carousel-control-prev-icon,
    .carousel-control-next-icon {
      width: 40px;
      height: 40px;
      background-size: 60% 60%;
      background-color: rgba(0,0,0,0.4);
      border-radius: 50%;
      padding: 10px;
    }

    .carousel-indicators {
      bottom: 15px;
    }

    .carousel-indicators button {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      margin: 0 6px;
      border: 2px solid rgba(255,255,255,0.6);
    }

    .carousel-indicators button.active {
      background: #0d6efd;
      border-color: #0d6efd;
      width: 14px;
      height: 14px;
    }

    /* ========== QUICK INFO SECTION ========== */
    .info-section {
      padding: 60px 0;
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    .info-card {
      background: white;
      padding: 35px 25px;
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.08);
      transition: all 0.3s ease;
      height: 100%;
      border: 1px solid rgba(0,0,0,0.05);
    }

    .info-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 15px 40px rgba(0,40,85,0.12);
    }

    .info-card i {
      font-size: 3rem;
      color: #0d6efd;
      background: rgba(13, 110, 253, 0.1);
      padding: 20px;
      border-radius: 50%;
      display: inline-block;
      margin-bottom: 15px;
    }

    .info-card h4 {
      font-weight: 700;
      font-size: 1.3rem;
      margin-bottom: 10px;
      color: #1a2a4a;
    }

    .info-card p {
      color: #6c757d;
      font-size: 0.95rem;
      margin-bottom: 0;
    }

    /* ========== FOOTER ========== */
    footer {
      background: #0d1b2a;
      color: white;
      padding: 40px 0 30px;
    }

    footer p {
      margin-bottom: 5px;
      font-size: 0.95rem;
    }

    footer .text-muted {
      color: #8a9bb5 !important;
      font-size: 0.85rem;
    }

    /* ========== RESPONSIVE TWEAKS ========== */
    /* Small devices (phones, less than 768px) */
    @media (max-width: 767.98px) {
      .navbar {
        padding: 8px 0;
      }

      .navbar-brand {
        font-size: 1.1rem;
      }

      .navbar-brand img {
        width: 32px;
        height: 32px;
      }

      .navbar-nav {
        padding: 10px 0;
      }

      .navbar-nav .nav-link {
        padding: 10px 16px !important;
        text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.05);
      }

      .navbar-nav .nav-link:last-child {
        border-bottom: none;
      }

      .carousel-item {
        height: 85vh;
        min-height: 400px;
      }

      .carousel-caption {
        padding: 1.2rem 1.5rem;
        bottom: 15%;
        left: 3%;
        right: 3%;
        max-width: 94%;
        border-radius: 12px;
      }

      .carousel-caption h1 {
        font-size: 1.8rem;
      }

      .carousel-caption p {
        font-size: 0.95rem;
        margin-bottom: 1rem;
      }

      .carousel-caption .btn {
        padding: 8px 20px;
        font-size: 0.9rem;
        display: inline-block;
        width: auto;
        min-width: 120px;
      }

      .carousel-caption .btn-group-mobile {
        display: flex;
        flex-direction: column;
        gap: 8px;
        align-items: center;
      }

      .carousel-control-prev-icon,
      .carousel-control-next-icon {
        width: 32px;
        height: 32px;
        background-size: 50% 50%;
      }

      .carousel-indicators {
        bottom: 10px;
      }

      .carousel-indicators button {
        width: 10px;
        height: 10px;
        margin: 0 4px;
      }

      .info-section {
        padding: 40px 0;
      }

      .info-card {
        padding: 25px 20px;
        margin-bottom: 15px;
      }

      .info-card i {
        font-size: 2.5rem;
        padding: 15px;
      }

      .info-card h4 {
        font-size: 1.1rem;
      }

      footer {
        padding: 30px 0 20px;
      }

      footer p {
        font-size: 0.85rem;
      }
    }

    /* Medium devices (tablets, 768px to 991px) */
    @media (min-width: 768px) and (max-width: 991.98px) {
      .carousel-caption {
        padding: 1.8rem 2rem;
        bottom: 18%;
        max-width: 85%;
      }

      .carousel-caption h1 {
        font-size: 2.5rem;
      }

      .carousel-caption p {
        font-size: 1.1rem;
      }

      .info-card {
        padding: 30px 20px;
      }
    }

    /* Large devices (desktops, 992px and up) */
    @media (min-width: 992px) {
      .carousel-caption {
        max-width: 70%;
        left: 15%;
        right: 15%;
      }
    }

    /* Extra small devices (portrait phones, less than 576px) */
    @media (max-width: 575.98px) {
      .carousel-item {
        height: 80vh;
        min-height: 350px;
      }

      .carousel-caption {
        padding: 1rem 1.2rem;
        bottom: 12%;
        border-radius: 10px;
      }

      .carousel-caption h1 {
        font-size: 1.5rem;
      }

      .carousel-caption p {
        font-size: 0.85rem;
        margin-bottom: 0.8rem;
      }

      .carousel-caption .btn {
        padding: 6px 16px;
        font-size: 0.8rem;
        min-width: 100px;
      }

      .carousel-caption .btn-lg {
        font-size: 0.85rem;
        padding: 8px 20px;
      }

      .info-card i {
        font-size: 2rem;
        padding: 12px;
      }
    }

    /* ========== UTILITY CLASSES ========== */
    .text-shadow {
      text-shadow: 2px 2px 8px rgba(0,0,0,0.3);
    }

    .gap-2 {
      gap: 0.5rem;
    }

    /* Fix for mobile button wrapping */
    .btn-wrapper {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 8px;
    }

    @media (max-width: 575.98px) {
      .btn-wrapper {
        flex-direction: column;
        align-items: center;
        width: 100%;
      }
      .btn-wrapper .btn {
        width: 100%;
        max-width: 200px;
      }
    }
  </style>
</head>
<body>

<!-- ====== NAVBAR ====== -->
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <img src="assets/images/logo.png" alt="School Logo" class="me-2">
      <span>Dormitory MS</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="student/register.php"><i class="bi bi-person-plus me-1"></i> Register</a></li>
        <li class="nav-item"><a class="nav-link" href="student/login.php"><i class="bi bi-box-arrow-in-right me-1"></i> Student Login</a></li>
        <li class="nav-item"><a class="nav-link" href="admin/login.php"><i class="bi bi-shield-lock me-1"></i> Admin</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- ====== HERO CAROUSEL ====== -->
<div id="dormCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">
  <div class="carousel-inner">
    
    <!-- Slide 1 -->
    <div class="carousel-item active">
      <img src="assets/images/dorm1.jpg" class="d-block w-100" alt="Modern Hostel Room">
      <div class="carousel-caption">
        <h1>Comfortable & Secure Living</h1>
        <p>Well-furnished rooms, 24/7 security, high-speed WiFi</p>
        <div class="btn-wrapper">
          <a href="student/register.php" class="btn btn-primary btn-lg">Apply Now</a>
        </div>
      </div>
    </div>

    <!-- Slide 2 -->
    <div class="carousel-item">
      <img src="assets/images/dorm2.jpg" class="d-block w-100" alt="Student Common Area">
      <div class="carousel-caption">
        <h1>Modern Facilities</h1>
        <p>Study lounges, laundry, kitchenettes, and recreation areas</p>
        <div class="btn-wrapper">
          <a href="student/register.php" class="btn btn-primary btn-lg">Get Started</a>
        </div>
      </div>
    </div>

    <!-- Slide 3 -->
    <div class="carousel-item">
      <img src="assets/images/dorm3.jpg" class="d-block w-100" alt="Hostel Exterior">
      <div class="carousel-caption">
        <h1>Home Away From Home</h1>
        <p>Safe, affordable accommodation for university students</p>
        <div class="btn-wrapper">
          <a href="student/login.php" class="btn btn-outline-light btn-lg">Login</a>
          <a href="student/register.php" class="btn btn-primary btn-lg">Register</a>
        </div>
      </div>
    </div>

  </div>

  <!-- Controls -->
  <button class="carousel-control-prev" type="button" data-bs-target="#dormCarousel" data-bs-slide="prev">
    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Previous</span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#dormCarousel" data-bs-slide="next">
    <span class="carousel-control-next-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Next</span>
  </button>

  <!-- Indicators -->
  <div class="carousel-indicators">
    <button type="button" data-bs-target="#dormCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
    <button type="button" data-bs-target="#dormCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
    <button type="button" data-bs-target="#dormCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
  </div>
</div>

<!-- ====== QUICK INFO SECTION ====== -->
<section class="info-section">
  <div class="container">
    <div class="row g-4">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="info-card text-center">
          <i class="bi bi-building-fill"></i>
          <h4>Multiple Hostels</h4>
          <p>Boys, Girls &amp; Mixed options available</p>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="info-card text-center">
          <i class="bi bi-shield-lock-fill"></i>
          <h4>24/7 Security</h4>
          <p>CCTV, biometric access &amp; wardens on duty</p>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4 mx-md-auto">
        <div class="info-card text-center">
          <i class="bi bi-currency-naira"></i>
          <h4>Easy Online Payment</h4>
          <p>Secure Paystack integration</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ====== FOOTER ====== -->
<footer class="py-4 text-center">
  <div class="container">
    <p class="mb-1">&copy; <?= date('Y') ?> University Dormitory Management System</p>
    <p class="mb-0 text-muted">Designed with care for student comfort &bull; Powered by Bootstrap 5</p>
  </div>
</footer>

<!-- ====== SCRIPTS ====== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Optional: Auto-close mobile menu on link click -->
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    const navbarCollapse = document.getElementById('navbarNav');
    const bsCollapse = new bootstrap.Collapse(navbarCollapse, { toggle: false });
    
    navLinks.forEach(function(link) {
      link.addEventListener('click', function() {
        if (navbarCollapse.classList.contains('show')) {
          bsCollapse.hide();
        }
      });
    });
  });
</script>
</body>
</html>