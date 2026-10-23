<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: dashboard.php'); exit; }

$admin_name  = $_SESSION['nama'] ?? 'Admin';
$admin_first = explode(' ', $admin_name)[0];

// ═══ FETCH ALL STATISTICS ═══

// 1) Total users
$r = $conn->query('SELECT COUNT(*) c FROM users'); $total_users = (int)$r->fetch_assoc()['c'];

// 2) Total bookings
$r = $conn->query('SELECT COUNT(*) c FROM bookings'); $total_bookings = (int)$r->fetch_assoc()['c'];

// 3) Today bookings
$r = $conn->query('SELECT COUNT(*) c FROM bookings WHERE booking_date = CURDATE() AND status="active"');
$today_bookings = (int)$r->fetch_assoc()['c'];

// 4) Active bookings
$r = $conn->query('SELECT COUNT(*) c FROM bookings WHERE status="active" AND booking_date >= CURDATE()');
$active_bookings = (int)$r->fetch_assoc()['c'];

// 5) Bookings per room (this month)
$r = $conn->query('SELECT room_name, COUNT(*) c FROM bookings WHERE MONTH(booking_date)=MONTH(CURDATE()) AND YEAR(booking_date)=YEAR(CURDATE()) GROUP BY room_name ORDER BY c DESC');
$rooms_data = []; while ($row = $r->fetch_assoc()) { $rooms_data[] = $row; }

// 6) Trend 7 days
$trend_data = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $stmt = $conn->prepare('SELECT COUNT(*) c FROM bookings WHERE booking_date = ?');
    $stmt->bind_param('s', $d);
    $stmt->execute();
    $cnt = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    $trend_data[] = ['date' => $d, 'label' => date('d/m', strtotime($d)), 'count' => $cnt];
}

// 7) Role distribution
$r = $conn->query("SELECT role, COUNT(*) c FROM users GROUP BY role");
$role_data = []; while ($row = $r->fetch_assoc()) { $role_data[$row['role']] = (int)$row['c']; }

// 8) Popular time slots (top 8)
$r = $conn->query('SELECT time_slot, COUNT(*) c FROM bookings GROUP BY time_slot ORDER BY c DESC LIMIT 8');
$slot_data = []; while ($row = $r->fetch_assoc()) { $slot_data[] = $row; }

// 9) Today's schedule (Gantt)
$r = $conn->query('SELECT b.room_name, b.time_slot, u.nama FROM bookings b JOIN users u ON b.user_id=u.id WHERE b.booking_date=CURDATE() AND b.status="active" ORDER BY b.room_name, b.time_slot');
$gantt_data = []; while ($row = $r->fetch_assoc()) { $gantt_data[] = $row; }

// 10) Latest users (10)
$r = $conn->query('SELECT id, nama, no_matrik, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 10');
$latest_users = []; while ($row = $r->fetch_assoc()) { $latest_users[] = $row; }

// 11) Latest bookings (10)
$r = $conn->query('SELECT b.id, b.room_name, b.booking_date, b.time_slot, b.status, u.nama FROM bookings b JOIN users u ON b.user_id=u.id ORDER BY b.created_at DESC LIMIT 10');
$latest_bookings = []; while ($row = $r->fetch_assoc()) { $latest_bookings[] = $row; }

// 12) AI Insights
$insights = [];

// Peak hour
$r = $conn->query('SELECT time_slot, COUNT(*) c FROM bookings WHERE booking_date=CURDATE() AND status="active" GROUP BY time_slot ORDER BY c DESC LIMIT 1');
$peak = $r->fetch_assoc();
if ($peak) $insights[] = ['icon' => '⏰', 'color' => '#f59e0b', 'text' => 'Peak hour hari ini: <strong>' . htmlspecialchars($peak['time_slot']) . '</strong>'];

// Top room
$r = $conn->query('SELECT room_name, COUNT(*) c FROM bookings GROUP BY room_name ORDER BY c DESC LIMIT 1');
$top = $r->fetch_assoc();
if ($top) $insights[] = ['icon' => '🏆', 'color' => '#6d5fcc', 'text' => 'Bilik paling popular: <strong>' . htmlspecialchars($top['room_name']) . '</strong> (' . $top['c'] . ' tempahan)'];

// Student percentage
$pelajar_count = $role_data['pelajar'] ?? 0;
if ($total_users > 0) {
    $pct = round(($pelajar_count / $total_users) * 100);
    $insights[] = ['icon' => '🎓', 'color' => '#10b981', 'text' => '<strong>' . $pct . '%</strong> pengguna adalah Pelajar'];
}

// Week comparison
$r = $conn->query('SELECT COUNT(*) c FROM bookings WHERE booking_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()');
$this_week = (int)$r->fetch_assoc()['c'];
$r = $conn->query('SELECT COUNT(*) c FROM bookings WHERE booking_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)');
$last_week = (int)$r->fetch_assoc()['c'];
if ($last_week > 0) {
    $change = round((($this_week - $last_week) / $last_week) * 100);
    $dir = $change >= 0 ? 'meningkat' : 'menurun';
    $insights[] = ['icon' => '📊', 'color' => $change >= 0 ? '#10b981' : '#ef4444', 'text' => 'Tempahan ' . $dir . ' <strong>' . abs($change) . '%</strong> berbanding minggu lepas'];
} else {
    $insights[] = ['icon' => '📊', 'color' => '#6d5fcc', 'text' => '<strong>' . $this_week . '</strong> tempahan dalam 7 hari lepas'];
}

