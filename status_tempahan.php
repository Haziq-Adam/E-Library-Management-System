<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id = $_SESSION['user_id'];
$nama    = $_SESSION['nama'] ?? 'Pengguna';

$action_msg = '';
$action_status = '';

// Handle cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking_id'])) {
    $bid = (int)$_POST['cancel_booking_id'];
    $stmt = $conn->prepare('DELETE FROM bookings WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $bid, $user_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $action_msg = 'Tempahan berjaya dibatalkan.';
        $action_status = 'success';
    } else {
        $action_msg = 'Gagal membatalkan tempahan.';
        $action_status = 'error';
    }
    $stmt->close();
}

// Handle edit (change time slot)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_booking_id'])) {
    $bid      = (int)$_POST['edit_booking_id'];
    $new_time = $_POST['new_time'] ?? '';

    $stmt = $conn->prepare('SELECT room_name, booking_date FROM bookings WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $bid, $user_id);
    $stmt->execute();
    $bk = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($bk) {
        // Check conflict
        $chk = $conn->prepare('SELECT id FROM bookings WHERE room_name = ? AND booking_date = ? AND time_slot = ? AND status = "active" AND id != ?');
        $chk->bind_param('sssi', $bk['room_name'], $bk['booking_date'], $new_time, $bid);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $action_msg = 'Konflik: Jam ' . htmlspecialchars($new_time) . ' sudah ditempah.';
            $action_status = 'error';
        } else {
            $upd = $conn->prepare('UPDATE bookings SET time_slot = ? WHERE id = ? AND user_id = ?');
            $upd->bind_param('sii', $new_time, $bid, $user_id);
            $upd->execute();
            $upd->close();
            $action_msg = 'Jam berjaya dipinda ke ' . htmlspecialchars($new_time) . '.';
            $action_status = 'success';
        }
        $chk->close();
    }
}

// Fetch personal bookings
$stmt = $conn->prepare('SELECT id, room_name, booking_date, time_slot FROM bookings WHERE user_id = ? AND status = "active" AND booking_date >= CURDATE() ORDER BY booking_date ASC, time_slot ASC');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$personal_bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch today's public bookings
$stmt = $conn->prepare('SELECT b.room_name, b.time_slot, u.nama, u.role FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.booking_date = CURDATE() AND b.status = "active" ORDER BY b.room_name, b.time_slot');
$stmt->execute();
$public_bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$operation_hours = [];
for ($i = 8; $i <= 22; $i++) {
    $slot_val   = sprintf('%02d:00', $i);
    $slot_label = sprintf('%02d:00 - %02d:00', $i, $i + 1);
    $operation_hours[] = ['value' => $slot_val, 'label' => $slot_label];
}

$view_tab = $_GET['tab'] ?? 'personal';

$page_title = 'Status Jadual';
include 'header.php';
?>

