<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id = $_SESSION['user_id'];

// Fetch user's reservations with book info
$stmt = $conn->prepare('
    SELECT br.*, b.title, b.author, b.isbn, b.cover_color, b.category, b.publisher
    FROM book_reservations br
    JOIN books b ON br.book_id = b.id
    WHERE br.user_id = ?
    ORDER BY br.created_at DESC
');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count by status
$counts = ['pending'=>0,'approved'=>0,'rejected'=>0,'returned'=>0];
foreach ($reservations as $r) { $counts[$r['status']]++; }

$active_tab = $_GET['filter'] ?? 'all';

$page_title = 'Status Buku';
include 'header.php';
?>

<main class="container sb-main-container">

<div class="sb-hero-head fade-in">
    <div class="sb-hero-bg">
        <div class="sb-blob sb-blob-1"></div>
        <div class="sb-blob sb-blob-2"></div>
        <div class="sb-grid-overlay"></div>
    </div>
    <div class="sb-hero-content">
        <span class="sb-eyebrow"><i class="fa-solid fa-satellite-dish"></i> Radar Perpustakaan</span>
        <h1>Status <em>Tempahan</em> Anda</h1>
        <p>Pantau kelulusan buku, dapatkan Pas Akses (QR) untuk pengambilan, dan jejak tarikh pemulangan koleksi anda dengan mudah.</p>
    </div>
</div>

<!-- Stats Row -->
<div class="sb-stats fade-in" style="animation-delay: 0.1s;">
    <div class="sb-stat-glass" style="--accent:#f59e0b; --bg:rgba(245,158,11,0.1)">
        <div class="sb-stat-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
        <div class="sb-stat-info">
            <div class="sb-stat-num"><?= $counts['pending'] ?></div>
            <div class="sb-stat-label">Menunggu</div>
        </div>
    </div>
    <div class="sb-stat-glass" style="--accent:#10b981; --bg:rgba(16,185,129,0.1)">
        <div class="sb-stat-icon"><i class="fa-solid fa-check-double"></i></div>
        <div class="sb-stat-info">
            <div class="sb-stat-num"><?= $counts['approved'] ?></div>
            <div class="sb-stat-label">Diluluskan</div>
        </div>
    </div>
    <div class="sb-stat-glass" style="--accent:#ef4444; --bg:rgba(239,68,68,0.1)">
        <div class="sb-stat-icon"><i class="fa-solid fa-ban"></i></div>
        <div class="sb-stat-info">
            <div class="sb-stat-num"><?= $counts['rejected'] ?></div>
            <div class="sb-stat-label">Ditolak</div>
        </div>
    </div>
    <div class="sb-stat-glass" style="--accent:#6d5fcc; --bg:rgba(109,95,204,0.1)">
        <div class="sb-stat-icon"><i class="fa-solid fa-box-archive"></i></div>
        <div class="sb-stat-info">
            <div class="sb-stat-num"><?= $counts['returned'] ?></div>
            <div class="sb-stat-label">Dipulangkan</div>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<div class="sb-tabs-wrapper fade-in" style="animation-delay: 0.2s;">
    <div class="sb-tabs">
        <a href="?filter=all" class="sb-tab <?= $active_tab==='all'?'active':'' ?>">
            <i class="fa-solid fa-layer-group"></i> Semua <span class="tab-badge"><?= count($reservations) ?></span>
        </a>
        <a href="?filter=pending" class="sb-tab <?= $active_tab==='pending'?'active':'' ?>">
            <i class="fa-solid fa-hourglass-half"></i> Menunggu <span class="tab-badge"><?= $counts['pending'] ?></span>
        </a>
        <a href="?filter=approved" class="sb-tab <?= $active_tab==='approved'?'active':'' ?>">
            <i class="fa-regular fa-circle-check"></i> Diluluskan <span class="tab-badge"><?= $counts['approved'] ?></span>
        </a>
    </div>
</div>

<?php
$filtered = $reservations;
if ($active_tab !== 'all') {
    $filtered = array_filter($reservations, fn($r) => $r['status'] === $active_tab);
}
?>

<?php if (empty($filtered)): ?>
<div class="sb-empty-state fade-in" style="animation-delay: 0.3s;">
    <div class="empty-icon"><i class="fa-solid fa-ghost"></i></div>
    <h3>Tiada Rekod Ditemui</h3>
    <p>Anda belum membuat sebarang tempahan atau tiada status yang sepadan.</p>
    <a href="tempah_buku.php" class="btn btn-primary sb-btn-glow"><i class="fa-solid fa-book"></i> Mula Meneroka Katalog</a>
</div>
<?php else: ?>

<div class="sb-list">
<?php 
$delay = 0.3;
foreach ($filtered as $res):
    $st_map = [
        'pending'  => ['label'=>'Menunggu','cls'=>'sb-card-pending','icon'=>'fa-clock','color'=>'#f59e0b'],
        'approved' => ['label'=>'Akses Lulus','cls'=>'sb-card-approved','icon'=>'fa-shield-check','color'=>'#10b981'],
        'rejected' => ['label'=>'Ditolak','cls'=>'sb-card-rejected','icon'=>'fa-circle-xmark','color'=>'#ef4444'],
        'returned' => ['label'=>'Telah Dipulangkan','cls'=>'sb-card-returned','icon'=>'fa-rotate-left','color'=>'#6d5fcc'],
    ];
    $st = $st_map[$res['status']];
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($res['reservation_code'] . ' | ' . $res['title'] . ' | ' . $res['isbn']) . '&color=1e1b4b&bgcolor=ffffff';
?>
<div class="sb-ticket fade-in <?= $st['cls'] ?>" style="animation-delay: <?= $delay ?>s;">
    <div class="sb-ticket-main">
        <div class="sb-ticket-spine" style="background: linear-gradient(to bottom, <?= $res['cover_color'] ?>, #1e1b4b);"></div>
        <div class="sb-ticket-content">
            <div class="sb-ticket-header">
                <span class="sb-badge-category"><?= htmlspecialchars($res['category']) ?></span>
                <span class="sb-badge-status" style="color:<?= $st['color'] ?>; background:<?= $st['color'] ?>15; border: 1px solid <?= $st['color'] ?>30;">
                    <i class="fa-solid <?= $st['icon'] ?>"></i> <?= $st['label'] ?>
                </span>
            </div>
            
            <h3 class="sb-book-title"><?= htmlspecialchars($res['title']) ?></h3>
            <p class="sb-book-author"><i class="fa-solid fa-feather-pointed"></i> <?= htmlspecialchars($res['author']) ?></p>
            
            <div class="sb-book-meta">
                <div class="sb-meta-item">
                    <span class="meta-label">ISBN</span>
                    <span class="meta-val"><?= htmlspecialchars($res['isbn']) ?></span>
                </div>
                <div class="sb-meta-item">
                    <span class="meta-label">Penerbit</span>
                    <span class="meta-val"><?= htmlspecialchars($res['publisher']) ?></span>
                </div>
                <div class="sb-meta-item">
                    <span class="meta-label">Mohon</span>
                    <span class="meta-val"><?= date('d M Y', strtotime($res['created_at'])) ?></span>
                </div>
                <?php if ($res['due_date']): ?>
                <div class="sb-meta-item meta-due">
                    <span class="meta-label">Tarikh Pulang</span>
                    <span class="meta-val"><?= date('d M Y', strtotime($res['due_date'])) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($res['admin_note']): ?>
            <div class="sb-admin-note">
                <div class="note-icon"><i class="fa-solid fa-comment-dots"></i></div>
                <div class="note-content">
                    <strong>Nota Admin</strong>
                    <p><?= htmlspecialchars($res['admin_note']) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="sb-ticket-separator"></div>

    <div class="sb-ticket-side">
        <?php if ($res['status'] === 'approved' || $res['status'] === 'returned'): ?>
            <div class="sb-qr-box">
                <img src="<?= $qr_url ?>" alt="QR Code" class="sb-qr-image" loading="lazy">
                <div class="sb-qr-scanner-line"></div>
            </div>
            <div class="sb-code-group">
                <span class="sb-code-title">KOD AKSES</span>
                <span class="sb-code-hash"><?= htmlspecialchars($res['reservation_code']) ?></span>
                <button class="sb-btn-copy" data-code="<?= htmlspecialchars($res['reservation_code']) ?>">
                    <i class="fa-regular fa-copy"></i> Salin Kod
                </button>
            </div>
        <?php elseif ($res['status'] === 'pending'): ?>
            <div class="sb-pending-side">
                <div class="sb-hourglass-loader">
                    <i class="fa-solid fa-hourglass-empty fa-spin"></i>
                </div>
                <span class="sb-code-title">KOD SEMENTARA</span>
                <span class="sb-code-hash" style="color:var(--text-muted);"><?= htmlspecialchars($res['reservation_code']) ?></span>
                <button class="sb-btn-cancel" data-id="<?= $res['id'] ?>">
                    <i class="fa-solid fa-xmark"></i> Batal Tempahan
                </button>
            </div>
        <?php else: ?>
            <div class="sb-rejected-side">
                <i class="fa-solid fa-ban sb-huge-icon"></i>
                <span>Permohonan<br>Ditolak</span>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php 
    $delay += 0.1;
endforeach; 
?>
</div>

<?php endif; ?>
</main>

<style>
/* ─── Layout & Hero ─── */
.sb-main-container { padding-top: 40px; padding-bottom: 80px; }
.sb-hero-head { position: relative; border-radius: 24px; padding: 48px 56px; overflow: hidden; margin-bottom: 40px; box-shadow: 0 20px 50px rgba(30,27,75,0.15); display: flex; align-items: center; justify-content: flex-start; }
.sb-hero-bg { position: absolute; inset: 0; background: linear-gradient(135deg, #1e1b4b 0%, #312e6b 40%, #4338ca 100%); z-index: 1; overflow: hidden; }
.sb-grid-overlay { position: absolute; inset: 0; background-image: linear-gradient(rgba(255,255,255,0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.05) 1px, transparent 1px); background-size: 30px 30px; opacity: 0.5; }
.sb-blob { position: absolute; border-radius: 50%; filter: blur(60px); opacity: 0.6; animation: sbDrift 10s infinite alternate ease-in-out; }
.sb-blob-1 { width: 300px; height: 300px; background: #6d5fcc; top: -100px; right: -50px; }
.sb-blob-2 { width: 250px; height: 250px; background: #f59e0b; bottom: -80px; right: 20%; animation-delay: -5s; opacity: 0.4; }
.sb-hero-content { position: relative; z-index: 2; max-width: 600px; }
.sb-eyebrow { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); border-radius: 30px; color: #c4bbf0; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px; backdrop-filter: blur(10px); }
.sb-hero-content h1 { font-size: 2.8rem; font-weight: 800; color: white; margin-bottom: 16px; line-height: 1.1; letter-spacing: -1px; }
.sb-hero-content h1 em { font-style: normal; background: linear-gradient(to right, #fca5a5, #fcd34d); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.sb-hero-content p { font-size: 1.05rem; color: #a5b4fc; line-height: 1.6; }

@keyframes sbDrift { from { transform: translate(0,0) scale(1); } to { transform: translate(-30px, 40px) scale(1.1); } }

/* ─── Stats Glassmorphism ─── */
.sb-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 36px; }
.sb-stat-glass { background: white; border: 1px solid var(--border-card); border-radius: 20px; padding: 24px; display: flex; align-items: center; gap: 18px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); transition: all 0.3s ease; position: relative; overflow: hidden; }
.sb-stat-glass:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.08); border-color: var(--accent); }
.sb-stat-glass::before { content: ''; position: absolute; top: 0; left: 0; bottom: 0; width: 4px; background: var(--accent); }
.sb-stat-icon { width: 54px; height: 54px; border-radius: 14px; background: var(--bg); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
.sb-stat-info { display: flex; flex-direction: column; }
.sb-stat-num { font-size: 1.8rem; font-weight: 800; color: var(--text-dark); line-height: 1; margin-bottom: 4px; }
.sb-stat-label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }

/* ─── Filter Tabs ─── */
.sb-tabs-wrapper { display: flex; margin-bottom: 40px; }
.sb-tabs { display: inline-flex; background: white; padding: 6px; border-radius: 16px; border: 1px solid var(--border-card); box-shadow: 0 4px 15px rgba(0,0,0,0.02); gap: 6px; }
.sb-tab { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 12px; font-size: 0.9rem; font-weight: 600; color: var(--text-muted); transition: all 0.2s; }
.sb-tab:hover { color: var(--primary); background: var(--primary-light); }
.sb-tab.active { background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(109,95,204,0.3); }
.tab-badge { display: inline-flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.08); padding: 2px 8px; border-radius: 20px; font-size: 0.75rem; }
.sb-tab.active .tab-badge { background: rgba(255,255,255,0.2); }

/* ─── Premium Ticket List ─── */
.sb-list { display: flex; flex-direction: column; gap: 24px; }
.sb-ticket { display: flex; background: white; border-radius: 20px; border: 1px solid var(--border-card); overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.04); transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); position: relative; }
.sb-ticket:hover { transform: translateY(-6px); box-shadow: 0 20px 40px rgba(109,95,204,0.12); border-color: rgba(109,95,204,0.3); }
.sb-ticket-main { flex: 1; display: flex; }
.sb-ticket-spine { width: 8px; flex-shrink: 0; }
.sb-ticket-content { padding: 30px; flex: 1; }
.sb-ticket-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.sb-badge-category { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--primary); background: var(--primary-light); padding: 4px 12px; border-radius: 6px; }
.sb-badge-status { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 30px; font-size: 0.8rem; font-weight: 700; }
.sb-book-title { font-size: 1.4rem; font-weight: 800; color: var(--text-dark); margin-bottom: 6px; line-height: 1.3; }
.sb-book-author { font-size: 0.95rem; color: var(--text-muted); font-weight: 600; margin-bottom: 24px; display: flex; align-items: center; gap: 8px; }