// Usage rate
$usage_rate = $today_bookings > 0 ? round(($today_bookings / 90) * 100, 1) : 0;

// Room names for Gantt
$all_rooms = ['Bilik Bacaan 1','Bilik Bacaan 2','Bilik Mesyuarat','Ruang Seminar','Makmal Komputer','Studio Rakaman'];
$gantt_map = [];
foreach ($gantt_data as $g) {
    $gantt_map[$g['room_name']][] = $g;
}

$page_title = 'Admin Dashboard';
include 'header.php';
?>

<main class="container" style="padding-top: 20px;">

    <!-- ═══ ADMIN HERO BANNER ═══ -->
    <section class="admin-hero fade-in">
        <div class="admin-hero-content">
            <div class="admin-badge">
                <i class="fa-solid fa-shield-halved"></i> Panel Pentadbir
            </div>
            <h1>
                <span class="text-hai">Hai <?= htmlspecialchars($admin_first) ?>,</span><br>
                <span class="text-discover">selamat datang ke <em>Admin Dashboard</em>.</span>
            </h1>
            <p>Pantau semua aktiviti, analisa trend, dan urus pengguna & tempahan dari satu lokasi.</p>
            <div style="margin-top: 24px;">
                <button class="btn btn-primary" style="background: linear-gradient(135deg, #8b5cf6, #d946ef); border: none;" onclick="openAiModal()">
                    <i class="fa-solid fa-sparkles"></i> Laporan AI Harian
                </button>
            </div>
        </div>
        <div class="admin-hero-visual">
            <div class="admin-orb orb-1"></div>
            <div class="admin-orb orb-2"></div>
            <div class="admin-orb orb-3"></div>
            <div class="admin-hero-icon">
                <i class="fa-solid fa-chart-line"></i>
            </div>
        </div>
    </section>

    <!-- AI Insights have been moved to an auto-popup modal -->

    <!-- ═══ STATS CARDS ═══ -->
    <section class="admin-section">
        <div class="admin-stats-grid">
            <div class="admin-stat-card scroll-reveal lift-on-hover" style="--accent:#6d5fcc">
                <div class="asc-icon"><i class="fa-solid fa-users"></i></div>
                <div class="asc-value" data-target="<?= $total_users ?>"><?= $total_users ?></div>
                <div class="asc-label">Jumlah Pengguna</div>
                <div class="asc-bar" style="--fill:100%"></div>
            </div>
            <div class="admin-stat-card scroll-reveal lift-on-hover" style="--accent:#2563eb">
                <div class="asc-icon"><i class="fa-solid fa-calendar-day"></i></div>
                <div class="asc-value" data-target="<?= $today_bookings ?>"><?= $today_bookings ?></div>
                <div class="asc-label">Tempahan Hari Ini</div>
                <div class="asc-bar" style="--fill:<?= min($today_bookings * 5, 100) ?>%"></div>
            </div>
            <div class="admin-stat-card scroll-reveal lift-on-hover" style="--accent:#10b981">
                <div class="asc-icon"><i class="fa-solid fa-fire"></i></div>
                <div class="asc-value"><?= $usage_rate ?>%</div>
                <div class="asc-label">Kadar Penggunaan</div>
                <div class="asc-bar" style="--fill:<?= $usage_rate ?>%"></div>
            </div>
            <div class="admin-stat-card scroll-reveal lift-on-hover" style="--accent:#f59e0b">
                <div class="asc-icon"><i class="fa-solid fa-layer-group"></i></div>
                <div class="asc-value" data-target="<?= $total_bookings ?>"><?= $total_bookings ?></div>
                <div class="asc-label">Jumlah Tempahan</div>
                <div class="asc-bar" style="--fill:100%"></div>
            </div>
        </div>
    </section>

    <!-- ═══ CHARTS BENTO GRID ═══ -->
    <section class="admin-section">
        <div class="section-header">
            <div>
                <h2>📈 Analitik & Carta</h2>
                <p>Gambaran visual terperinci tentang penggunaan fasiliti.</p>
            </div>
        </div>
        <div class="charts-bento">

            <!-- BAR: Bookings per room -->
            <div class="chart-card chart-wide scroll-reveal">
                <div class="chart-card-header">
                    <h3><i class="fa-solid fa-chart-bar" style="color:var(--primary)"></i> Tempahan Per Bilik</h3>
                    <span class="chart-badge">Bulan Ini</span>
                </div>
                <div class="chart-wrap"><canvas id="chartRooms"></canvas></div>
            </div>

            <!-- LINE: 7-day trend -->
            <div class="chart-card scroll-reveal">
                <div class="chart-card-header">
                    <h3><i class="fa-solid fa-chart-line" style="color:#2563eb"></i> Trend 7 Hari</h3>
                    <span class="chart-badge">Harian</span>
                </div>
                <div class="chart-wrap"><canvas id="chartTrend"></canvas></div>
            </div>

            <!-- DOUGHNUT: Role distribution -->
            <div class="chart-card scroll-reveal">
                <div class="chart-card-header">
                    <h3><i class="fa-solid fa-chart-pie" style="color:#10b981"></i> Taburan Peranan</h3>
                    <span class="chart-badge">Pengguna</span>
                </div>
                <div class="chart-wrap chart-wrap-sm"><canvas id="chartRoles"></canvas></div>
            </div>

            <!-- HORIZONTAL BAR: Popular hours -->
            <div class="chart-card scroll-reveal">
                <div class="chart-card-header">
                    <h3><i class="fa-solid fa-clock" style="color:#f59e0b"></i> Slot Masa Popular</h3>
                    <span class="chart-badge">Top 8</span>
                </div>
                <div class="chart-wrap"><canvas id="chartSlots"></canvas></div>
            </div>

        </div>
    </section>

    <!-- ═══ GANTT TIMELINE ═══ -->
    <section class="admin-section">
        <div class="section-header">
            <div>
                <h2>🗓️ Jadual Hari Ini — Gantt Timeline</h2>
                <p>Paparan masa nyata semua tempahan hari ini mengikut bilik & slot masa.</p>
            </div>
            <span class="chart-badge"><?= date('d M Y') ?></span>
        </div>

        <?php if (empty($gantt_data)): ?>
            <div class="empty-card scroll-reveal">
                <div class="empty-icon">📅</div>
                <h3>Tiada tempahan hari ini</h3>
                <p>Semua bilik tersedia untuk ditempah.</p>
            </div>
        <?php else: ?>
        <div class="gantt-container scroll-reveal">
            <!-- Time header -->
            <div class="gantt-header">
                <div class="gantt-room-label" style="font-weight:800;color:var(--text-muted);font-size:.72rem">BILIK</div>
                <?php for ($h = 8; $h <= 22; $h++): ?>
                <div class="gantt-time-cell"><?= sprintf('%02d', $h) ?></div>
                <?php endfor; ?>
            </div>
            <!-- Room rows -->
            <?php foreach ($all_rooms as $rm): ?>
            <div class="gantt-row">
                <div class="gantt-room-label" title="<?= htmlspecialchars($rm) ?>"><?= htmlspecialchars($rm) ?></div>
                <?php for ($h = 8; $h <= 22; $h++):
                    $slot_key = sprintf('%02d:00', $h);
                    $booked = null;
                    foreach ($gantt_map[$rm] ?? [] as $g) {
                        if ($g['time_slot'] === $slot_key) { $booked = $g; break; }
                    }
                ?>
                <div class="gantt-cell <?= $booked ? 'gantt-booked' : '' ?>"
                     <?= $booked ? 'title="'.htmlspecialchars($booked['nama']).'"' : '' ?>>
                    <?php if ($booked): ?>
                        <span class="gantt-name"><?= htmlspecialchars(explode(' ', $booked['nama'])[0]) ?></span>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- ═══ LATEST BOOKINGS TABLE ═══ -->
    <section class="admin-section">
        <div class="section-header">
            <div>
                <h2>📋 Tempahan Terkini</h2>
                <p>10 tempahan paling baru dalam sistem.</p>
            </div>
        </div>
        <?php if (empty($latest_bookings)): ?>
            <div class="empty-card"><div class="empty-icon">📭</div><h3>Tiada tempahan</h3></div>
        <?php else: ?>
        <div class="admin-table-wrap scroll-reveal">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Pengguna</th>
                        <th>Bilik</th>
                        <th>Tarikh</th>
                        <th>Masa</th>
                        <th>Status</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($latest_bookings as $bk): ?>
                    <tr id="bk-row-<?= $bk['id'] ?>">
                        <td class="td-id">#<?= $bk['id'] ?></td>
                        <td>
                            <div class="td-user">
                                <div class="avatar-mini"><?= mb_strtoupper(mb_substr($bk['nama'], 0, 1)) ?></div>
                                <?= htmlspecialchars(explode(' ', $bk['nama'])[0]) ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($bk['room_name']) ?></td>
                        <td><?= date('d M Y', strtotime($bk['booking_date'])) ?></td>
                        <td><strong><?= htmlspecialchars($bk['time_slot']) ?></strong></td>
                        <td>
                            <?php
                            $sc = ['active'=>'badge-primary','completed'=>'badge-success','cancelled'=>'badge-warning'];
                            $sl = ['active'=>'Aktif','completed'=>'Selesai','cancelled'=>'Batal'];
                            ?>
                            <span class="badge <?= $sc[$bk['status']] ?? 'badge-primary' ?>"><?= $sl[$bk['status']] ?? $bk['status'] ?></span>
                        </td>
                        <td>
                            <button class="btn btn-danger btn-sm" onclick="deleteBooking(<?= $bk['id'] ?>)">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <!-- ═══ LATEST USERS TABLE ═══ -->
    <section class="admin-section">
        <div class="section-header">
            <div>
                <h2>👥 Pengguna Terkini</h2>
                <p>10 pengguna paling baru yang mendaftar.</p>
            </div>
        </div>
        <?php if (empty($latest_users)): ?>
            <div class="empty-card"><div class="empty-icon">👤</div><h3>Tiada pengguna</h3></div>
        <?php else: ?>
        <div class="admin-table-wrap scroll-reveal">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>No. Matrik</th>
                        <th>E-mel</th>
                        <th>Peranan</th>
                        <th>Tarikh Daftar</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($latest_users as $u): ?>
                    <tr id="usr-row-<?= $u['id'] ?>">
                        <td class="td-id">#<?= $u['id'] ?></td>
                        <td>
                            <div class="td-user">
                                <div class="avatar-mini" style="background:linear-gradient(135deg,<?= $u['role']==='admin'?'#f59e0b,#ef4444':($u['role']==='pensyarah'?'#ffc48a,#f59e0b':'#a89fe8,#6d5fcc') ?>)">
                                    <?= mb_strtoupper(mb_substr($u['nama'], 0, 1)) ?>
                                </div>
                                <?= htmlspecialchars($u['nama']) ?>
                            </div>
                        </td>
                        <td><code><?= htmlspecialchars($u['no_matrik']) ?></code></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <?php
                            $rc = ['pelajar'=>'badge-primary','pensyarah'=>'badge-warning','admin'=>'badge-success'];
                            $rl = ['pelajar'=>'🎒 Pelajar','pensyarah'=>'🎓 Pensyarah','admin'=>'🛡️ Admin'];
                            ?>
                            <span class="badge <?= $rc[$u['role']] ?? 'badge-primary' ?>"><?= $rl[$u['role']] ?? $u['role'] ?></span>
                        </td>
                        <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <div style="display:flex; gap:6px; align-items:center;">
                                <button class="btn btn-sm" style="background:#f59e0b; color:white; padding: 6px 10px;" onclick="openEditUserModal(<?= $u['id'] ?>)">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                <button class="btn btn-danger btn-sm" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['nama'])) ?>')">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                                <?php else: ?>
                                <span class="badge badge-success" style="font-size:.68rem">Anda</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