<main class="container" style="padding-top: 40px;">

    <div class="page-head fade-in">
        <span class="eyebrow">&#128202; Status</span>
        <h1>Urus Jadual <em>Tempahan</em> Anda.</h1>
        <p>Lihat, pinda atau batal tempahan aktif anda &mdash; atau semak jadual komuniti hari ini.</p>
    </div>

    <?php if ($action_msg): ?>
        <div class="alert alert-<?= $action_status === 'success' ? 'success' : 'error' ?>">
            <i class="fa-solid <?= $action_status === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
            <?= htmlspecialchars($action_msg) ?>
        </div>
    <?php endif; ?>

    <!-- Tab pills -->
    <div class="status-tabs fade-in">
        <a href="?tab=personal" class="status-tab <?= $view_tab === 'personal' ? 'active' : '' ?>">
            <i class="fa-solid fa-user"></i> Rekod Saya
            <span class="tab-count"><?= count($personal_bookings) ?></span>
        </a>
        <a href="?tab=public" class="status-tab <?= $view_tab === 'public' ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i> Komuniti Hari Ini
            <span class="tab-count"><?= count($public_bookings) ?></span>
        </a>
    </div>

    <?php if ($view_tab === 'personal'): ?>

        <section class="status-panel">
            <?php if (empty($personal_bookings)): ?>
                <div class="empty-card">
                    <div class="empty-icon">&#128237;</div>
                    <h3>Tiada tempahan aktif</h3>
                    <p>Anda belum mempunyai sebarang tempahan aktif. Mulakan sekarang!</p>
                    <a href="booking.php" class="btn btn-primary" style="margin-top: 16px;">
                        <i class="fa-solid fa-plus"></i> Tempah Bilik
                    </a>
                </div>
            <?php else: ?>
                <div class="booking-list">
                    <?php foreach ($personal_bookings as $bk): ?>
                        <div class="booking-row scroll-reveal lift-on-hover">
                            <div class="br-visual">
                                <i class="fa-solid fa-door-open"></i>
                            </div>

                            <div class="br-main">
                                <h3><?= htmlspecialchars($bk['room_name']) ?></h3>
                                <div class="br-meta">
                                    <span><i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime($bk['booking_date'])) ?></span>
                                    <span class="dot">&bull;</span>
                                    <span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($bk['time_slot']) ?> &ndash; <?= sprintf('%02d:00', (int)explode(':', $bk['time_slot'])[0] + 1) ?></span>
                                    <span class="dot">&bull;</span>
                                    <span class="badge badge-success">Aktif</span>
                                </div>
                            </div>

                            <div class="br-actions">
                                <button type="button" class="sp-action-btn edit btn-edit-slot"
                                        title="Pinda masa"
                                        data-bid="<?= $bk['id'] ?>"
                                        data-room="<?= htmlspecialchars($bk['room_name']) ?>"
                                        data-date="<?= htmlspecialchars($bk['booking_date']) ?>"
                                        data-current="<?= htmlspecialchars($bk['time_slot']) ?>">
                                    <i class="fa-solid fa-pen"></i> Pinda
                                </button>
                                <form method="POST" action="status_tempahan.php?tab=personal"
                                      onsubmit="return confirm('Pasti untuk batalkan tempahan ini?')"
                                      class="inline-form">
                                    <input type="hidden" name="cancel_booking_id" value="<?= $bk['id'] ?>">
                                    <button type="submit" class="sp-action-btn delete">
                                        <i class="fa-solid fa-trash"></i> Batal
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    <?php else: ?>

        <section class="status-panel">
            <div class="panel-intro">
                <h2>Jadual hari ini</h2>
                <p>Senarai tempahan aktif oleh semua pengguna pada <?= date('d M Y') ?>.</p>
            </div>

            <?php if (empty($public_bookings)): ?>
                <div class="empty-card">
                    <div class="empty-icon">&#9749;</div>
                    <h3>Tiada tempahan hari ini</h3>
                    <p>Semua bilik tersedia untuk ditempah.</p>
                    <a href="booking.php" class="btn btn-primary" style="margin-top: 16px;">
                        <i class="fa-solid fa-calendar-plus"></i> Tempah Sekarang
                    </a>
                </div>
            <?php else: ?>
                <div class="public-grid">
                    <?php foreach ($public_bookings as $pb): ?>
                        <div class="public-card scroll-reveal lift-on-hover">
                            <div class="public-time"><?= htmlspecialchars($pb['time_slot']) ?> &ndash; <?= sprintf('%02d:00', (int)explode(':', $pb['time_slot'])[0] + 1) ?></div>
                            <h4><?= htmlspecialchars($pb['room_name']) ?></h4>
                            <div class="public-meta">
                                <div class="avatar-mini"><?= mb_strtoupper(mb_substr($pb['nama'], 0, 1)) ?></div>
                                <div>
                                    <strong><?= htmlspecialchars(explode(' ', $pb['nama'])[0]) ?></strong>
                                    <span><?= htmlspecialchars($pb['role']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    <?php endif; ?>

<!-- Slot Picker Modal -->
<div class="sp-overlay" id="slotModalOverlay">
  <div class="sp-card" id="slotModal">
    <div class="sp-topbar">
      <div class="sp-topbar-inner">
        <div class="sp-title-group">
          <div class="sp-icon-wrap"><i class="fa-solid fa-arrow-right-arrow-left"></i></div>
          <div><h3>Tukar Slot Masa</h3><p>Klik slot yang tersedia untuk menukar</p></div>
        </div>
        <button type="button" class="sp-close" id="modalClose"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="sp-info-strip">
        <span><i class="fa-solid fa-door-open"></i> <span id="modalRoom">&mdash;</span></span>
        <span class="sp-divider"></span>
        <span><i class="fa-regular fa-calendar"></i> <span id="modalDate">&mdash;</span></span>
        <span class="sp-divider"></span>
        <span class="sp-current-badge"><i class="fa-solid fa-clock"></i> Semasa: <strong id="modalCurrentTime">&mdash;</strong></span>
      </div>
    </div>
    <div class="sp-body">
      <div class="sp-grid" id="modalSlotGrid">
        <div class="sp-loading"><div class="sp-spinner"></div>Memuatkan slot...</div>
      </div>
    </div>
    <div class="sp-footer">
      <div class="sp-selection-info" id="spSelInfo">
        <i class="fa-regular fa-circle-dot"></i> Pilih slot masa baru
      </div>
      <div class="sp-footer-actions">
        <button type="button" class="sp-btn-cancel" id="modalCancelBtn">Batal</button>
        <form method="POST" action="status_tempahan.php?tab=personal" id="modalEditForm" style="display:inline">
          <input type="hidden" name="edit_booking_id" id="modalBid" value="">
          <input type="hidden" name="new_time" id="modalNewTime" value="">
          <button type="submit" class="sp-btn-confirm" id="modalConfirmBtn" disabled>
            <i class="fa-solid fa-check"></i> Sahkan
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

</main>

<link rel="stylesheet" href="slot_picker.css">
<style>
.page-head {
    display: flex !important;
    flex-direction: column !important;
    align-items: flex-start !important;
    margin-bottom: 36px;
}
.eyebrow {
    display: inline-flex !important;
    background: white;
    padding: 7px 14px;
    border-radius: var(--radius-pill);
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--primary);
    box-shadow: var(--shadow-sm);
    margin-top: 20px !important;
    margin-bottom: 15px !important;
}
.page-head h1 {
    font-size: 2.6rem;
    font-weight: 800;
    color: var(--text-dark);
    line-height: 1.1;
    margin-top: 0 !important;
    margin-bottom: 10px;
    letter-spacing: -0.02em;
}
.page-head h1 em { font-style: italic; color: var(--primary); }
.page-head p {
    font-size: 1.05rem;
    color: var(--text-muted);
    max-width: 620px;
}

