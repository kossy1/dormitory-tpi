<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Dashboard - <?= htmlspecialchars($student['full_name'] ?? 'Student') ?></title>

  <!-- AdminLTE + Bootstrap -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.css">
 <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/fontawesome/css/fontawesome.css">
   <link rel="stylesheet" href="../assets/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.css">
     <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
  <style>
    body {
        background: url('../assets/images/logo.png') no-repeat center center fixed;
        background-size: 45%;
        background-color: #f4f6f9;
        opacity: 0.95;
    }
    .content-wrapper {
        background: rgba(255, 255, 255, 0.95);
        min-height: 100vh;
    }
    .welcome-card {
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
    }
  </style>
</head>