</main>

<!-- ═══ EDIT USER MODAL ═══ -->
<div id="editUserModal" class="modal-overlay" style="opacity: 0; pointer-events: none;">
    <div class="modal-card">
        <button class="modal-close" onclick="closeEditUserModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-pen"></i> Kemaskini Pengguna</h3>
            <p>Ubah maklumat profil atau peranan pengguna dalam sistem.</p>
        </div>
        <form id="editUserForm" onsubmit="submitEditUser(event)">
            <input type="hidden" id="edit_user_id" name="id">
            
            <div class="form-group">
                <label>Nama Penuh</label>
                <div class="input-icon-wrap">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" id="edit_user_nama" name="nama" required class="form-control">
                </div>
            </div>
            
            <div class="form-group">
                <label>No. Matrik / ID</label>
                <div class="input-icon-wrap">
                    <i class="fa-solid fa-id-badge"></i>
                    <input type="text" id="edit_user_no_matrik" name="no_matrik" required class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label>E-mel</label>
                <div class="input-icon-wrap">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" id="edit_user_email" name="email" required class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label>Peranan (Role)</label>
                <div class="input-icon-wrap">
                    <i class="fa-solid fa-user-shield"></i>
                    <select id="edit_user_role" name="role" required class="form-control">
                        <option value="pelajar">Pelajar</option>
                        <option value="pensyarah">Pensyarah</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeEditUserModal()">Batal</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitEdit">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ AI INSIGHTS MODAL ═══ -->