/* Tabs */
.status-tabs { display: flex; gap: 10px; margin-bottom: 28px; flex-wrap: wrap; }
.status-tab {
    display: inline-flex; align-items: center; gap: 10px;
    padding: 12px 22px; background: white;
    border: 1px solid var(--border-soft); border-radius: var(--radius-pill);
    font-size: 0.95rem; font-weight: 700; color: var(--text-muted);
    text-decoration: none; transition: all 0.25s var(--ease); box-shadow: var(--shadow-sm);
}
.status-tab:hover { color: var(--text-dark); border-color: var(--primary-light); }
.status-tab.active {
    background: var(--primary); color: white; border-color: var(--primary);
    box-shadow: 0 10px 24px rgba(109, 95, 204, 0.35);
}
.tab-count { background: rgba(255,255,255,0.25); padding: 2px 10px; border-radius: var(--radius-pill); font-size: 0.78rem; }
.status-tab:not(.active) .tab-count { background: var(--primary-light); color: var(--primary); }

/* Panel */
.status-panel { margin-bottom: 40px; }
.panel-intro { margin-bottom: 20px; }
.panel-intro h2 { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-bottom: 4px; }
.panel-intro p { font-size: 0.95rem; color: var(--text-muted); }

/* Booking row */
.booking-list { display: flex; flex-direction: column; gap: 14px; }
.booking-row {
    background: white; border: 1px solid var(--border-card); border-radius: var(--radius-lg);
    padding: 20px 24px; display: flex; align-items: center; gap: 20px; box-shadow: var(--shadow-sm);
}
.br-visual {
    width: 54px; height: 54px; border-radius: 16px;
    background: linear-gradient(135deg, #a89fe8, #6d5fcc); color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; flex-shrink: 0; box-shadow: 0 8px 20px rgba(109, 95, 204, 0.3);
}
.br-main { flex: 1; min-width: 0; }
.br-main h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-dark); margin-bottom: 6px; }
.br-meta { display: flex; align-items: center; gap: 10px; font-size: 0.87rem; color: var(--text-muted); font-weight: 500; flex-wrap: wrap; }
.br-meta .dot { color: var(--border-soft); }
.br-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.inline-form { display: flex; gap: 6px; align-items: center; }

