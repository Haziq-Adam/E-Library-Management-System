<?php
/**
 * admin_api.php
 * JSON API for admin dashboard actions
 */
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Auth guard
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ── DELETE BOOKING ──
    case 'delete_booking':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid ID']); exit; }
        $stmt = $conn->prepare('DELETE FROM bookings WHERE id = ?');
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        break;

    // ── DELETE USER ──
    case 'delete_user':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid ID']); exit; }
        // Prevent self-delete
        if ($id === (int)$_SESSION['user_id']) {
            echo json_encode(['error' => 'Cannot delete yourself']);
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        break;

    // ── GET USER ──
    case 'get_user':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid ID']); exit; }
        $stmt = $conn->prepare('SELECT id, nama, no_matrik, email, role FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($user) {
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            http_response_code(404); echo json_encode(['error' => 'User not found']);
        }
        break;

    // ── UPDATE USER ──
    case 'update_user':
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $no_matrik = trim($_POST['no_matrik'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? '');

        if ($id <= 0 || !$nama || !$no_matrik || !$email || !$role) {
            http_response_code(400); echo json_encode(['error' => 'Missing required fields']); exit;
        }

        // Prevent self demotion or role change if needed? Maybe skip that logic for now, but ensure no duplicate matric/email
        $stmt = $conn->prepare('SELECT id FROM users WHERE (email = ? OR no_matrik = ?) AND id != ?');
        $stmt->bind_param('ssi', $email, $no_matrik, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['error' => 'Email atau No Matrik sudah digunakan oleh pengguna lain']);
            $stmt->close();
            exit;
        }
        $stmt->close();

        $stmt = $conn->prepare('UPDATE users SET nama = ?, no_matrik = ?, email = ?, role = ? WHERE id = ?');
        $stmt->bind_param('ssssi', $nama, $no_matrik, $email, $role, $id);
        $ok = $stmt->execute();
        $stmt->close();
        
        if ($ok) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Gagal mengemaskini pengguna']);
        }
        break;

    // ── STATS ──
    case 'stats':
        $stats = [];

        // Total users
        $r = $conn->query('SELECT COUNT(*) c FROM users');
        $stats['total_users'] = (int)$r->fetch_assoc()['c'];

        // Today bookings
        $r = $conn->query('SELECT COUNT(*) c FROM bookings WHERE booking_date = CURDATE() AND status = "active"');
        $stats['today_bookings'] = (int)$r->fetch_assoc()['c'];

        // Total bookings
        $r = $conn->query('SELECT COUNT(*) c FROM bookings');
        $stats['total_bookings'] = (int)$r->fetch_assoc()['c'];

        // Active bookings
        $r = $conn->query('SELECT COUNT(*) c FROM bookings WHERE status = "active" AND booking_date >= CURDATE()');
        $stats['active_bookings'] = (int)$r->fetch_assoc()['c'];

        // Most popular room
        $r = $conn->query('SELECT room_name, COUNT(*) c FROM bookings GROUP BY room_name ORDER BY c DESC LIMIT 1');
        $row = $r->fetch_assoc();
        $stats['top_room'] = $row ? $row['room_name'] : 'N/A';

        // Usage rate (today active / total slots available)
        // 6 rooms × 15 hour slots = 90 total slots
        $stats['usage_rate'] = $stats['today_bookings'] > 0
            ? round(($stats['today_bookings'] / 90) * 100, 1)
            : 0;

        echo json_encode($stats);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