<div id="aiModal" class="modal-overlay" style="opacity: 0; pointer-events: none;">
    <div class="modal-card" style="max-width: 600px; padding: 0; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #1e1b4b 0%, #312e6b 100%); padding: 32px 32px 24px; text-align: center; position: relative;">
            <button class="modal-close" onclick="closeAiModal()" style="color: rgba(255,255,255,0.7); top: 16px; right: 20px;"><i class="fa-solid fa-xmark"></i></button>
            <div style="font-size: 3.5rem; margin-bottom: 12px; animation: hero-float 4s ease-in-out infinite;">✨</div>
            <h3 style="color: white; font-size: 1.6rem; font-weight: 800; margin-bottom: 6px;">Laporan AI Harian</h3>
            <p style="color: #a5b4fc; font-size: .95rem;">Analisis pintar berdasarkan corak data terkini sistem.</p>
            
            <div class="admin-orb orb-3" style="width: 100px; height: 100px; opacity: 0.3; top: -20px; left: -20px;"></div>
        </div>
        
        <div style="padding: 28px 32px 32px; background: var(--bg-soft);">
            <div class="insights-grid" style="grid-template-columns: 1fr; gap: 12px;">
                <?php foreach ($insights as $ins): ?>
                <div class="insight-card" style="padding: 16px; box-shadow: none; border-color: rgba(109,95,204,0.1);">
                    <div class="insight-icon" style="background:<?= $ins['color'] ?>20;color:<?= $ins['color'] ?>; width: 42px; height: 42px;">
                        <span><?= $ins['icon'] ?></span>
                    </div>
                    <div class="insight-text" style="font-size: 1rem;"><?= $ins['text'] ?></div>
                    <div class="insight-sparkle">
                        <i class="fa-solid fa-sparkles" style="color:<?= $ins['color'] ?>;opacity:.3"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button class="btn btn-primary btn-block" style="margin-top: 24px; padding: 14px; background: #1e1b4b;" onclick="closeAiModal()">
                Tutup Laporan
            </button>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<style>
