<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id    = $_SESSION['user_id'];
$nama       = $_SESSION['nama'] ?? 'Pengguna';
$role       = $_SESSION['role'] ?? 'pelajar';
$first_name = explode(' ', $nama)[0];
$initial    = mb_strtoupper(mb_substr($nama, 0, 1));

// --- STATS ---
function scalar_count(mysqli $conn, string $sql, array $params = [], string $types = '') {
    $stmt = $conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($res['c'] ?? 0);
}

$total_bookings     = scalar_count($conn, 'SELECT COUNT(*) c FROM bookings WHERE user_id = ?', [$user_id], 'i');
$active_bookings    = scalar_count($conn, 'SELECT COUNT(*) c FROM bookings WHERE user_id = ? AND status = "active"', [$user_id], 'i');
$completed_bookings = scalar_count($conn, 'SELECT COUNT(*) c FROM bookings WHERE user_id = ? AND status = "completed"', [$user_id], 'i');
$rooms_used         = scalar_count($conn, 'SELECT COUNT(DISTINCT room_name) c FROM bookings WHERE user_id = ?', [$user_id], 'i');

// --- UPCOMING BOOKINGS (next 3) ---
$stmt = $conn->prepare('SELECT room_name, booking_date, time_slot FROM bookings WHERE user_id = ? AND status = "active" AND booking_date >= CURDATE() ORDER BY booking_date ASC, time_slot ASC LIMIT 3');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$upcoming = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- TODAY'S PUBLIC BOOKINGS ---
$stmt = $conn->prepare('SELECT b.room_name, b.time_slot, u.nama FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.booking_date = CURDATE() AND b.status = "active" ORDER BY b.time_slot LIMIT 6');
$stmt->execute();
$today_public = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Dashboard';
include 'header.php';
?>