.sb-book-meta { display: flex; flex-wrap: wrap; gap: 20px 30px; padding: 20px; background: #f8f9fc; border-radius: 12px; border: 1px dashed var(--border-soft); }
.sb-meta-item { display: flex; flex-direction: column; gap: 4px; }
.meta-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }
.meta-val { font-size: 0.9rem; font-weight: 600; color: var(--text-dark); }
.meta-due .meta-val { color: var(--primary); font-weight: 800; }

.sb-admin-note { margin-top: 20px; padding: 16px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; display: flex; gap: 14px; }
.note-icon { color: #d97706; font-size: 1.2rem; margin-top: 2px; }
.note-content strong { display: block; font-size: 0.8rem; color: #92400e; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.note-content p { margin: 0; font-size: 0.9rem; color: #b45309; line-height: 1.5; }

/* Ticket Separator */
.sb-ticket-separator { width: 2px; background-image: linear-gradient(to bottom, var(--border-soft) 50%, transparent 50%); background-size: 2px 14px; background-repeat: repeat-y; position: relative; }
.sb-ticket-separator::before, .sb-ticket-separator::after { content: ''; position: absolute; left: -14px; width: 30px; height: 30px; background: #f4f5f9; border-radius: 50%; box-shadow: inset 0 0 4px rgba(0,0,0,0.05); }
.sb-ticket-separator::before { top: -15px; border-bottom: 1px solid var(--border-card); }
.sb-ticket-separator::after { bottom: -15px; border-top: 1px solid var(--border-card); }

/* Ticket Side */
.sb-ticket-side { width: 240px; padding: 30px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; background: white; flex-shrink: 0; }
.sb-qr-box { width: 140px; height: 140px; border-radius: 16px; padding: 8px; background: white; border: 2px solid var(--primary-light); box-shadow: 0 10px 25px rgba(109,95,204,0.15); margin-bottom: 16px; position: relative; overflow: hidden; }
.sb-qr-image { width: 100%; height: 100%; object-fit: contain; }
.sb-qr-scanner-line { position: absolute; left: 0; right: 0; top: 0; height: 3px; background: rgba(16,185,129,0.7); box-shadow: 0 0 15px rgba(16,185,129,1); animation: scan 2.5s infinite linear; }
@keyframes scan { 0% { top: -5px; opacity: 0; } 10% { opacity: 1; } 90% { opacity: 1; } 100% { top: 105%; opacity: 0; } }

.sb-code-group { display: flex; flex-direction: column; align-items: center; gap: 6px; }
.sb-code-title { font-size: 0.7rem; font-weight: 800; color: var(--text-muted); letter-spacing: 1px; }
.sb-code-hash { font-size: 1.4rem; font-weight: 800; color: var(--primary); font-family: 'Courier New', monospace; letter-spacing: 1px; }
.sb-btn-copy { background: var(--primary-light); color: var(--primary); border: none; padding: 6px 14px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; transition: all 0.2s; margin-top: 4px; }
.sb-btn-copy:hover { background: var(--primary); color: white; }

.sb-pending-side { display: flex; flex-direction: column; align-items: center; gap: 10px; width: 100%; }
.sb-hourglass-loader { font-size: 2.5rem; color: #f59e0b; margin-bottom: 10px; opacity: 0.6; }
.sb-btn-cancel { background: transparent; border: 1px solid #fecaca; color: #ef4444; padding: 8px 16px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: all 0.2s; margin-top: 10px; width: 100%; }
.sb-btn-cancel:hover { background: #fee2e2; border-color: #ef4444; }

.sb-rejected-side { color: #ef4444; opacity: 0.7; }
.sb-huge-icon { font-size: 3rem; margin-bottom: 12px; }

/* ─── Empty State ─── */
.sb-empty-state { text-align: center; padding: 60px 20px; background: white; border: 1px dashed var(--border-card); border-radius: 20px; }
.sb-empty-state .empty-icon { font-size: 3.5rem; margin-bottom: 16px; color: var(--primary-light); }
.sb-empty-state h3 { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; }
.sb-empty-state p { color: var(--text-muted); margin-bottom: 24px; }
.sb-btn-glow { box-shadow: 0 10px 25px rgba(109,95,204,0.3); }
.sb-btn-glow:hover { box-shadow: 0 15px 35px rgba(109,95,204,0.4); transform: translateY(-2px); }

@media(max-width: 900px) {
    .sb-ticket { flex-direction: column; }
    .sb-ticket-separator { width: 100%; height: 2px; background-image: linear-gradient(to right, var(--border-soft) 50%, transparent 50%); background-size: 14px 2px; background-repeat: repeat-x; }
    .sb-ticket-separator::before { top: auto; bottom: -15px; left: -15px; border: none; border-right: 1px solid var(--border-card); }
    .sb-ticket-separator::after { top: auto; bottom: -15px; left: auto; right: -15px; border: none; border-left: 1px solid var(--border-card); }
    .sb-ticket-side { width: 100%; border-top: none; }
}
@media(max-width: 640px) {
    .sb-hero-head { padding: 30px 24px; border-radius: 16px; }
    .sb-hero-content h1 { font-size: 2rem; }
    .sb-ticket-content { padding: 20px; }
    .sb-book-meta { flex-direction: column; gap: 12px; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Copy code
    document.querySelectorAll('.sb-btn-copy').forEach(btn => {
        btn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(btn.dataset.code);
                const orig = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Disalin!';
                btn.style.color = '#10b981';
                setTimeout(() => { btn.innerHTML = orig; btn.style.color = ''; }, 1500);
            } catch(e) { /* fallback */ }
        });
    });

    // Cancel reservation
    document.querySelectorAll('.sb-btn-cancel').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Pasti untuk batalkan permohonan ini?')) return;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            try {
                const fd = new FormData();
                fd.append('action', 'cancel_reservation');
                fd.append('reservation_id', btn.dataset.id);
                const r = await fetch('book_api.php', {method:'POST',body:fd});
                const d = await r.json();
                if (d.success) {
                    const card = btn.closest('.sb-ticket');
                    card.style.transition = 'all .4s';
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(30px)';
                    setTimeout(() => { card.remove(); location.reload(); }, 500);
                } else {
                    alert(d.error || 'Gagal');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-trash"></i> Batal';
                }
            } catch(e) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-trash"></i> Batal';
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>
