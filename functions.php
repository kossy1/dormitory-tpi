<?php
function allocateRoom($student_id, $conn) {
    $student = $conn->query("SELECT gender, level FROM students WHERE id = $student_id")->fetch_assoc();
    
    $query = "SELECT id FROM rooms 
              WHERE status = 'available' 
              AND gender IN ('Mixed', '{$student['gender']}') 
              ORDER BY price ASC LIMIT 1";
    
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        $room = $result->fetch_assoc();
        $conn->query("UPDATE rooms SET status = 'occupied' WHERE id = {$room['id']}");
        $conn->query("INSERT INTO allocations (student_id, room_id, semester) 
                      VALUES ($student_id, {$room['id']}, '1')");
        return true;
    }
    return false; // No room available
}
?>