<main class="container" style="padding-top: 20px;">

    <!-- ═══ HERO BANNER — Clean redesign ═══ -->
    <section class="dash-hero fade-in">
        <div class="dash-hero-text">
            <span class="dash-hero-eyebrow">👋 Selamat Kembali</span>
            <h1>
                <span class="text-hai">Hai @<?= htmlspecialchars($first_name) ?>,</span><br>
                <span class="text-discover">Discover a New World of <em>E-Library</em>.</span>
            </h1>
            <p>Tempah bilik fasiliti kolej anda dengan pantas dan teratur. Semua jadual, semua ruangan — dalam satu portal yang indah.</p>
            <div class="dash-hero-actions">
                <a href="booking.php" class="btn btn-primary btn-lg">
                    <i class="fa-solid fa-calendar-plus"></i> Tempah Bilik
                </a>
                <a href="status_tempahan.php" class="btn btn-ghost btn-lg">
                    <i class="fa-solid fa-chart-pie"></i> Lihat Status
                </a>
            </div>
            <div class="dash-hero-avatars">
                <div class="avatar-stack">
                    <div class="avatar-bubble" style="background:#f4a4b8">A</div>
                    <div class="avatar-bubble" style="background:#a89fe8">B</div>
                    <div class="avatar-bubble" style="background:#ffc48a">C</div>
                    <div class="avatar-bubble" style="background:#8ed4c8">D</div>
                    <div class="avatar-bubble" style="background:#ffb3d9">E</div>
                </div>
                <div>
                    <strong>325+ Pelajar</strong>
                    <span>menggunakan portal</span>
                </div>
            </div>
        </div>

        <!-- SVG Illustration panel — Physical Books (Super Smooth 3D) -->
        <div class="dash-hero-art" id="heroArtScene" style="--mx: 0; --my: 0;">
            <div class="hero-ill-glow" style="width: 300px; height: 300px;"></div>
            
            <svg viewBox="0 0 360 360" xmlns="http://www.w3.org/2000/svg" class="svg-books-phys-3d">
                <ellipse cx="180" cy="320" rx="100" ry="15" fill="#c4bbf0" opacity="0.5" class="p-layer p-shadow"/>

                <g class="p-layer p-book-back">
                    <rect x="70" y="140" width="220" height="150" rx="8" fill="#5a4db8" stroke="#4a3b9c" stroke-width="2"/>
                    <rect x="270" y="145" width="15" height="140" rx="2" fill="#fffbe0" stroke="#e6e0b8" stroke-width="1"/>
                    <line x1="275" y1="150" x2="275" y2="280" stroke="#e6e0b8" stroke-width="0.5"/>
                    <line x1="280" y1="150" x2="280" y2="280" stroke="#e6e0b8" stroke-width="0.5"/>
                </g>

                <g class="p-layer p-book-front">
                    <rect x="50" y="170" width="220" height="150" rx="8" fill="#fce4e9" stroke="#f4a4b8" stroke-width="2"/>
                    <path d="M 120 170 V 100 L 135 115 L 150 100 V 170 Z" fill="#ffcd60" stroke="#e6b03c" stroke-width="2" stroke-linejoin="round"/>
                    <rect x="65" y="185" width="190" height="120" rx="4" fill="#fff" opacity="0.3"/>
                    <line x1="85" y1="210" x2="235" y2="210" stroke="#f4a4b8" stroke-width="2" stroke-linecap="round"/>
                    <line x1="85" y1="225" x2="215" y2="225" stroke="#f4a4b8" stroke-width="2" stroke-linecap="round"/>
                </g>

                <g class="p-layer p-page-float">
                    <g transform="translate(180, 80) rotate(-15) translate(-180, -80)">
                        <rect x="110" y="60" width="140" height="40" rx="6" fill="#a89fe8" stroke="#8b7fd9" stroke-width="2.5"/>
                        <line x1="140" y1="75" x2="220" y2="70" stroke="#ebe8ff" stroke-width="2" stroke-linecap="round"/>
                        <line x1="140" y1="88" x2="200" y2="85" stroke="#ebe8ff" stroke-width="2" stroke-linecap="round"/>
                    </g>
                </g>

                <g class="p-float pf-1"><circle cx="40" cy="60" r="10" fill="#f4a4b8" opacity="0.8"/></g>
                <g class="p-float pf-2"><path d="M 320 80 L 324 90 L 335 90 L 326 96 L 329 106 L 320 100 L 311 106 L 314 96 L 305 90 L 316 90 Z" fill="#ffcd60"/></g>
                <g class="p-float pf-3"><rect x="310" y="200" width="16" height="16" rx="4" fill="#8ed4c8" transform="rotate(20 318 208)"/></g>
                <g class="p-float pf-4"><path d="M 50 160 L 53 168 L 62 168 L 55 174 L 57 183 L 50 178 L 43 183 L 45 174 L 38 168 L 47 168 Z" fill="#a89fe8"/></g>
            </svg>
        </div>

            <div class="dash-hero-stats-row">
                <div class="auth-stat"><strong>6+</strong><span>Bilik Fasiliti</span></div>
                <div class="auth-stat"><strong>24/7</strong><span>Akses Portal</span></div>
                <div class="auth-stat"><strong>100%</strong><span>Percuma</span></div>
            </div>
        </div>
    </section>

    <!-- ═══ STATS GRID ═══ -->
    <section class="stats-section">
        <div class="stats-grid">
            <div class="stat-card scroll-reveal lift-on-hover">
                <div class="stat-icon" style="background:#e8e5fa;color:#6d5fcc">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <div class="stat-number" data-target="<?= $total_bookings ?>"><?= $total_bookings ?></div>
                <div class="stat-label">Jumlah Tempahan</div>
            </div>
            <div class="stat-card scroll-reveal lift-on-hover">
                <div class="stat-icon" style="background:#dbeafe;color:#2563eb">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div class="stat-number" data-target="<?= $active_bookings ?>"><?= $active_bookings ?></div>
                <div class="stat-label">Tempahan Aktif</div>
            </div>
            <div class="stat-card scroll-reveal lift-on-hover">
                <div class="stat-icon" style="background:#dcfce7;color:#16a34a">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="stat-number" data-target="<?= $completed_bookings ?>"><?= $completed_bookings ?></div>
                <div class="stat-label">Selesai</div>
            </div>
            <div class="stat-card scroll-reveal lift-on-hover">
                <div class="stat-icon" style="background:#fef3c7;color:#d97706">
                    <i class="fa-solid fa-door-open"></i>
                </div>
                <div class="stat-number" data-target="<?= $rooms_used ?>"><?= $rooms_used ?></div>
                <div class="stat-label">Bilik Digunakan</div>
            </div>
        </div>
    </section>

    <!-- ═══ UPCOMING BOOKINGS ═══ -->
    <section class="dash-section">
        <div class="section-header">
            <div>
                <h2>Tempahan Akan Datang</h2>
                <p>Jadual tempahan aktif anda yang terdekat.</p>
            </div>
            <a href="status_tempahan.php" class="btn btn-ghost btn-sm">Lihat Semua <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <?php if (empty($upcoming)): ?>
            <div class="empty-card scroll-reveal">
                <div class="empty-icon">📅</div>
                <h3>Tiada tempahan aktif</h3>
                <p>Mulakan dengan menempah bilik fasiliti untuk sesi pengajian anda.</p>
                <a href="booking.php" class="btn btn-primary" style="margin-top: 16px;">
                    <i class="fa-solid fa-plus"></i> Tempah Sekarang
                </a>
            </div>
        <?php else: ?>
            <div class="upcoming-grid">
                <?php foreach ($upcoming as $i => $bk): ?>
                    <div class="upcoming-card scroll-reveal lift-on-hover">
                        <div class="upcoming-card-top">
                            <span class="badge badge-primary">Aktif</span>
                            <span class="upcoming-time"><?= htmlspecialchars($bk['time_slot']) ?></span>
                        </div>
                        <h3><?= htmlspecialchars($bk['room_name']) ?></h3>
                        <div class="upcoming-meta">
                            <i class="fa-regular fa-calendar"></i>
                            <?= date('d M Y', strtotime($bk['booking_date'])) ?>
                            <span class="dot-sep">•</span>
                            <i class="fa-regular fa-clock"></i>
                            1 jam
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ═══ FEATURES (Bento) ═══ -->
    <section class="dash-section">
        <div class="section-header">
            <div>
                <h2>Kenapa Pilih E-Library?</h2>
                <p>Portal tempahan yang direka untuk pengalaman yang lancar.</p>
            </div>
        </div>
        <div class="features-grid">
            <div class="feature-card scroll-reveal lift-on-hover feature-lg">
                <div class="feature-icon" style="background:linear-gradient(135deg,#a89fe8,#6d5fcc);color:white">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <h3>Tempahan Pantas</h3>
                <p>Sistem tempahan real-time yang mencegah konflik jadual secara automatik. Tempah dalam 3 klik.</p>
            </div>
            <div class="feature-card scroll-reveal lift-on-hover">
                <div class="feature-icon" style="background:#fef3c7;color:#d97706">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h3>Selamat & Peribadi</h3>
                <p>Data anda dilindungi dengan enkripsi moden.</p>
            </div>
            <div class="feature-card scroll-reveal lift-on-hover">
                <div class="feature-icon" style="background:#dcfce7;color:#16a34a">
                    <i class="fa-solid fa-mobile-screen"></i>
                </div>
                <h3>Responsif</h3>
                <p>Berfungsi di semua peranti — desktop, tablet, mobile.</p>
            </div>
            <div class="feature-card scroll-reveal lift-on-hover">
                <div class="feature-icon" style="background:#dbeafe;color:#2563eb">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <h3>Rekod Lengkap</h3>
                <p>Semua tempahan lalu disimpan dan boleh dirujuk.</p>
            </div>
            <div class="feature-card scroll-reveal lift-on-hover">
                <div class="feature-icon" style="background:#ffe4e6;color:#e11d48">
                    <i class="fa-solid fa-heart"></i>
                </div>
                <h3>Percuma</h3>
                <p>Akses penuh tanpa bayaran untuk semua pelajar & pensyarah.</p>
            </div>
        </div>
    </section>

    <!-- ═══ TODAY'S PUBLIC SCHEDULE ═══ -->
    <?php if (!empty($today_public)): ?>
    <section class="dash-section">
        <div class="section-header">
            <div>
                <h2>Jadual Komuniti Hari Ini</h2>
                <p>Tempahan aktif oleh pelajar lain pada <?= date('d M Y') ?>.</p>
            </div>
        </div>
        <div class="today-grid">
            <?php foreach ($today_public as $pb): ?>
                <div class="today-card scroll-reveal">
                    <div class="today-time"><?= htmlspecialchars($pb['time_slot']) ?></div>
                    <div class="today-info">
                        <h4><?= htmlspecialchars($pb['room_name']) ?></h4>
                        <p>Oleh <?= htmlspecialchars(explode(' ', $pb['nama'])[0]) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</main>

