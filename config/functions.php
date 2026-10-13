<?php
// config/functions.php

if (!function_exists('allocateRoom')) {
    function allocateRoom($student_id, $conn) {
        $student_id = (int)$student_id;

        $student = $conn->query("SELECT gender FROM students WHERE id = $student_id")->fetch_assoc();
        if (!$student) return false;

        $gender = $student['gender'];

        $query = "
            SELECT id FROM rooms 
            WHERE status IN ('available', 'partially_occupied')
              AND current_occupancy < capacity
              AND (gender = 'Mixed' OR gender = '$gender')
            ORDER BY current_occupancy ASC, price ASC 
            LIMIT 1
        ";

        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            $room = $result->fetch_assoc();
            $room_id = (int)$room['id'];

            $new_occupancy = $conn->query("SELECT current_occupancy FROM rooms WHERE id = $room_id")->fetch_row()[0] + 1;

            $conn->query("UPDATE rooms SET current_occupancy = $new_occupancy WHERE id = $room_id");

            if ($new_occupancy >= 3) {
                $conn->query("UPDATE rooms SET status = 'occupied' WHERE id = $room_id");
            } else {
                $conn->query("UPDATE rooms SET status = 'partially_occupied' WHERE id = $room_id");
            }

            $stmt = $conn->prepare("
                INSERT INTO allocations (student_id, room_id, semester, status, allocated_by)
                VALUES (?, ?, '1', 'active', 'auto')
            ");
            $stmt->bind_param("ii", $student_id, $room_id);
            $stmt->execute();

            return true;
        }
        return false;
    }
}

if (!function_exists('hasActiveAllocation')) {
    function hasActiveAllocation($student_id, $conn) {
        $student_id = (int)$student_id;
        $result = $conn->query("SELECT id FROM allocations WHERE student_id = $student_id AND status = 'active'");
        return $result->num_rows > 0;
    }
}
?>