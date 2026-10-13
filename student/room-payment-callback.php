<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}
include '../config/db.php';
include '../config/paystack.php';

$student_id = $_SESSION['student_id'];
$reference = trim($_GET['reference'] ?? '');
$room_id = (int)($_GET['room_id'] ?? 0);

$student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();

if (empty($reference) || $room_id <= 0) {
    die("Invalid payment reference or missing room selection.");
}

// Verify with Paystack
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Cache-Control: no-cache",
    ],
]);
$response = curl_exec($curl);
curl_close($curl);
$result = json_decode($response, true);

if (!$result || !isset($result['status']) || $result['status'] !== true) {
    die("Payment verification failed. Please contact support.");
}

$payment_data = $result['data'];
$payment_status = $payment_data['status'];
$amount_paid = $payment_data['amount'] / 100;
$payment_reference = $payment_data['reference'];

if ($payment_status !== 'success') {
    die("Payment was not successful. Status: " . htmlspecialchars($payment_status));
}

// Duplicate reference check
$check = $conn->prepare("SELECT id FROM payments WHERE reference = ?");
$check->bind_param("s", $payment_reference);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    header("Location: my-room.php?already=1");
    exit;
}

// Fetch room
$room = $conn->query("
    SELECT r.*, h.name AS hostel_name 
    FROM rooms r 
    LEFT JOIN hostels h ON r.hostel_id = h.id 
    WHERE r.id = $room_id AND r.status = 'available'
    LIMIT 1
")->fetch_assoc();

if (!$room) {
    $stmt = $conn->prepare("
        INSERT INTO payments (student_id, amount, reference, status, created_at) 
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    $stmt->bind_param("ids", $student_id, $amount_paid, $payment_reference);
    $stmt->execute();
    die("Sorry, the room was taken. Your payment has been recorded. Contact Student Affairs for a refund.");
}

$current = $conn->query("SELECT COUNT(*) FROM allocations WHERE room_id = $room_id AND status = 'active'")->fetch_row()[0];
if ($current >= $room['capacity']) {
    die("This room is now full. Please contact support.");
}

// Insert payment
$stmt = $conn->prepare("
    INSERT INTO payments (student_id, amount, reference, status, created_at) 
    VALUES (?, ?, ?, 'completed', NOW())
");
$stmt->bind_param("ids", $student_id, $amount_paid, $payment_reference);
$stmt->execute();

// Insert allocation
$semester = $student['semester'] ?? '1';
$stmt = $conn->prepare("
    INSERT INTO allocations (student_id, room_id, semester, allocation_date, status) 
    VALUES (?, ?, ?, NOW(), 'active')
");
$stmt->bind_param("iis", $student_id, $room_id, $semester);
$stmt->execute();
$allocation_id = (int)$conn->insert_id;

// Update room
$new_occupancy = $current + 1;
$new_status = ($new_occupancy >= $room['capacity']) ? 'occupied' : 'available';
$conn->query("UPDATE rooms SET status = '$new_status', current_occupancy = $new_occupancy WHERE id = $room_id");

// ===== LOG FOR ADMIN =====
$admin_name = 'System (Paystack)';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
$desc = "Auto-allocated Room {$room['room_number']} to student ID $student_id — ref: $payment_reference";
$log_stmt = $conn->prepare("
    INSERT INTO activity_logs 
        (admin_id, admin_name, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
    VALUES 
        (NULL, ?, 'payment', 'allocation', ?, ?, ?, ?, NOW())
");
$log_stmt->bind_param("sisss", $admin_name, $allocation_id, $desc, $ip, $ua);
$log_stmt->execute();

$_SESSION['flash_success'] = "Payment successful! Room {$room['room_number']} at {$room['hostel_name']} has been allocated to you.";
header("Location: my-room.php?allocated=1");
exit;
?>