<style>
/* ─── HERO ─── */
.dash-hero {
    background: linear-gradient(135deg, #eef0ff 0%, #e0e7ff 55%, #d6cfff 100%);
    border-radius: var(--radius-xl);
    padding: 56px 60px;
    display: grid;
    grid-template-columns: 1.15fr 1fr;
    gap: 48px;
    align-items: center;
    margin-bottom: 50px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}
.dash-hero::before {
    content: '';
    position: absolute;
    top: -80px; right: -80px;
    width: 360px; height: 360px;
    background: radial-gradient(circle, rgba(255,255,255,0.5), transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.dash-hero-eyebrow {
    display: inline-block;
    background: white;
    padding: 8px 16px;
    border-radius: var(--radius-pill);
    font-size: .84rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 18px;
    box-shadow: var(--shadow-sm);
}
.dash-hero-text h1 {
    font-size: 3rem;
    font-weight: 800;
    line-height: 1.08;
    color: var(--text-dark);
    margin-bottom: 16px;
    letter-spacing: -.025em;
}
/* Warna untuk "Hai @Nama," */
.dash-hero-text h1 .text-hai {
    color: var(--primary); /* Warna ungu tema */
}

/* Warna untuk "Discover a New World of..." */
.dash-hero-text h1 .text-discover {
    color: var(--text-dark); /* Warna gelap kebiruan */
}

/* Warna khas untuk perkataan "E-Library" sahaja */
.dash-hero-text h1 .text-discover em {
    color: #f4a4b8; /* Warna merah jambu pastel / boleh tukar ikut suka */
    font-style: italic;
}
.dash-hero-text > p {
    font-size: 1.05rem;
    color: var(--text-body);
    line-height: 1.6;
    max-width: 500px;
    margin-bottom: 26px;
}
.dash-hero-actions { display: flex; gap: 12px; margin-bottom: 26px; flex-wrap: wrap; }
.dash-hero-avatars { display: flex; align-items: center; gap: 14px; }
.dash-hero-avatars strong { display: block; font-size: .93rem; font-weight: 700; color: var(--text-dark); }
.dash-hero-avatars span   { font-size: .8rem; color: var(--text-muted); }
.avatar-stack { display: flex; }
.avatar-bubble {
    width: 36px; height: 36px;
    border-radius: 50%;
    border: 3px solid white;
    margin-left: -10px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: 800; font-size: .82rem;
    box-shadow: var(--shadow-sm);
}
.avatar-bubble:first-child { margin-left: 0; }

/* --- KAWALAN 3D BUKU FIZIKAL (SUPER SMOOTH LERP) --- */
.dash-hero-art {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    perspective: 1200px; /* Penting untuk kedalaman 3D */
    z-index: 10;
}

.svg-books-phys-3d {
    width: 100%;
    max-width: 360px;
    height: auto;
    transform-style: preserve-3d;
    /* Transformasi dipacu 100% oleh JavaScript (Tiada transition CSS untuk elak lag) */
    transform: rotateY(calc(var(--mx) * 25deg)) rotateX(calc(var(--my) * -20deg));
}

.p-layer { transform-style: preserve-3d; }

/* Ketinggian Z-Index Fizikal untuk Setiap Lapisan */
.p-shadow     { transform: translateZ(-60px); }
.p-book-back  { transform: translateZ(15px); }
.p-book-front { transform: translateZ(50px); }

/* Animasi helaian 'terbang' terapung automatik */
.p-page-float { 
    transform: translateZ(100px); 
    animation: float-page 4s ease-in-out infinite; 
}
@keyframes float-page {
    0%, 100% { transform: translateZ(100px) translateY(0) rotateX(0deg); }
    50%      { transform: translateZ(100px) translateY(-15px) rotateX(5deg); }
}

/* Objek terapung (bintang/hiasan) bergerak lebih agresif ikut mouse */
.p-float { transform-style: preserve-3d; }
.pf-1 { transform: translate(calc(var(--mx) * -60px), calc(var(--my) * -50px)) translateZ(80px); }
.pf-2 { transform: translate(calc(var(--mx) * 70px), calc(var(--my) * 60px)) translateZ(100px); }
.pf-3 { transform: translate(calc(var(--mx) * 50px), calc(var(--my) * -60px)) translateZ(70px); }
.pf-4 { transform: translate(calc(var(--mx) * -40px), calc(var(--my) * 80px)) translateZ(110px); }

.dash-hero-stats-row {
    display: flex; gap: 16px; margin-top: 16px; position: relative; z-index: 2;
}
.dash-hero-stats-row .auth-stat {
    background: rgba(255,255,255,0.7);
    backdrop-filter: blur(10px);
    padding: 12px 18px;
    border-radius: 16px;
    border: 1px solid rgba(255,255,255,0.9);
    text-align: center;
}
.dash-hero-stats-row .auth-stat strong { display: block; font-size: 1.3rem; font-weight: 800; color: #6d5fcc; }
.dash-hero-stats-row .auth-stat span { font-size: 0.76rem; font-weight: 600; color: #4a4560; }

/* Stats */
.stats-section { margin-bottom: 60px; }
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
.stat-card {
    background: white;
    border: 1px solid var(--border-card);
    border-radius: var(--radius-lg);
    padding: 28px;
    box-shadow: var(--shadow-sm);
}
.stat-icon { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 18px; }
.stat-number { font-size: 2.4rem; font-weight: 800; color: var(--text-dark); line-height: 1; margin-bottom: 6px; letter-spacing: -.02em; }
.stat-label  { font-size: .92rem; font-weight: 600; color: var(--text-muted); }

/* Sections */
.dash-section { margin-bottom: 60px; }
.section-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 26px; gap: 20px; flex-wrap: wrap; }
.section-header h2 { font-size: 1.8rem; font-weight: 800; color: var(--text-dark); margin-bottom: 4px; letter-spacing: -.01em; }
.section-header p  { font-size: .95rem; color: var(--text-muted); }

/* Upcoming */
.upcoming-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
.upcoming-card { background: white; border: 1px solid var(--border-card); border-radius: var(--radius-lg); padding: 26px; box-shadow: var(--shadow-sm); }
.upcoming-card-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.upcoming-time { font-size: 1.3rem; font-weight: 800; color: var(--primary); }
.upcoming-card h3 { font-size: 1.15rem; font-weight: 700; color: var(--text-dark); margin-bottom: 10px; }
.upcoming-meta { display: flex; align-items: center; gap: 8px; font-size: .88rem; color: var(--text-muted); font-weight: 500; }
.upcoming-meta .dot-sep { color: var(--border-soft); }

/* Empty */
.empty-card { background: white; border: 2px dashed var(--border-soft); border-radius: var(--radius-lg); padding: 60px 40px; text-align: center; }
.empty-icon { font-size: 3rem; margin-bottom: 14px; }
.empty-card h3 { font-size: 1.3rem; font-weight: 700; color: var(--text-dark); margin-bottom: 6px; }
.empty-card p  { color: var(--text-muted); font-size: .95rem; }

/* Features bento */
.features-grid { display: grid; grid-template-columns: repeat(4, 1fr); grid-template-rows: auto auto; gap: 20px; }
.feature-card { background: white; border: 1px solid var(--border-card); border-radius: var(--radius-lg); padding: 28px; box-shadow: var(--shadow-sm); }
.feature-lg { grid-column: span 2; grid-row: span 2; background: linear-gradient(135deg, #eef0ff, #e0e7ff); padding: 40px; }
.feature-lg h3 { font-size: 1.5rem; }
.feature-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 16px; box-shadow: 0 8px 20px rgba(109,95,204,.18); }
.feature-card h3 { font-size: 1.15rem; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; }
.feature-card p  { font-size: .92rem; color: var(--text-muted); line-height: 1.55; }

/* Today */
.today-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
.today-card { background: white; border: 1px solid var(--border-card); border-radius: var(--radius-md); padding: 20px; display: flex; align-items: center; gap: 16px; box-shadow: var(--shadow-sm); }
.today-time { background: var(--primary-light); color: var(--primary); padding: 12px 14px; border-radius: 12px; font-size: .95rem; font-weight: 800; min-width: 70px; text-align: center; }
.today-info h4 { font-size: .98rem; font-weight: 700; color: var(--text-dark); margin-bottom: 2px; }
.today-info p  { font-size: .82rem; color: var(--text-muted); }

/* Responsive */
@media (max-width: 980px) {
    .dash-hero { grid-template-columns: 1fr; padding: 40px 32px; }
    .dash-hero-text h1 { font-size: 2.2rem; }
    .dash-hero-art { max-width: 420px; margin: 0 auto; }
    .hero-illustration { min-height: 240px; }
    .svg-books { width: 220px; height: 186px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .features-grid { grid-template-columns: repeat(2, 1fr); }
    .feature-lg { grid-column: span 2; grid-row: auto; }
}
@media (max-width: 560px) {
    .dash-hero { padding: 32px 22px; border-radius: var(--radius-lg); }
    .dash-hero-text h1 { font-size: 1.8rem; }
    .hero-illustration { min-height: 200px; }
    .svg-books { width: 180px; height: 153px; }
    .dash-hero-stats-row { flex-wrap: wrap; gap: 10px; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .features-grid { grid-template-columns: 1fr; }
    .feature-lg { grid-column: span 1; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- ENJIN 3D LERP (SUPER SMOOTH) ---
    const artScene = document.getElementById('heroArtScene');
    if (!artScene) return;

    // Pembolehubah untuk sasaran (target) dan posisi semasa (current)
    let targetX = 0, targetY = 0;
    let currentX = 0, currentY = 0;
    
    // Pantau mouse di seluruh skrin (window), bukan sekadar pada gambar
    window.addEventListener('mousemove', (e) => {
        // Tukar koordinat ke range -1 hingga 1 berdasarkan saiz skrin
        targetX = (e.clientX / window.innerWidth) * 2 - 1;
        targetY = (e.clientY / window.innerHeight) * 2 - 1;
    });

    // Fungsi animasi berterusan 60fps
    function animate3D() {
        // Lerp (Linear Interpolation) - 0.08 adalah tahap kelajuan/kelembutan (smoothness)
        // Semakin rendah nilai (contoh: 0.05), semakin lambat/lembut ia mengekori mouse
        currentX += (targetX - currentX) * 0.08;
        currentY += (targetY - currentY) * 0.08;

        // Terapkan nilai yang disaring ke CSS variables
        artScene.style.setProperty('--mx', currentX.toFixed(4));
        artScene.style.setProperty('--my', currentY.toFixed(4));

        requestAnimationFrame(animate3D);
    }
    
    // Mulakan *loop* animasi
    animate3D();
});
</script>

<?php include 'footer.php'; ?>
