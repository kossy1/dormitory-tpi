<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

include '../config/db.php';

$room_id = (int)($_GET['id'] ?? 0);

if ($room_id <= 0) {
    header("Location: rooms.php");
    exit;
}

// Check if room is occupied
$check = $conn->query("SELECT status FROM rooms WHERE id = $room_id")->fetch_assoc();

if ($check && $check['status'] === 'occupied') {
    $_SESSION['error'] = "Cannot delete an occupied room. Deallocate first.";
    header("Location: rooms.php");
    exit;
}

// Delete the room
if ($conn->query("DELETE FROM rooms WHERE id = $room_id")) {
    $_SESSION['success'] = "Room deleted successfully.";
} else {
    $_SESSION['error'] = "Failed to delete room.";
}

header("Location: rooms.php");
exit;
?>