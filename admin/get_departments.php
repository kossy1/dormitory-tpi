<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
include '../config/db.php';

header('Content-Type: application/json');

$faculty_id = (int)($_GET['faculty_id'] ?? 0);
if ($faculty_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT id, name FROM departments WHERE faculty_id = ? ORDER BY name");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode($result);