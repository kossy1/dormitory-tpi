<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="students-template.csv"');

$output = fopen('php://output', 'w');

// Header row
fputcsv($output, [
    'matric_no', 'full_name', 'surname', 'email', 'phone',
    'gender', 'faculty_id', 'department_id', 'level', 'semester'
]);

// Sample row
fputcsv($output, [
    '2024239010098', 'OKUEWU ODUNAYO GLORIA', 'OKUEWU', 'student@polytechnic.edu.ng',
    '08012345678', 'Female', '1', '1', 'ND1', '1'
]);

fclose($output);
exit;