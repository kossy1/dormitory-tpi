<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: login.php"); exit; }

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    // Check active allocations
    $active = (int)$conn->query("SELECT COUNT(*) c FROM allocations WHERE room_id=$id AND status='active'")->fetch_assoc()['c'];
    if ($active > 0) {
        $_SESSION['flash_error'] = "Cannot delete: room has $active active allocation(s).";
    } else {
        // Delete images
        $imgs = $conn->query("SELECT image_path FROM room_images WHERE room_id=$id");
        while ($img = $imgs->fetch_assoc()) {
            @unlink('../' . $img['image_path']);
        }
        $conn->query("DELETE FROM room_images WHERE room_id=$id");
        $conn->query("DELETE FROM rooms WHERE id=$id");
        $_SESSION['flash_success'] = "Room deleted successfully.";
    }
}
header("Location: manage-rooms.php");
exit;