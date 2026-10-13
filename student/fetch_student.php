<?php
// student/fetch_student.php
// Fetches student data by matric number for pre-enrollment.

session_start();
header('Content-Type: application/json');
include '../config/db.php';

$matric_no = trim($_GET['matric_no'] ?? '');

if (empty($matric_no)) {
    echo json_encode(['success' => false, 'message' => 'Matric number is required.']);
    exit;
}

// Fetch student by matric number
$stmt = $conn->prepare("
    SELECT s.*, 
           f.name AS faculty_name,
           d.name AS department_name
    FROM students s
    LEFT JOIN faculties f ON s.faculty_id = f.id
    LEFT JOIN departments d ON s.department_id = d.id
    WHERE s.matric_no = ?
    LIMIT 1
");
$stmt->bind_param("s", $matric_no);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'No student found with this matric number. Please contact the Student Affairs Unit.'
    ]);
    exit;
}

$student = $result->fetch_assoc();

// Check if student already has a password set (already registered)
if (!empty($student['password'])) {
    echo json_encode([
        'success' => false,
        'message' => 'This student has already completed registration. Please login instead.',
        'already_registered' => true
    ]);
    exit;
}

// Return student data (only safe fields)
echo json_encode([
    'success' => true,
    'student' => [
        'id'            => (int)$student['id'],
        'matric_no'     => $student['matric_no'],
        'full_name'     => $student['full_name'],
        'email'         => $student['email'],
        'phone'         => $student['phone'],
        'gender'        => $student['gender'],
        'level'         => $student['level'],
        'semester'      => $student['semester'],
        'faculty_name'  => $student['faculty_name'],
        'department_name' => $student['department_name'],
        'photo'         => $student['photo'],
    ]
]);