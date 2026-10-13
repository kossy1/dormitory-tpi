<?php
session_start();

// Regenerate session ID to prevent fixation
session_regenerate_id(true);

// Clear all session data
$_SESSION = [];

// Destroy session
session_destroy();

// Redirect
header("Location: ../student/login.php"); // adjust path for student/admin
exit;
?>