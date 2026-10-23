<?php
/**
 * booking_slots.php
 * Lightweight JSON endpoint — returns occupied time slots
 * for a given room + date. Used by booking.php via fetch().
 */
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$room = $_GET['room'] ?? '';
$date = $_GET['date'] ?? '';

// Basic validation
if ($room === '' || $date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid params']);
    exit;
}

$occupied = [];
$owners   = []; // time_slot => nama (optional, for showing who booked)

$stmt = $conn->prepare('
    SELECT b.time_slot, u.nama, b.user_id
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    WHERE b.room_name = ? AND b.booking_date = ? AND b.status = "active"
');
$stmt->bind_param('ss', $room, $date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $occupied[] = $row['time_slot'];
    $owners[$row['time_slot']] = [
        'nama'   => $row['nama'],
        'is_you' => ((int)$row['user_id'] === (int)$_SESSION['user_id']),
    ];
}
$stmt->close();

echo json_encode([
    'room'     => $room,
    'date'     => $date,
    'occupied' => $occupied,
    'owners'   => $owners,
]);