/* ═══════════════════════════════════════════════
   SHARED STYLES (from other pages)
   ═══════════════════════════════════════════════ */

/* Section header */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 26px;
    gap: 20px;
    flex-wrap: wrap;
}
.section-header h2 {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--text-dark);
    margin-bottom: 4px;
    letter-spacing: -.01em;
}
.section-header p {
    font-size: .95rem;
    color: var(--text-muted);
}

/* Empty state card */
.empty-card {
    background: white;
    border: 2px dashed var(--border-soft);
    border-radius: var(--radius-lg);
    padding: 60px 40px;
    text-align: center;
    box-shadow: var(--shadow-sm);
}
.empty-icon {
    font-size: 3rem;
    margin-bottom: 14px;
}
.empty-card h3 {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 6px;
}
.empty-card p {
    color: var(--text-muted);
    font-size: .95rem;
}

/* ═══════════════════════════════════════════════
   ADMIN DASHBOARD STYLES
   ═══════════════════════════════════════════════ */

/* ── HERO ── */
.admin-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e6b 40%, #4338ca 100%);
    border-radius: var(--radius-xl);
    padding: 56px 60px;
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 32px;
    align-items: center;
    margin-bottom: 44px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(30, 27, 75, 0.5);
}
.admin-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    pointer-events: none;
}
.admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.12);
    backdrop-filter: blur(10px);
    padding: 8px 18px;
    border-radius: var(--radius-pill);
    font-size: .82rem;
    font-weight: 700;
    color: #c4bbf0;
    margin-bottom: 18px;
    border: 1px solid rgba(255,255,255,0.1);
}
.admin-hero-content h1 {
    font-size: 2.8rem;
    font-weight: 800;
    line-height: 1.08;
    color: white;
    margin-bottom: 16px;
    letter-spacing: -.025em;
}
.admin-hero-content .text-hai { color: #a89fe8; }
.admin-hero-content .text-discover { color: #e0e7ff; }
.admin-hero-content .text-discover em { color: #ffc48a; font-style: italic; }
.admin-hero-content > p {
    font-size: 1.05rem;
    color: #a5b4fc;
    line-height: 1.6;
    max-width: 500px;
}

/* Hero visual */
.admin-hero-visual {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 200px;
}
.admin-hero-icon {
    width: 120px; height: 120px;
    background: linear-gradient(135deg, rgba(168,159,232,0.3), rgba(109,95,204,0.5));
    border-radius: 32px;
    display: flex; align-items: center; justify-content: center;
    font-size: 3rem; color: white;
    box-shadow: 0 20px 60px rgba(109,95,204,0.4);
    animation: hero-float 5s ease-in-out infinite;
    position: relative; z-index: 2;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.15);
}
@keyframes hero-float {
    0%,100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-12px) rotate(3deg); }
}
.admin-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(40px);
    opacity: 0.5;
}
.orb-1 { width: 200px; height: 200px; background: #6d5fcc; top: -20px; right: -30px; animation: orbFloat 7s ease-in-out infinite; }
.orb-2 { width: 120px; height: 120px; background: #f4a4b8; bottom: -10px; left: 10px; animation: orbFloat 5s ease-in-out infinite reverse; }
.orb-3 { width: 80px; height: 80px; background: #ffc48a; top: 20px; left: 40px; animation: orbFloat 9s ease-in-out infinite; }
@keyframes orbFloat {
    0%,100% { transform: translate(0,0) scale(1); }
    50% { transform: translate(10px,-15px) scale(1.1); }
}

/* ── SECTIONS ── */
.admin-section { margin-bottom: 52px; }

/* ── INSIGHT CARDS ── */
.insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.insight-card {
    background: white;
    border: 1px solid var(--border-card);
    border-radius: var(--radius-lg);
    padding: 20px 22px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
}
.insight-icon {
    width: 48px; height: 48px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.insight-text {
    font-size: .92rem;
    color: var(--text-body);
    line-height: 1.5;
    flex: 1;
}
.insight-sparkle {
    position: absolute;
    top: 10px; right: 14px;
    font-size: 1.1rem;
    animation: sparkle 2s ease-in-out infinite;
}
@keyframes sparkle {
    0%,100% { opacity: .3; transform: scale(1) rotate(0deg); }
    50% { opacity: .6; transform: scale(1.2) rotate(15deg); }
}

/* ── STAT CARDS ── */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
}
.admin-stat-card {
    background: white;
    border: 1px solid var(--border-card);
    border-radius: var(--radius-lg);
    padding: 26px;
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
}
.admin-stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--accent);
    border-radius: 3px 3px 0 0;
}
.asc-icon {
    width: 48px; height: 48px;
    border-radius: 14px;
    background: color-mix(in srgb, var(--accent) 12%, white);
    color: var(--accent);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    margin-bottom: 16px;
}
.asc-value {
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--text-dark);
    letter-spacing: -.02em;
    line-height: 1;
    margin-bottom: 6px;
}
.asc-label {
    font-size: .88rem;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 14px;
}
.asc-bar {
    height: 4px;
    background: var(--border-soft);
    border-radius: 2px;
    overflow: hidden;
    position: relative;
}
.asc-bar::after {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: var(--fill);
    background: var(--accent);
    border-radius: 2px;
    transition: width 1.2s cubic-bezier(.22,1,.36,1);
}

