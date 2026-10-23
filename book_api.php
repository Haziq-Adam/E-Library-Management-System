<?php
/**
 * book_api.php
 * JSON API for book reservation system
 */
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Auth guard
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$role    = $_SESSION['role'] ?? 'pelajar';
$action  = $_GET['action'] ?? $_POST['action'] ?? '';

/**
 * Generate unique reservation code: BK-XXXXXX
 */
function generate_reservation_code($conn) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no ambiguous chars
    do {
        $code = 'BK-';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $conn->prepare('SELECT id FROM book_reservations WHERE reservation_code = ?');
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
    } while ($exists);
    return $code;
}

switch ($action) {

    // ══════════════════════════════════════════
    // USER: Reserve a book
    // ══════════════════════════════════════════
    case 'reserve_book':
        $book_id = (int)($_POST['book_id'] ?? 0);
        if ($book_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'ID buku tidak sah']);
            exit;
        }

        // Check book exists and is available
        $stmt = $conn->prepare('SELECT id, title, status FROM books WHERE id = ?');
        $stmt->bind_param('i', $book_id);
        $stmt->execute();
        $book = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$book) {
            echo json_encode(['error' => 'Buku tidak ditemui']);
            exit;
        }
        if ($book['status'] !== 'ada') {
            echo json_encode(['error' => 'Buku ini tidak tersedia untuk ditempah']);
            exit;
        }

        // Check if user already has a pending/approved reservation for this book
        $stmt = $conn->prepare('SELECT id FROM book_reservations WHERE user_id = ? AND book_id = ? AND status IN ("pending","approved")');
        $stmt->bind_param('ii', $user_id, $book_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            echo json_encode(['error' => 'Anda sudah mempunyai tempahan aktif untuk buku ini']);
            exit;
        }
        $stmt->close();

        // Create reservation
        $code = generate_reservation_code($conn);
        $stmt = $conn->prepare('INSERT INTO book_reservations (user_id, book_id, reservation_code, status) VALUES (?, ?, ?, "pending")');
        $stmt->bind_param('iis', $user_id, $book_id, $code);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            echo json_encode([
                'success' => true,
                'message' => 'Permohonan tempahan berjaya dihantar! Sila tunggu kelulusan admin.',
                'code'    => $code
            ]);
        } else {
            echo json_encode(['error' => 'Gagal membuat tempahan. Sila cuba lagi.']);
        }
        break;

    // ══════════════════════════════════════════
    // USER: Cancel pending reservation
    // ══════════════════════════════════════════
    case 'cancel_reservation':
        $res_id = (int)($_POST['reservation_id'] ?? 0);
        if ($res_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'ID tidak sah']);
            exit;
        }

        $stmt = $conn->prepare('DELETE FROM book_reservations WHERE id = ? AND user_id = ? AND status = "pending"');
        $stmt->bind_param('ii', $res_id, $user_id);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($ok && $affected > 0) {
            echo json_encode(['success' => true, 'message' => 'Permohonan berjaya dibatalkan.']);
        } else {
            echo json_encode(['error' => 'Gagal membatalkan. Hanya permohonan pending boleh dibatalkan.']);
        }
        break;

    // ══════════════════════════════════════════
    // ADMIN: Approve reservation
    // ══════════════════════════════════════════
    case 'approve_reservation':
        if ($role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $res_id   = (int)($_POST['reservation_id'] ?? 0);
        $due_date = trim($_POST['due_date'] ?? '');
        $note     = trim($_POST['admin_note'] ?? '');

        if ($res_id <= 0) {
            echo json_encode(['error' => 'ID tidak sah']);
            exit;
        }
        if (!$due_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
            echo json_encode(['error' => 'Tarikh pulang tidak sah']);
            exit;
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare('UPDATE book_reservations SET status = "approved", approved_at = ?, due_date = ?, admin_note = ? WHERE id = ? AND status = "pending"');
        $stmt->bind_param('sssi', $now, $due_date, $note, $res_id);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($ok && $affected > 0) {
            echo json_encode(['success' => true, 'message' => 'Tempahan diluluskan.']);
        } else {
            echo json_encode(['error' => 'Gagal meluluskan. Tempahan mungkin sudah diproses.']);
        }
        break;

    // ══════════════════════════════════════════
    // ADMIN: Reject reservation
    // ══════════════════════════════════════════
    case 'reject_reservation':
        if ($role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $res_id = (int)($_POST['reservation_id'] ?? 0);
        $note   = trim($_POST['admin_note'] ?? '');

        if ($res_id <= 0) {
            echo json_encode(['error' => 'ID tidak sah']);
            exit;
        }

        $stmt = $conn->prepare('UPDATE book_reservations SET status = "rejected", admin_note = ? WHERE id = ? AND status = "pending"');
        $stmt->bind_param('si', $note, $res_id);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($ok && $affected > 0) {
            echo json_encode(['success' => true, 'message' => 'Tempahan ditolak.']);
        } else {
            echo json_encode(['error' => 'Gagal menolak.']);
        }
        break;

    // ══════════════════════════════════════════
    // ADMIN: Mark book as returned
    // ══════════════════════════════════════════
    case 'return_book':
        if ($role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $res_id = (int)($_POST['reservation_id'] ?? 0);
        if ($res_id <= 0) {
            echo json_encode(['error' => 'ID tidak sah']);
            exit;
        }

        $stmt = $conn->prepare('UPDATE book_reservations SET status = "returned" WHERE id = ? AND status = "approved"');
        $stmt->bind_param('i', $res_id);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($ok && $affected > 0) {
            echo json_encode(['success' => true, 'message' => 'Buku ditandakan dipulangkan.']);
        } else {
            echo json_encode(['error' => 'Gagal. Hanya tempahan approved boleh ditandakan returned.']);
        }
        break;

    // ══════════════════════════════════════════
    // ADMIN: Update book status
    // ══════════════════════════════════════════
    case 'update_book_status':
        if ($role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $book_id    = (int)($_POST['book_id'] ?? 0);
        $new_status = trim($_POST['new_status'] ?? '');
        $valid = ['ada', 'sedang_ditempah', 'tidak_tersedia'];

        if ($book_id <= 0 || !in_array($new_status, $valid, true)) {
            echo json_encode(['error' => 'Parameter tidak sah']);
            exit;
        }

        $stmt = $conn->prepare('UPDATE books SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $new_status, $book_id);
        $ok = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $ok, 'message' => 'Status buku dikemaskini.']);
        break;

    // ══════════════════════════════════════════
    // ADMIN: Add new book
    // ══════════════════════════════════════════
    case 'add_book':
        if ($role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $isbn       = trim($_POST['isbn'] ?? '');
        $title      = trim($_POST['title'] ?? '');
        $author     = trim($_POST['author'] ?? '');
        $publisher  = trim($_POST['publisher'] ?? '');
        $year       = (int)($_POST['year_published'] ?? date('Y'));
        $category   = trim($_POST['category'] ?? 'Umum');
        $description= trim($_POST['description'] ?? '');
        $color      = trim($_POST['cover_color'] ?? '#6d5fcc');
        $copies     = max(1, (int)($_POST['total_copies'] ?? 1));

        if (!$isbn || !$title || !$author) {
            echo json_encode(['error' => 'ISBN, tajuk, dan penulis diperlukan.']);
            exit;
        }

        $stmt = $conn->prepare('INSERT INTO books (isbn, title, author, publisher, year_published, category, description, cover_color, total_copies, cover_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $cover_image = null;

        // Handle cover image upload
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (in_array($_FILES['cover_image']['type'], $allowed, true) && $_FILES['cover_image']['size'] <= 5 * 1024 * 1024) {
                $ext = match($_FILES['cover_image']['type']) {
                    'image/jpeg' => '.jpg',
                    'image/png'  => '.png',
                    'image/webp' => '.webp',
                    'image/gif'  => '.gif',
                    default      => '.jpg',
                };
                $dir = __DIR__ . '/uploads/books';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $filename = 'book-' . uniqid() . $ext;
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $dir . '/' . $filename)) {
                    $cover_image = 'uploads/books/' . $filename;
                }
            }
        }

        $stmt->bind_param('ssssisssis', $isbn, $title, $author, $publisher, $year, $category, $description, $color, $copies, $cover_image);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Buku berjaya ditambah.', 'id' => $stmt->insert_id]);
        } else {
            echo json_encode(['error' => 'Gagal menambah buku. ISBN mungkin sudah wujud.']);
        }
        $stmt->close();
        break;

    // ══════════════════════════════════════════
    // ADMIN: Delete book
    // ══════════════════════════════════════════
    case 'delete_book':
        if ($role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $book_id = (int)($_POST['book_id'] ?? 0);
        if ($book_id <= 0) {
            echo json_encode(['error' => 'ID tidak sah']);
            exit;
        }

        // Check for active reservations
        $stmt = $conn->prepare('SELECT COUNT(*) c FROM book_reservations WHERE book_id = ? AND status IN ("pending","approved")');
        $stmt->bind_param('i', $book_id);
        $stmt->execute();
        $cnt = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();

        if ($cnt > 0) {
            echo json_encode(['error' => 'Tidak boleh padam buku yang masih ada tempahan aktif (' . $cnt . ' tempahan).']);
            exit;
        }

        $stmt = $conn->prepare('DELETE FROM books WHERE id = ?');
        $stmt->bind_param('i', $book_id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok, 'message' => 'Buku berjaya dipadam.']);
        break;

    // ══════════════════════════════════════════
    // GET: Fetch all books (for catalog)
    // ══════════════════════════════════════════
    case 'get_books':
        $search   = trim($_GET['search'] ?? '');
        $category = trim($_GET['category'] ?? '');

        $sql = 'SELECT * FROM books WHERE 1=1';
        $params = [];
        $types  = '';

        if ($search) {
            $sql .= ' AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)';
            $like = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sss';
        }
        if ($category) {
            $sql .= ' AND category = ?';
            $params[] = $category;
            $types .= 's';
        }

        $sql .= ' ORDER BY title ASC';

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode($books);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
