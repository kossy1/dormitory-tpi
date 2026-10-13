<?php

$host = 'localhost';
$db   = 'dormitory_tpi';
$user = 'root';
$pass = '';           // Change this on live server

// Create connection with error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db);
    
    // Set charset to utf8mb4 for better emoji/support
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    // Log error and show user-friendly message
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Show friendly error page in production
    if (php_sapi_name() !== 'cli') {
        http_response_code(503);
        die("
            <h2 style='color:red; text-align:center; margin-top:50px;'>
                ⚠️ System is temporarily unavailable<br>
                <small>Please try again later or contact administrator.</small>
            </h2>
        ");
    } else {
        die("Database Connection Failed: " . $e->getMessage());
    }
}

// Optional: Set timezone
date_default_timezone_set('Africa/Lagos');

// Helpful debug function (remove or comment in production)function db_debug($message) {


// Test connection (remove after testing)

?>