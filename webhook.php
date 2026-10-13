<?php
// webhook.php
$event = json_decode(file_get_contents('php://input'), true);
// Verify signature, then update payment status, allocate if needed
http_response_code(200);
?>