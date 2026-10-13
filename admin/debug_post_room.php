<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
include '../config/db.php';

echo "<h2>🔍 Post Room Diagnostic</h2>";
echo "<pre style='background:#1e1e1e;color:#4af626;padding:20px;border-radius:10px;'>";

// 1. Check rooms table
echo "=== ROOMS TABLE ===\n";
$cols = $conn->query("SHOW COLUMNS FROM rooms");
$room_cols = [];
while ($c = $cols->fetch_assoc()) {
    $room_cols[] = $c['Field'];
    echo "  - {$c['Field']} ({$c['Type']}) " . ($c['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}
echo "\n";

// 2. Check room_images table
echo "=== ROOM_IMAGES TABLE ===\n";
$check = $conn->query("SHOW TABLES LIKE 'room_images'");
if ($check->num_rows === 0) {
    echo "  ❌ TABLE DOES NOT EXIST!\n";
    echo "  Run this SQL:\n\n";
    echo "  CREATE TABLE room_images (\n";
    echo "      id INT AUTO_INCREMENT PRIMARY KEY,\n";
    echo "      room_id INT NOT NULL,\n";
    echo "      image_path VARCHAR(255) NOT NULL,\n";
    echo "      is_primary TINYINT(1) DEFAULT 0,\n";
    echo "      sort_order INT DEFAULT 0,\n";
    echo "      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
    echo "      FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE\n";
    echo "  );\n\n";
} else {
    echo "  ✅ Table exists\n";
    $cols = $conn->query("SHOW COLUMNS FROM room_images");
    while ($c = $cols->fetch_assoc()) {
        echo "  - {$c['Field']} ({$c['Type']})\n";
    }
}
echo "\n";

// 3. Check hostels table
echo "=== HOSTELS TABLE ===\n";
$res = $conn->query("SELECT id, name FROM hostels LIMIT 5");
if ($res) {
    while ($h = $res->fetch_assoc()) {
        echo "  - ID {$h['id']}: {$h['name']}\n";
    }
} else {
    echo "  ❌ Error: " . $conn->error . "\n";
}
echo "\n";

// 4. Check uploads directory
echo "=== UPLOADS DIRECTORY ===\n";
$dir = '../uploads/rooms/';
if (is_dir($dir)) {
    echo "  ✅ $dir exists\n";
    echo "  Writable: " . (is_writable($dir) ? 'YES' : 'NO ❌') . "\n";
} else {
    echo "  ❌ $dir does NOT exist\n";
    echo "  Attempting to create...\n";
    if (mkdir($dir, 0755, true)) {
        echo "  ✅ Created successfully\n";
    } else {
        echo "  ❌ Failed to create\n";
    }
}
echo "\n";

// 5. Test insert (will rollback)
echo "=== TEST INSERT (will rollback) ===\n";
$conn->begin_transaction();
$test_room_number = 'TEST_' . time();
$test_hostel_id = 0;
$res = $conn->query("SELECT id FROM hostels LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $test_hostel_id = $row['id'];
}

$stmt = $conn->prepare("
    INSERT INTO rooms (room_number, hostel_id, block, capacity, gender, price, status, description)
    VALUES (?, ?, 'TestBlock', 2, 'Male', 50000, 'available', 'Test description')
");
$stmt->bind_param("si", $test_room_number, $test_hostel_id);

if ($stmt->execute()) {
    echo "  ✅ Room INSERT works\n";
    $new_id = $conn->insert_id;
    echo "  New ID: $new_id\n";

    // Test room_images insert
    $img_stmt = $conn->prepare("
        INSERT INTO room_images (room_id, image_path, is_primary, sort_order)
        VALUES (?, 'test.jpg', 1, 0)
    ");
    $img_stmt->bind_param("i", $new_id);

    if ($img_stmt->execute()) {
        echo "  ✅ Image INSERT works\n";
    } else {
        echo "  ❌ Image INSERT failed: " . $img_stmt->error . "\n";
    }

    // Rollback
    $conn->rollback();
    echo "  Rolled back test data\n";
} else {
    echo "  ❌ Room INSERT failed: " . $stmt->error . "\n";
    $conn->rollback();
}
echo "\n";

// 6. Check existing rooms
echo "=== EXISTING ROOMS ===\n";
$res = $conn->query("SELECT COUNT(*) as c FROM rooms");
echo "  Total rooms: " . $res->fetch_assoc()['c'] . "\n";

$res = $conn->query("SELECT id, room_number FROM rooms ORDER BY id DESC LIMIT 5");
while ($r = $res->fetch_assoc()) {
    echo "  - ID {$r['id']}: Room {$r['room_number']}\n";
}

echo "\n=== DONE ===\n";
echo "</pre>";
echo "<p><a href='post-room.php'>← Back to Post Room</a></p>";