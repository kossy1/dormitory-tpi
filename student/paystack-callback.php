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

// Fetch student
$student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch_assoc();

if (empty($reference) || $room_id <= 0) {
    die("Invalid payment reference or missing room selection.");
}

// ===== VERIFY PAYMENT WITH PAYSTACK =====
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
$payment_status = $payment_data['status']; // 'success'
$amount_paid = $payment_data['amount'] / 100; // kobo → naira
$payment_reference = $payment_data['reference'];

if ($payment_status !== 'success') {
    die("Payment was not successful. Status: " . htmlspecialchars($payment_status));
}

// ===== CHECK FOR DUPLICATE REFERENCE =====
$check = $conn->prepare("SELECT id FROM payments WHERE reference = ?");
$check->bind_param("s", $payment_reference);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    header("Location: my-room.php?already=1");
    exit;
}

// ===== FETCH THE SELECTED ROOM =====
$room = $conn->query("
    SELECT r.*, h.name AS hostel_name 
    FROM rooms r 
    LEFT JOIN hostels h ON r.hostel_id = h.id 
    WHERE r.id = $room_id AND r.status = 'available'
    LIMIT 1
")->fetch_assoc();

if (!$room) {
    // Room was taken — record the payment but flag for admin
    $stmt = $conn->prepare("
        INSERT INTO payments (student_id, amount, reference, status, created_at) 
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    $stmt->bind_param("ids", $student_id, $amount_paid, $payment_reference);
    $stmt->execute();

    die("Sorry, the room was taken by another student before your payment completed. 
         Your payment has been recorded. Please contact the Student Affairs Unit for a refund or reallocation.");
}

// ===== CHECK ROOM ISN'T FULL =====
$current = $conn->query("SELECT COUNT(*) FROM allocations WHERE room_id = $room_id AND status = 'active'")->fetch_row()[0];
if ($current >= $room['capacity']) {
    die("This room is now full. Please contact support.");
}

// ===== CREATE PAYMENT RECORD =====
$stmt = $conn->prepare("
    INSERT INTO payments (student_id, amount, reference, status, created_at) 
    VALUES (?, ?, ?, 'completed', NOW())
");
$stmt->bind_param("ids", $student_id, $amount_paid, $payment_reference);
$stmt->execute();
$payment_id = $conn->insert_id;

// ===== CREATE ALLOCATION =====
$semester = $student['semester'] ?? '1';
$stmt = $conn->prepare("
    INSERT INTO allocations (student_id, room_id, semester, allocation_date, status) 
    VALUES (?, ?, ?, NOW(), 'active')
");
$stmt->bind_param("iis", $student_id, $room_id, $semester);
$stmt->execute();
$allocation_id = $conn->insert_id;

// ===== UPDATE ROOM STATUS =====
$new_occupancy = $current + 1;
$new_status = ($new_occupancy >= $room['capacity']) ? 'occupied' : 'available';
$conn->query("UPDATE rooms SET status = '$new_status', current_occupancy = $new_occupancy WHERE id = $room_id");

// ===== REDIRECT WITH SUCCESS =====
$_SESSION['flash_success'] = "Payment successful! Room {$room['room_number']} at {$room['hostel_name']} has been allocated to you.";
header("Location: my-room.php?allocated=1");
exit;
?>