/* ── CHARTS ── */
.charts-bento {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.chart-card {
    background: white;
    border: 1px solid var(--border-card);
    border-radius: var(--radius-xl);
    padding: 28px;
    box-shadow: var(--shadow-sm);
}
.chart-wide { grid-column: span 2; }
.chart-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.chart-card-header h3 {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 10px;
}
.chart-badge {
    display: inline-flex;
    padding: 5px 12px;
    border-radius: var(--radius-pill);
    background: var(--primary-light);
    color: var(--primary);
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.chart-wrap { position: relative; width: 100%; }
.chart-wrap canvas { width: 100% !important; }
.chart-wrap-sm { max-width: 280px; margin: 0 auto; }

/* ── GANTT ── */
.gantt-container {
    background: white;
    border: 1px solid var(--border-card);
    border-radius: var(--radius-xl);
    padding: 24px;
    box-shadow: var(--shadow-md);
    overflow-x: auto;
}
.gantt-header, .gantt-row {
    display: grid;
    grid-template-columns: 140px repeat(15, 1fr);
    gap: 2px;
    min-width: 800px;
}
.gantt-header {
    margin-bottom: 4px;
    border-bottom: 2px solid var(--border-soft);
    padding-bottom: 8px;
}
.gantt-time-cell {
    text-align: center;
    font-size: .7rem;
    font-weight: 700;
    color: var(--text-muted);
    padding: 4px 0;
}
.gantt-room-label {
    font-size: .78rem;
    font-weight: 700;
    color: var(--text-dark);
    padding: 8px 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
}
.gantt-row {
    border-bottom: 1px solid var(--border-soft);
}
.gantt-row:last-child { border-bottom: none; }
.gantt-cell {
    min-height: 40px;
    border-radius: 6px;
    margin: 2px 1px;
    background: var(--bg-cream);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .2s var(--ease);
}
.gantt-booked {
    background: linear-gradient(135deg, #a89fe8, #6d5fcc);
    box-shadow: 0 3px 10px rgba(109,95,204,0.25);
}
.gantt-name {
    font-size: .62rem;
    font-weight: 700;
    color: white;
    text-overflow: ellipsis;
    overflow: hidden;
    white-space: nowrap;
    max-width: 100%;
    padding: 0 2px;
}

/* ── TABLES ── */
.admin-table-wrap {
    background: white;
    border: 1px solid var(--border-card);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-sm);
    overflow-x: auto;
}
.admin-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .9rem;
}
.admin-table th {
    text-align: left;
    padding: 14px 18px;
    background: var(--bg-cream);
    color: var(--text-muted);
    font-size: .76rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    white-space: nowrap;
}
.admin-table th:first-child { border-radius: 16px 0 0 0; }
.admin-table th:last-child  { border-radius: 0 16px 0 0; }
.admin-table td {
    padding: 14px 18px;
    border-bottom: 1px solid var(--border-soft);
    color: var(--text-dark);
    font-weight: 500;
    vertical-align: middle;
}
.admin-table tr:last-child td { border-bottom: none; }
.admin-table tr:hover td { background: #faf9ff; }
.td-id { font-weight: 800; color: var(--primary); font-size: .85rem; }
.td-user {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
}
.avatar-mini {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #a89fe8, #6d5fcc);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800;
    font-size: .78rem;
    flex-shrink: 0;
}
.admin-table code {
    background: var(--primary-light);
    padding: 3px 8px;
    border-radius: 6px;
    font-size: .82rem;
    color: var(--primary);
    font-weight: 600;
}

/* ── RESPONSIVE ── */
@media (max-width: 1080px) {
    .admin-hero { grid-template-columns: 1fr; padding: 40px 32px; }
    .admin-hero-visual { display: none; }
    .admin-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .charts-bento { grid-template-columns: 1fr; }
    .chart-wide { grid-column: span 1; }
}
@media (max-width: 640px) {
    .admin-hero { padding: 28px 20px; }
    .admin-hero-content h1 { font-size: 2rem; }
    .admin-stats-grid { grid-template-columns: 1fr 1fr; }
    .insights-grid { grid-template-columns: 1fr; }
}

/* ── MODAL STYLES ── */
.modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(8px);
    z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    opacity: 0; pointer-events: none;
    transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
}
.modal-overlay.active {
    opacity: 1 !important; pointer-events: auto !important;
}
.modal-card {
    background: white;
    border-radius: var(--radius-xl);
    width: 90%; max-width: 500px;
    padding: 32px;
    position: relative;
    transform: scale(0.95) translateY(20px);
    transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    border: 1px solid var(--border-soft);
}
.modal-overlay.active .modal-card {
    transform: scale(1) translateY(0);
}
.modal-close {
    position: absolute; top: 20px; right: 24px;
    background: none; border: none; font-size: 1.4rem; color: var(--text-muted);
    cursor: pointer; transition: color 0.2s;
}
.modal-close:hover { color: var(--text-dark); }
.modal-header { margin-bottom: 24px; }
.modal-header h3 {
    font-size: 1.4rem; font-weight: 800; color: var(--text-dark); margin-bottom: 6px;
    display: flex; align-items: center; gap: 10px;
}
.modal-header h3 i { color: var(--primary); }
.modal-header p { font-size: .9rem; color: var(--text-muted); line-height: 1.5; }
.form-group { margin-bottom: 18px; }
.form-group label {
    display: block; font-size: .88rem; font-weight: 700; color: var(--text-dark); margin-bottom: 8px;
}
.input-icon-wrap { position: relative; }
.input-icon-wrap i {
    position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); font-size: 1.1rem; pointer-events: none;
}
.input-icon-wrap .form-control {
    width: 100%; padding: 12px 16px 12px 45px;
    border: 2px solid var(--border-soft); border-radius: var(--radius-md);
    font-family: inherit; font-size: .95rem; color: var(--text-dark);
    transition: all 0.2s; background: var(--bg-cream);
}
.input-icon-wrap .form-control:focus {
    border-color: var(--primary); outline: none; background: white;
    box-shadow: 0 0 0 4px rgba(109,95,204,0.15);
}
.modal-actions {
    display: flex; justify-content: flex-end; gap: 12px; margin-top: 32px;
}
.btn-outline {
    background: white; border: 2px solid var(--border-soft); color: var(--text-body);
    font-weight: 700; border-radius: var(--radius-md); padding: 10px 20px; cursor: pointer; transition: 0.2s;
}
.btn-outline:hover { background: var(--bg-cream); border-color: var(--border-card); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // ── COLOR PALETTE ──
    const COLORS = {
        primary: '#6d5fcc',
        primaryLight: '#a89fe8',
        blue: '#2563eb',
        green: '#10b981',
        amber: '#f59e0b',
        pink: '#f4a4b8',
        teal: '#8ed4c8',
        red: '#ef4444',
        purple: '#8b5cf6'
    };
    const GRADIENT = (ctx, c1, c2) => {
        const g = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height);
        g.addColorStop(0, c1); g.addColorStop(1, c2);
        return g;
    };

    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.font.weight = 600;
    Chart.defaults.color = '#8b87a3';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.pointStyle = 'circle';

    // ═══ 1. BAR CHART — Bookings per Room ═══
    const roomLabels = <?= json_encode(array_column($rooms_data, 'room_name')) ?>;
    const roomCounts = <?= json_encode(array_map('intval', array_column($rooms_data, 'c'))) ?>;
    const roomColors = [COLORS.primary, COLORS.pink, COLORS.teal, COLORS.amber, COLORS.blue, COLORS.purple];

    new Chart(document.getElementById('chartRooms'), {
        type: 'bar',
        data: {
            labels: roomLabels.map(l => l.replace('Bilik ','').replace('Ruang ','')),
            datasets: [{
                label: 'Tempahan',
                data: roomCounts,
                backgroundColor: roomColors.slice(0, roomLabels.length),
                borderRadius: 10,
                borderSkipped: false,
                barPercentage: 0.65
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 3,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e1b4b',
                    titleFont: { weight: 800 },
                    cornerRadius: 10,
                    padding: 12
                }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f0edff' }, ticks: { stepSize: 1 } },
                x: { grid: { display: false } }
            },
            animation: { duration: 1200, easing: 'easeOutQuart' }
        }
    });

    // ═══ 2. LINE CHART — 7-Day Trend ═══
    const trendLabels = <?= json_encode(array_column($trend_data, 'label')) ?>;
    const trendCounts = <?= json_encode(array_column($trend_data, 'count')) ?>;

    new Chart(document.getElementById('chartTrend'), {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Tempahan',
                data: trendCounts,
                borderColor: COLORS.blue,
                backgroundColor: (ctx) => {
                    const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 250);
                    g.addColorStop(0, 'rgba(37,99,235,0.2)');
                    g.addColorStop(1, 'rgba(37,99,235,0)');
                    return g;
                },
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: COLORS.blue,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f0edff' }, ticks: { stepSize: 1 } },
                x: { grid: { display: false } }
            },
            animation: { duration: 1400, easing: 'easeOutQuart' }
        }
    });

    // ═══ 3. DOUGHNUT — Role Distribution ═══
    const roleLabels = <?= json_encode(array_keys($role_data)) ?>;
    const roleCounts = <?= json_encode(array_values($role_data)) ?>;
    const roleColors = { pelajar: COLORS.primary, pensyarah: COLORS.amber, admin: COLORS.green };

    new Chart(document.getElementById('chartRoles'), {
        type: 'doughnut',
        data: {
            labels: roleLabels.map(r => r.charAt(0).toUpperCase() + r.slice(1)),
            datasets: [{
                data: roleCounts,
                backgroundColor: roleLabels.map(r => roleColors[r] || COLORS.primary),
                borderWidth: 3,
                borderColor: '#fff',
                hoverOffset: 12,
                spacing: 3
            }]
        },
        options: {
            responsive: true,
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 18 } },
                tooltip: { backgroundColor: '#1e1b4b', cornerRadius: 10, padding: 12 }
            },
            animation: { animateRotate: true, duration: 1200 }
        }
    });

    // ═══ 4. HORIZONTAL BAR — Popular Slots ═══
    const slotLabels = <?= json_encode(array_column($slot_data, 'time_slot')) ?>;
    const slotCounts = <?= json_encode(array_map('intval', array_column($slot_data, 'c'))) ?>;

    new Chart(document.getElementById('chartSlots'), {
        type: 'bar',
        data: {
            labels: slotLabels,
            datasets: [{
                label: 'Kekerapan',
                data: slotCounts,
                backgroundColor: COLORS.amber + '88',
                borderColor: COLORS.amber,
                borderWidth: 2,
                borderRadius: 8,
                barPercentage: 0.7
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { color: '#f0edff' }, ticks: { stepSize: 1 } },
                y: { grid: { display: false } }
            },
            animation: { duration: 1000, easing: 'easeOutQuart' }
        }
    });

});

