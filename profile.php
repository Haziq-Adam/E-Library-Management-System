<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id = $_SESSION['user_id'];
$action_msg    = '';
$action_status = '';

// ── HANDLE DELETE ACCOUNT ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $confirm = trim($_POST['confirm_delete'] ?? '');
    if ($confirm !== 'PADAM') {
        $action_msg    = 'Sila taip "PADAM" untuk mengesahkan pemadaman.';
        $action_status = 'error';
    } else {
        $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $user_id);
        if ($stmt->execute()) {
            $stmt->close();
            session_destroy();
            header('Location: login.php?deleted=1');
            exit;
        } else {
            $action_msg    = 'Ralat pangkalan data. Cuba lagi.';
            $action_status = 'error';
        }
        $stmt->close();
    }
}

// ── HANDLE EDIT PROFILE ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_nama       = trim($_POST['nama']       ?? '');
    $new_email      = trim($_POST['email']      ?? '');
    $new_no_telefon = trim($_POST['no_telefon'] ?? '');
    $new_password   = $_POST['new_password']    ?? '';
    $confirm_pass   = $_POST['confirm_password'] ?? '';

    if (empty($new_nama) || empty($new_email) || empty($new_no_telefon)) {
        $action_msg    = 'Sila isi semua medan yang wajib.';
        $action_status = 'error';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $action_msg    = 'Format e-mel tidak sah.';
        $action_status = 'error';
    } elseif (!empty($new_password) && $new_password !== $confirm_pass) {
        $action_msg    = 'Kata laluan baharu tidak sepadan.';
        $action_status = 'error';
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $action_msg    = 'Kata laluan perlu sekurang-kurangnya 6 aksara.';
        $action_status = 'error';
    } else {
        // Check email not taken by another user
        $chk = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $chk->bind_param('si', $new_email, $user_id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $action_msg    = 'E-mel sudah digunakan oleh akaun lain.';
            $action_status = 'error';
            $chk->close();
        } else {
            $chk->close();
            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('UPDATE users SET nama=?, email=?, no_telefon=?, password=? WHERE id=?');
                $stmt->bind_param('ssssi', $new_nama, $new_email, $new_no_telefon, $hashed, $user_id);
            } else {
                $stmt = $conn->prepare('UPDATE users SET nama=?, email=?, no_telefon=? WHERE id=?');
                $stmt->bind_param('sssi', $new_nama, $new_email, $new_no_telefon, $user_id);
            }
            if ($stmt->execute()) {
                $_SESSION['nama'] = $new_nama;
                $action_msg    = 'Profil berjaya dikemaskini!';
                $action_status = 'success';
            } else {
                $action_msg    = 'Ralat pangkalan data. Cuba lagi.';
                $action_status = 'error';
            }
            $stmt->close();
        }
    }
}

