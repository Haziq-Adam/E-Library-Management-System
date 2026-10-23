<?php
/**
 * image_api.php
 * JSON API for image uploads (rooms, books, page banners)
 */
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Auth guard — admin only
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * Handle file upload — returns relative path or false
 */
function handle_upload($file, $dest_dir, $prefix = '') {
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if (!in_array($file['type'], $allowed, true)) return false;
    if ($file['size'] > 5 * 1024 * 1024) return false; // 5MB max

    $ext = match($file['type']) {
        'image/jpeg' => '.jpg',
        'image/png'  => '.png',
        'image/webp' => '.webp',
        'image/gif'  => '.gif',
        default      => '.jpg',
    };

    $dir = __DIR__ . '/' . $dest_dir;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = $prefix . uniqid() . $ext;
    $dest     = $dir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return $dest_dir . '/' . $filename;
    }
    return false;
}

switch ($action) {

    // ── Upload Room Image ──
    case 'upload_room_image':
        $room_name = trim($_POST['room_name'] ?? '');
        if (!$room_name || !isset($_FILES['image'])) {
            echo json_encode(['error' => 'Nama bilik dan gambar diperlukan']);
            exit;
        }

        $path = handle_upload($_FILES['image'], 'uploads/rooms', 'room-');
        if (!$path) {
            echo json_encode(['error' => 'Gagal muat naik gambar. Format: JPG/PNG/WEBP, max 5MB.']);
            exit;
        }

        // Delete old image if it's a custom upload (not default)
        $stmt = $conn->prepare('SELECT image_path FROM room_images WHERE room_name = ?');
        $stmt->bind_param('s', $room_name);
        $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($old && strpos($old['image_path'], 'uploads/rooms/room-') !== false) {
            @unlink(__DIR__ . '/' . $old['image_path']);
        }

        // Upsert
        $stmt = $conn->prepare('INSERT INTO room_images (room_name, image_path) VALUES (?, ?) ON DUPLICATE KEY UPDATE image_path = VALUES(image_path)');
        $stmt->bind_param('ss', $room_name, $path);
        $ok = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $ok, 'path' => $path]);
        break;

    // ── Upload Book Cover Image ──
    case 'upload_book_image':
        $book_id = (int)($_POST['book_id'] ?? 0);
        if ($book_id <= 0 || !isset($_FILES['image'])) {
            echo json_encode(['error' => 'ID buku dan gambar diperlukan']);
            exit;
        }

        $path = handle_upload($_FILES['image'], 'uploads/books', 'book-' . $book_id . '-');
        if (!$path) {
            echo json_encode(['error' => 'Gagal muat naik gambar. Format: JPG/PNG/WEBP, max 5MB.']);
            exit;
        }

        // Delete old local image
        $stmt = $conn->prepare('SELECT cover_image FROM books WHERE id = ?');
        $stmt->bind_param('i', $book_id);
        $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($old && $old['cover_image'] && strpos($old['cover_image'], 'uploads/') === 0) {
            @unlink(__DIR__ . '/' . $old['cover_image']);
        }

        $stmt = $conn->prepare('UPDATE books SET cover_image = ? WHERE id = ?');
        $stmt->bind_param('si', $path, $book_id);
        $ok = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $ok, 'path' => $path]);
        break;

    // ── Get all room images ──
    case 'get_room_images':
        $result = $conn->query('SELECT room_name, image_path FROM room_images ORDER BY room_name');
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[$row['room_name']] = $row['image_path'];
        }
        echo json_encode($data);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
