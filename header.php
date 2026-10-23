<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_logged_in = isset($_SESSION['user_id']);
$user_first_name = '';
$user_initial    = '';
if ($is_logged_in && !empty($_SESSION['nama'])) {
    $user_first_name = explode(' ', $_SESSION['nama'])[0];
    $user_initial    = mb_strtoupper(mb_substr($_SESSION['nama'], 0, 1));
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistem Tempahan Bilik & Folio E-Library Kolej ATX">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' — ' : '' ?>e-Library Kolej ATX</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="animations.css?v=<?= time() ?>">

    <style>
        /* ── Kolej ATX Navbar Brand ── */
        .navbar-brand { display: flex; align-items: center; gap: 10px; }
        .logo-atx { width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #6d5fcc, #a89fe8); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(109, 95, 204, 0.35); flex-shrink: 0; }
        .logo-atx svg { display: block; }
        .brand-text { display: flex; flex-direction: column; line-height: 1; }
        .brand-text .brand-main { font-size: 1rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.01em; }
        .brand-text .brand-sub { font-size: 0.65rem; font-weight: 700; color: var(--primary); letter-spacing: 0.08em; text-transform: uppercase; margin-top: 1px; }

        /* ── Nav Links ── */
        .navbar-links a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            text-decoration: none;
            line-height: 1;
        }
        .navbar-links a i {
            margin-right: 0 !important;
            display: flex;
            align-items: center;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<div class="preloader" id="preloader"><div class="spinner"></div></div>

<?php if ($is_logged_in): ?>
<nav class="navbar">
    <a href="dashboard.php" class="navbar-brand">
        <span class="logo-atx">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 3L2 8.5V9.5H3V17H8V12H12V17H17V9.5H18V8.5L10 3Z" fill="white" opacity="0.95"/>
                <rect x="8" y="5.5" width="4" height="3" rx="0.5" fill="white" opacity="0.5"/>
            </svg>
        </span>
        <span class="brand-text">
            <span class="brand-main">e-Library</span>
            <span class="brand-sub">Kolej ATX</span>
        </span>
    </a>

    <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
        <i class="fa-solid fa-bars"></i>
    </button>

    <ul class="navbar-links" id="navLinks">
        <li>
            <a href="dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-house"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="booking.php" class="<?= $current_page === 'booking.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-plus"></i> Tempahan
            </a>
        </li>
        <li>
            <a href="status_tempahan.php" class="<?= $current_page === 'status_tempahan.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i> Status
            </a>
        </li>
        <li>
            <a href="tempah_buku.php" class="<?= $current_page === 'tempah_buku.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-book"></i> Buku
            </a>
        </li>
        <li>
            <a href="status_buku.php" class="<?= $current_page === 'status_buku.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-book-bookmark"></i> Status Buku
            </a>
        </li>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <li>
            <a href="admin_dashboard.php" class="<?= $current_page === 'admin_dashboard.php' ? 'active' : '' ?>" style="color:#f59e0b;">
                <i class="fa-solid fa-shield-halved"></i> Admin
            </a>
        </li>
        <li>
            <a href="admin_books.php" class="<?= $current_page === 'admin_books.php' ? 'active' : '' ?>" style="color:#f59e0b;">
                <i class="fa-solid fa-book-open-reader"></i> Urus Buku
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="logout.php" class="btn-nav-cta">
                <i class="fa-solid fa-right-from-bracket"></i> Log Keluar
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>