// ── FETCH CURRENT USER DATA ────────────────────────────────────────────────
$stmt = $conn->prepare('SELECT nama, no_matrik, email, no_telefon, role, created_at FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) { session_destroy(); header('Location: login.php'); exit; }

// ── FETCH BOOKING HISTORY ──────────────────────────────────────────────────
$stmt = $conn->prepare('SELECT room_name, booking_date, time_slot, status, created_at FROM bookings WHERE user_id = ? ORDER BY booking_date DESC, time_slot DESC LIMIT 20');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Stats
$stmt = $conn->prepare('SELECT COUNT(*) c FROM bookings WHERE user_id = ?'); $stmt->bind_param('i',$user_id); $stmt->execute(); $total_bk = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close();
$stmt = $conn->prepare('SELECT COUNT(*) c FROM bookings WHERE user_id = ? AND status="active" AND booking_date >= CURDATE()'); $stmt->bind_param('i',$user_id); $stmt->execute(); $active_bk = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close();

$initial     = mb_strtoupper(mb_substr($user['nama'], 0, 1));
$first_name  = explode(' ', $user['nama'])[0];
$member_since = date('M Y', strtotime($user['created_at']));

$page_title = 'Profil Saya';
include 'header.php';
?>

<main class="container" style="padding-top: 40px;">

    <!-- Page head -->
    <div class="page-head fade-in">
        <span class="eyebrow">👤 Akaun</span>
        <h1>Profil <em>Saya</em>.</h1>
        <p>Urus maklumat peribadi, kemaskini kata laluan, dan lihat sejarah tempahan anda.</p>
    </div>

    <?php if ($action_msg): ?>
        <div class="alert alert-<?= $action_status === 'success' ? 'success' : 'error' ?>">
            <i class="fa-solid <?= $action_status === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
            <?= htmlspecialchars($action_msg) ?>
        </div>
    <?php endif; ?>

    <div class="profile-layout">

        <!-- LEFT SIDEBAR: Avatar + Stats -->
        <aside class="profile-sidebar">
            <div class="profile-card">
                <div class="profile-avatar-wrap">
                    <div class="profile-avatar"><?= htmlspecialchars($initial) ?></div>
                    <div class="profile-avatar-ring"></div>
                </div>
                <h2 class="profile-name"><?= htmlspecialchars($user['nama']) ?></h2>
                <span class="badge badge-<?= $user['role'] === 'pensyarah' ? 'warning' : 'primary' ?>" style="margin-bottom:8px;">
                    <?= $user['role'] === 'pensyarah' ? '🎓 Pensyarah' : '🎒 Pelajar' ?>
                </span>
                <p class="profile-matrik"><?= htmlspecialchars($user['no_matrik']) ?></p>
                <p class="profile-since"><i class="fa-regular fa-calendar-check"></i> Ahli sejak <?= $member_since ?></p>

                <div class="profile-stats">
                    <div class="ps-item">
                        <strong><?= $total_bk ?></strong>
                        <span>Jumlah Tempahan</span>
                    </div>
                    <div class="ps-divider"></div>
                    <div class="ps-item">
                        <strong><?= $active_bk ?></strong>
                        <span>Tempahan Aktif</span>
                    </div>
                </div>

                <a href="booking.php" class="btn btn-primary btn-block" style="margin-top: 20px;">
                    <i class="fa-solid fa-calendar-plus"></i> Buat Tempahan
                </a>
                <a href="status_tempahan.php" class="btn btn-ghost btn-block" style="margin-top: 10px;">
                    <i class="fa-solid fa-list"></i> Lihat Status
                </a>
            </div>
        </aside>

        <!-- RIGHT: Edit Form + History -->
        <div class="profile-main">

            <!-- Edit Profile Card -->
            <div class="profile-section-card fade-in">
                <div class="section-card-header">
                    <div class="section-card-icon" style="background:linear-gradient(135deg,#a89fe8,#6d5fcc)">
                        <i class="fa-solid fa-pen"></i>
                    </div>
                    <div>
                        <h3>Kemaskini Maklumat</h3>
                        <p>Ubah nama, e-mel, telefon atau kata laluan anda</p>
                    </div>
                </div>

                <form method="POST" autocomplete="off">
                    <input type="hidden" name="update_profile" value="1">

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Nama Penuh <span class="req">*</span></label>
                            <div class="input-icon">
                                <input type="text" name="nama" class="form-control"
                                       value="<?= htmlspecialchars($user['nama']) ?>" required>
                                <i class="icon fa-solid fa-user"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">No. Matrik</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['no_matrik']) ?>"
                                   readonly style="background:var(--primary-soft);color:var(--primary);font-weight:700;">
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">E-mel <span class="req">*</span></label>
                            <div class="input-icon">
                                <input type="email" name="email" class="form-control"
                                       value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                       placeholder="cth. ahmad@student.atx.edu.my" required>
                                <i class="icon fa-solid fa-envelope"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">No. Telefon <span class="req">*</span></label>
                            <div class="input-icon">
                                <input type="tel" name="no_telefon" class="form-control"
                                       value="<?= htmlspecialchars($user['no_telefon'] ?? '') ?>"
                                       placeholder="cth. 0123456789" required>
                                <i class="icon fa-solid fa-phone"></i>
                            </div>
                        </div>
                    </div>

                    <div class="pass-divider">
                        <span>Tukar Kata Laluan <em>(kosongkan jika tidak mahu tukar)</em></span>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Kata Laluan Baharu</label>
                            <div class="input-icon">
                                <input type="password" name="new_password" class="form-control"
                                       placeholder="Minimum 6 aksara">
                                <i class="icon fa-solid fa-lock"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sahkan Kata Laluan</label>
                            <div class="input-icon">
                                <input type="password" name="confirm_password" class="form-control"
                                       placeholder="Ulang kata laluan baharu">
                                <i class="icon fa-solid fa-lock"></i>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>

            <!-- Booking History Card -->
            <div class="profile-section-card fade-in" style="animation-delay:.1s">
                <div class="section-card-header">
                    <div class="section-card-icon" style="background:linear-gradient(135deg,#ffc48a,#f59e0b)">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div>
                        <h3>Sejarah Tempahan</h3>
                        <p>20 rekod terkini</p>
                    </div>
                </div>

                <?php if (empty($history)): ?>
                    <div class="history-empty">
                        <div style="font-size:2.5rem;margin-bottom:12px;">📭</div>
                        <p>Tiada sejarah tempahan lagi.</p>
                        <a href="booking.php" class="btn btn-primary btn-sm" style="margin-top:12px;">
                            <i class="fa-solid fa-plus"></i> Buat Tempahan Pertama
                        </a>
                    </div>
                <?php else: ?>
                    <div class="history-table-wrap">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Bilik</th>
                                    <th>Tarikh</th>
                                    <th>Masa</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td><i class="fa-solid fa-door-open" style="color:var(--primary);margin-right:8px;"></i><?= htmlspecialchars($h['room_name']) ?></td>
                                        <td><?= date('d M Y', strtotime($h['booking_date'])) ?></td>
                                        <td><?= htmlspecialchars($h['time_slot']) ?></td>
                                        <td>
                                            <?php
                                            $sc = ['active'=>'badge-primary','completed'=>'badge-success','cancelled'=>'badge-warning'];
                                            $sl = ['active'=>'Aktif','completed'=>'Selesai','cancelled'=>'Batal'];
                                            ?>
                                            <span class="badge <?= $sc[$h['status']] ?? 'badge-primary' ?>">
                                                <?= $sl[$h['status']] ?? $h['status'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Danger Zone -->
            <div class="profile-section-card danger-zone fade-in" style="animation-delay:.2s">
                <div class="section-card-header">
                    <div class="section-card-icon" style="background:linear-gradient(135deg,#fca5a5,#ef4444)">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <h3>Zon Bahaya</h3>
                        <p>Tindakan ini tidak boleh diundur</p>
                    </div>
                </div>

                <div class="danger-body">
                    <p>Memadam akaun akan menghapuskan <strong>semua data</strong> anda termasuk rekod tempahan secara kekal.</p>

                    <details class="delete-accordion">
                        <summary>
                            <i class="fa-solid fa-trash"></i> Padam Akaun Saya
                        </summary>
                        <div class="delete-form">
                            <p class="delete-warning">
                                ⚠️ Taip <strong>PADAM</strong> dalam kotak di bawah untuk mengesahkan:
                            </p>
                            <form method="POST"
                                  onsubmit="return confirm('Adakah anda PASTI ingin memadam akaun ini? Ini tidak boleh diundur!');">
                                <input type="hidden" name="delete_account" value="1">
                                <div class="form-row-2" style="align-items:flex-end;gap:12px;">
                                    <div class="form-group" style="margin-bottom:0;">
                                        <input type="text" name="confirm_delete" class="form-control"
                                               placeholder='Taip "PADAM" di sini' autocomplete="off">
                                    </div>
                                    <button type="submit" class="btn btn-danger" style="white-space:nowrap;">
                                        <i class="fa-solid fa-trash"></i> Padam Akaun
                                    </button>
                                </div>
                            </form>
                        </div>
                    </details>
                </div>
            </div>

        </div><!-- /.profile-main -->
    </div><!-- /.profile-layout -->

</main>

<style>
/* ── Page Head ─────────────────────────────── */
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
    font-size: 2.6rem; font-weight: 800; color: var(--text-dark);
    line-height: 1.1; margin-top: 0 !important; margin-bottom: 10px; letter-spacing: -0.02em;
}
.page-head h1 em { font-style: italic; color: var(--primary); }
.page-head p { font-size: 1.05rem; color: var(--text-muted); max-width: 620px; }

/* ── Layout ────────────────────────────────── */
.profile-layout {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 28px;
    align-items: flex-start;
    margin-bottom: 60px;
}

/* ── Sidebar Card ──────────────────────────── */
.profile-card {
    background: white;
    border: 1px solid var(--border-card);
    border-radius: var(--radius-xl);
    padding: 36px 28px;
    text-align: center;
    box-shadow: var(--shadow-md);
    position: sticky;
    top: 120px;
}
.profile-avatar-wrap {
    position: relative;
    display: inline-block;
    margin-bottom: 16px;
}
.profile-avatar {
    width: 90px; height: 90px;
    border-radius: 50%;
    background: linear-gradient(135deg, #a89fe8, #6d5fcc);
    color: white;
    font-size: 2.5rem;
    font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 12px 30px rgba(109, 95, 204, 0.35);
    position: relative; z-index: 1;
}
.profile-avatar-ring {
    position: absolute; inset: -6px;
    border-radius: 50%;
    border: 3px solid var(--primary-light);
    animation: ring-pulse 3s ease-in-out infinite;
}
@keyframes ring-pulse {
    0%,100% { transform: scale(1); opacity: 1; }
    50%      { transform: scale(1.06); opacity: 0.6; }
}
.profile-name {
    font-size: 1.3rem; font-weight: 800; color: var(--text-dark);
    margin-bottom: 8px;
}
.profile-matrik {
    font-size: 0.88rem; color: var(--text-muted);
    font-weight: 600; margin-bottom: 4px;
}
.profile-since {
    font-size: 0.82rem; color: var(--text-muted);
    margin-bottom: 20px; display: flex; align-items: center;
    justify-content: center; gap: 6px;
}
.profile-stats {
    display: flex; align-items: center; justify-content: center;
    gap: 0; background: var(--bg-cream);
    border-radius: var(--radius-sm); padding: 16px;
    border: 1px solid var(--border-soft); margin-bottom: 4px;
}
.ps-item { flex: 1; }
.ps-item strong { display: block; font-size: 1.6rem; font-weight: 800; color: var(--primary); }
.ps-item span { font-size: 0.76rem; color: var(--text-muted); font-weight: 600; }
.ps-divider { width: 1px; height: 40px; background: var(--border-soft); }

/* ── Section Cards ─────────────────────────── */
.profile-section-card {
    background: white;
    border: 1px solid var(--border-card);
    border-radius: var(--radius-xl);
    padding: 32px;
    box-shadow: var(--shadow-md);
    margin-bottom: 24px;
}
.section-card-header {
    display: flex; align-items: center; gap: 16px;
    padding-bottom: 22px; border-bottom: 1px solid var(--border-soft);
    margin-bottom: 24px;
}
.section-card-icon {
    width: 48px; height: 48px;
    border-radius: 14px; color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0;
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}
.section-card-header h3 {
    font-size: 1.15rem; font-weight: 800; color: var(--text-dark); margin-bottom: 2px;
}
.section-card-header p { font-size: 0.85rem; color: var(--text-muted); }

/* ── Form helpers ──────────────────────────── */
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.req { color: var(--danger); }
.pass-divider {
    border-top: 1px dashed var(--border-soft); padding-top: 16px;
    margin: 8px 0 20px; font-size: 0.82rem; font-weight: 600;
    color: var(--text-muted);
}
.pass-divider em { font-weight: 400; }
.form-actions { display: flex; justify-content: flex-end; padding-top: 4px; }

/* ── History Table ─────────────────────────── */
.history-table-wrap { overflow-x: auto; border-radius: var(--radius-sm); }
.history-table {
    width: 100%; border-collapse: collapse;
    font-size: 0.9rem;
}
.history-table th {
    text-align: left; padding: 10px 14px;
    background: var(--bg-cream); color: var(--text-muted);
    font-size: 0.78rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.history-table th:first-child { border-radius: 10px 0 0 10px; }
.history-table th:last-child  { border-radius: 0 10px 10px 0; }
.history-table td {
    padding: 12px 14px; border-bottom: 1px solid var(--border-soft);
    color: var(--text-dark); font-weight: 500;
}
.history-table tr:last-child td { border-bottom: none; }
.history-table tr:hover td { background: var(--bg-cream); }
.history-empty {
    text-align: center; padding: 40px 20px;
    color: var(--text-muted);
}

/* ── Danger Zone ───────────────────────────── */
.danger-zone { border-color: #fee2e2; }
.danger-body { font-size: 0.92rem; color: var(--text-body); }
.danger-body p { margin-bottom: 16px; }
.delete-accordion {
    border: 1px solid #fecaca; border-radius: var(--radius-sm);
    overflow: hidden;
}
.delete-accordion summary {
    padding: 14px 18px; cursor: pointer;
    font-weight: 700; color: var(--danger);
    background: var(--danger-bg); list-style: none;
    display: flex; align-items: center; gap: 10px;
    user-select: none;
    transition: background 0.2s;
}
.delete-accordion summary:hover { background: #fecaca; }
.delete-accordion[open] summary { border-bottom: 1px solid #fecaca; }
.delete-form {
    padding: 20px 18px; background: white;
}
.delete-warning {
    font-size: 0.88rem; color: #991b1b;
    margin-bottom: 14px;
    padding: 10px 14px; background: var(--danger-bg);
    border-radius: 8px;
}

/* ── Responsive ────────────────────────────── */
@media (max-width: 1024px) {
    .profile-layout { grid-template-columns: 1fr; }
    .profile-card { position: static; max-width: 400px; margin: 0 auto; }
}
@media (max-width: 640px) {
    .form-row-2 { grid-template-columns: 1fr; }
    .page-head h1 { font-size: 2rem; }
    .profile-section-card { padding: 22px; }
}
</style>

<?php include 'footer.php'; ?>