/* Action Buttons */
.sp-action-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 18px; border-radius: 12px; font-size: 0.85rem; font-weight: 700;
    border: none; cursor: pointer; transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    font-family: inherit;
}
.sp-action-btn i { font-size: 0.9rem; }
.sp-action-btn.edit {
    background: var(--primary-soft); color: var(--primary);
}
.sp-action-btn.edit:hover {
    background: var(--primary); color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(109, 95, 204, 0.3);
}
.sp-action-btn.delete {
    background: #fee2e2; color: #dc2626;
}
.sp-action-btn.delete:hover {
    background: #ef4444; color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.3);
}

/* Empty state */
.empty-card { background: white; border: 2px dashed var(--border-soft); border-radius: var(--radius-lg); padding: 70px 40px; text-align: center; }
.empty-icon { font-size: 3.4rem; margin-bottom: 14px; }
.empty-card h3 { font-size: 1.35rem; font-weight: 800; color: var(--text-dark); margin-bottom: 6px; }
.empty-card p { color: var(--text-muted); font-size: 0.95rem; }

/* Public grid */
.public-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
.public-card {
    background: white; border: 1px solid var(--border-card); border-radius: var(--radius-lg);
    padding: 24px; box-shadow: var(--shadow-sm); position: relative; overflow: hidden;
}
.public-card::before {
    content: ''; position: absolute; top: 0; right: 0; width: 100px; height: 100px;
    background: radial-gradient(circle, var(--primary-soft), transparent 70%); pointer-events: none;
}
.public-time { font-size: 1.6rem; font-weight: 800; color: var(--primary); line-height: 1; margin-bottom: 10px; letter-spacing: -0.02em; }
.public-card h4 { font-size: 1.05rem; font-weight: 800; color: var(--text-dark); margin-bottom: 14px; }
.public-meta { display: flex; align-items: center; gap: 10px; padding-top: 14px; border-top: 1px solid var(--border-soft); }
.avatar-mini {
    width: 34px; height: 34px; border-radius: 50%;
    background: linear-gradient(135deg, #f4a4b8, #a89fe8); color: white;
    display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.85rem;
}
.public-meta strong { display: block; font-size: 0.9rem; font-weight: 700; color: var(--text-dark); }
.public-meta span { font-size: 0.76rem; color: var(--text-muted); text-transform: capitalize; }

@media (max-width: 720px) {
    .booking-row { flex-wrap: wrap; gap: 14px; }
    .br-actions { width: 100%; }
    .page-head h1 { font-size: 2rem; }
}
</style>

<script>window.__SP_HOURS = <?= json_encode(array_map(fn($h) => $h['value'], $operation_hours)) ?>;</script>
<script src="slot_picker.js"></script>

<?php include 'footer.php'; ?>