// ═══ ADMIN ACTIONS ═══
async function deleteBooking(id) {
    if (!confirm('Pasti mahu padam tempahan #' + id + '?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_booking');
    fd.append('id', id);
    try {
        const r = await fetch('admin_api.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            const row = document.getElementById('bk-row-' + id);
            if (row) { row.style.transition = 'all .4s'; row.style.opacity = '0'; row.style.transform = 'translateX(20px)'; setTimeout(() => row.remove(), 400); }
        } else {
            alert('Gagal: ' + (d.error || 'Ralat tidak diketahui'));
        }
    } catch (e) { alert('Ralat rangkaian'); }
}

async function deleteUser(id, name) {
    if (!confirm('Pasti mahu padam pengguna "' + name + '"? Semua tempahan mereka juga akan dipadam.')) return;
    const fd = new FormData();
    fd.append('action', 'delete_user');
    fd.append('id', id);
    try {
        const r = await fetch('admin_api.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            const row = document.getElementById('usr-row-' + id);
            if (row) { row.style.transition = 'all .4s'; row.style.opacity = '0'; row.style.transform = 'translateX(20px)'; setTimeout(() => row.remove(), 400); }
        } else {
            alert('Gagal: ' + (d.error || 'Ralat tidak diketahui'));
        }
    } catch (e) { alert('Ralat rangkaian'); }
}

// ── EDIT USER MODAL FUNCTIONS ──
const editModal = document.getElementById('editUserModal');
function openEditUserModal(id) {
    document.getElementById('edit_user_id').value = id;
    
    // Reset form
    document.getElementById('editUserForm').reset();
    document.getElementById('btnSubmitEdit').disabled = true;
    document.getElementById('btnSubmitEdit').innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memuat...';
    
    editModal.classList.add('active');

    // Fetch user data
    fetch('admin_api.php?action=get_user&id=' + id)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                document.getElementById('edit_user_nama').value = d.user.nama;
                document.getElementById('edit_user_no_matrik').value = d.user.no_matrik;
                document.getElementById('edit_user_email').value = d.user.email;
                document.getElementById('edit_user_role').value = d.user.role;
                
                document.getElementById('btnSubmitEdit').disabled = false;
                document.getElementById('btnSubmitEdit').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan';
            } else {
                alert('Gagal mengambil data pengguna: ' + d.error);
                closeEditUserModal();
            }
        })
        .catch(e => {
            alert('Ralat rangkaian');
            closeEditUserModal();
        });
}

