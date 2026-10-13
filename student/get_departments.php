<?php
include '../config/db.php';

if (isset($_GET['faculty_id'])) {
    $fid = (int)$_GET['faculty_id'];
    $result = $conn->query("SELECT id, name FROM departments WHERE faculty_id = $fid ORDER BY name");
    $depts = $result->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($depts);
}
?>