function closeEditUserModal() {
    editModal.classList.remove('active');
}

async function submitEditUser(e) {
    e.preventDefault();
    const form = e.target;
    const fd = new FormData(form);
    fd.append('action', 'update_user');
    
    const btn = document.getElementById('btnSubmitEdit');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';
    
    try {
        const r = await fetch('admin_api.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            closeEditUserModal();
            // Reload page to reflect changes in the table (or update DOM dynamically, reloading is safer for roles)
            window.location.reload();
        } else {
            alert('Gagal: ' + (d.error || 'Ralat tidak diketahui'));
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (err) {
        alert('Ralat rangkaian');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// ── AI MODAL FUNCTIONS ──
const aiModalObj = document.getElementById('aiModal');
function openAiModal() {
    aiModalObj.classList.add('active');
}
function closeAiModal() {
    aiModalObj.classList.remove('active');
}

// Auto-open AI Modal on page load (if not seen this session, or just always for demo)
window.addEventListener('DOMContentLoaded', () => {
    // We can use sessionStorage to ensure it only auto-pops once per browser tab session
    if (!sessionStorage.getItem('ai_insights_shown')) {
        setTimeout(() => {
            openAiModal();
            sessionStorage.setItem('ai_insights_shown', 'true');
        }, 600);
    }
});

</script>

<?php include 'footer.php'; ?>
