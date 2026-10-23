<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: dashboard.php'); exit; }

// Fetch all books
$books = $conn->query('SELECT * FROM books ORDER BY title ASC')->fetch_all(MYSQLI_ASSOC);

// Fetch room images
$room_images = [];
$ri_result = $conn->query('SELECT room_name, image_path FROM room_images');
if ($ri_result) {
    while ($ri = $ri_result->fetch_assoc()) {
        $room_images[$ri['room_name']] = $ri['image_path'];
    }
}
$all_rooms = ['Bilik Bacaan 1','Bilik Bacaan 2','Bilik Mesyuarat','Ruang Seminar','Makmal Komputer','Studio Rakaman'];

// Fetch all reservations with user+book info
$reservations = $conn->query('
    SELECT br.*, b.title AS book_title, b.isbn, b.cover_color, b.author, u.nama AS user_nama, u.no_matrik, u.email
    FROM book_reservations br
    JOIN books b ON br.book_id = b.id
    JOIN users u ON br.user_id = u.id
    ORDER BY br.created_at DESC
')->fetch_all(MYSQLI_ASSOC);

$pending_count = count(array_filter($reservations, fn($r) => $r['status'] === 'pending'));
$active_tab = $_GET['tab'] ?? 'reservations';

$page_title = 'Urus Buku';
include 'header.php';
?>

<main class="container" style="padding-top:20px;">

<section class="admin-hero fade-in" style="margin-bottom:36px;">
    <div class="admin-hero-content">
        <div class="admin-badge"><i class="fa-solid fa-book-bookmark"></i> Pengurusan Buku</div>
        <h1><span class="text-hai">Urus Buku</span><br><span class="text-discover">& <em>Tempahan</em> Perpustakaan.</span></h1>
        <p>Luluskan permohonan, urus koleksi buku, dan kawal status ketersediaan buku perpustakaan.</p>
    </div>
    <div class="admin-hero-visual">
        <div class="admin-orb orb-1"></div><div class="admin-orb orb-2"></div><div class="admin-orb orb-3"></div>
        <div class="admin-hero-icon"><i class="fa-solid fa-book-bookmark"></i></div>
    </div>
</section>

<!-- Stats -->
<div class="admin-stats-grid" style="margin-bottom:36px;">
    <div class="admin-stat-card scroll-reveal lift-on-hover" style="--accent:#6d5fcc"><div class="asc-icon"><i class="fa-solid fa-book"></i></div><div class="asc-value"><?= count($books) ?></div><div class="asc-label">Jumlah Buku</div><div class="asc-bar" style="--fill:100%"></div></div>
    <div class="admin-stat-card scroll-reveal lift-on-hover" style="--accent:#f59e0b"><div class="asc-icon"><i class="fa-solid fa-clock"></i></div><div class="asc-value"><?= $pending_count ?></div><div class="asc-label">Menunggu Kelulusan</div><div class="asc-bar" style="--fill:<?= min($pending_count*20,100) ?>%"></div></div>
    <div class="admin-stat-card scroll-reveal lift-on-hover" style="--accent:#10b981"><div class="asc-icon"><i class="fa-solid fa-check-double"></i></div><div class="asc-value"><?= count(array_filter($reservations, fn($r)=>$r['status']==='approved')) ?></div><div class="asc-label">Sedang Dipinjam</div><div class="asc-bar" style="--fill:60%"></div></div>
    <div class="admin-stat-card scroll-reveal lift-on-hover" style="--accent:#2563eb"><div class="asc-icon"><i class="fa-solid fa-layer-group"></i></div><div class="asc-value"><?= count($reservations) ?></div><div class="asc-label">Jumlah Tempahan</div><div class="asc-bar" style="--fill:100%"></div></div>
</div>

<!-- Tabs -->
<div class="status-tabs fade-in" style="margin-bottom:28px;">
    <a href="?tab=reservations" class="status-tab <?= $active_tab==='reservations'?'active':'' ?>">
        <i class="fa-solid fa-inbox"></i> Tempahan
        <?php if ($pending_count > 0): ?><span class="tab-count" style="background:#f59e0b;color:white;"><?= $pending_count ?></span><?php endif; ?>
    </a>
    <a href="?tab=books" class="status-tab <?= $active_tab==='books'?'active':'' ?>"><i class="fa-solid fa-book"></i> Koleksi Buku</a>
    <a href="?tab=add" class="status-tab <?= $active_tab==='add'?'active':'' ?>"><i class="fa-solid fa-plus"></i> Tambah Buku</a>
    <a href="?tab=images" class="status-tab <?= $active_tab==='images'?'active':'' ?>"><i class="fa-solid fa-image"></i> Gambar</a>
</div>

<?php if ($active_tab === 'reservations'): ?>
<!-- ══════════ RESERVATIONS TABLE ══════════ -->
<section class="admin-section">
    <div class="section-header"><div><h2>📋 Senarai Tempahan Buku</h2><p>Luluskan atau tolak permohonan tempahan buku oleh pengguna.</p></div></div>

    <?php if (empty($reservations)): ?>
    <div class="empty-card"><div class="empty-icon">📭</div><h3>Tiada tempahan</h3></div>
    <?php else: ?>
    <div class="admin-table-wrap scroll-reveal">
        <table class="admin-table">
            <thead><tr><th>ID</th><th>Pemohon</th><th>Buku</th><th>Kod</th><th>Tarikh</th><th>Status</th><th>Tindakan</th></tr></thead>
            <tbody>
            <?php foreach ($reservations as $res):
                $sc=['pending'=>'badge-warning','approved'=>'badge-success','rejected'=>'badge badge-danger','returned'=>'badge-primary'];
                $sl=['pending'=>'⏳ Pending','approved'=>'✅ Diluluskan','rejected'=>'❌ Ditolak','returned'=>'📦 Dipulangkan'];
            ?>
            <tr id="res-row-<?= $res['id'] ?>">
                <td class="td-id">#<?= $res['id'] ?></td>
                <td><div class="td-user"><div class="avatar-mini"><?= mb_strtoupper(mb_substr($res['user_nama'],0,1)) ?></div><div><strong><?= htmlspecialchars($res['user_nama']) ?></strong><br><small style="color:var(--text-muted)"><?= htmlspecialchars($res['no_matrik']) ?></small></div></div></td>
                <td><div style="display:flex;align-items:center;gap:8px"><div style="width:8px;height:30px;border-radius:3px;background:<?= $res['cover_color'] ?>;flex-shrink:0"></div><div><strong><?= htmlspecialchars(mb_strimwidth($res['book_title'],0,30,'...')) ?></strong><br><small style="color:var(--text-muted)"><?= htmlspecialchars($res['isbn']) ?></small></div></div></td>
                <td><code style="background:var(--primary-light);padding:3px 8px;border-radius:6px;font-size:.82rem;color:var(--primary);font-weight:700"><?= htmlspecialchars($res['reservation_code']) ?></code></td>
                <td><?= date('d M Y', strtotime($res['created_at'])) ?></td>
                <td><span class="badge <?= $sc[$res['status']] ?? 'badge-primary' ?>"><?= $sl[$res['status']] ?? $res['status'] ?></span></td>
                <td>
                    <?php if ($res['status'] === 'pending'): ?>
                    <div style="display:flex;gap:6px">
                        <button class="btn btn-primary btn-sm ab-approve-btn" data-id="<?= $res['id'] ?>" data-title="<?= htmlspecialchars($res['book_title']) ?>"><i class="fa-solid fa-check"></i></button>
                        <button class="btn btn-danger btn-sm ab-reject-btn" data-id="<?= $res['id'] ?>" data-title="<?= htmlspecialchars($res['book_title']) ?>"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <?php elseif ($res['status'] === 'approved'): ?>
                    <button class="btn btn-ghost btn-sm ab-return-btn" data-id="<?= $res['id'] ?>"><i class="fa-solid fa-rotate-left"></i> Pulang</button>
                    <?php else: ?>
                    <span style="color:var(--text-muted);font-size:.8rem">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php elseif ($active_tab === 'books'): ?>
<!-- ══════════ BOOK COLLECTION TABLE ══════════ -->
<section class="admin-section">
    <div class="section-header"><div><h2>📚 Koleksi Buku</h2><p>Urus status ketersediaan setiap buku dalam perpustakaan.</p></div></div>

    <div class="admin-table-wrap scroll-reveal">
        <table class="admin-table">
            <thead><tr><th>ID</th><th>Gambar</th><th>Buku</th><th>Kategori</th><th>Naskah</th><th>Status</th><th>Tindakan</th></tr></thead>
            <tbody>
            <?php foreach ($books as $bk):
                $st_cls = ['ada'=>'badge-success','sedang_ditempah'=>'badge-warning','tidak_tersedia'=>'badge badge-danger'][$bk['status']];
                $st_lbl = ['ada'=>'Ada','sedang_ditempah'=>'Sedang Ditempah','tidak_tersedia'=>'Tidak Tersedia'][$bk['status']];
            ?>
            <tr id="book-row-<?= $bk['id'] ?>">
                <td class="td-id">#<?= $bk['id'] ?></td>
                <td>
                    <div class="ab-img-cell">
                        <?php if (!empty($bk['cover_image'])): ?>
                            <img src="<?= htmlspecialchars($bk['cover_image']) ?>" alt="" class="ab-thumb">
                        <?php else: ?>
                            <div class="ab-thumb-placeholder" style="background:<?= $bk['cover_color'] ?>"><i class="fa-solid fa-book-open"></i></div>
                        <?php endif; ?>
                        <label class="ab-img-upload-btn" title="Tukar gambar">
                            <i class="fa-solid fa-camera"></i>
                            <input type="file" accept="image/*" class="ab-book-img-input" data-id="<?= $bk['id'] ?>" hidden>
                        </label>
                    </div>
                </td>
                <td><div style="display:flex;align-items:center;gap:10px"><div style="width:8px;height:36px;border-radius:3px;background:<?= $bk['cover_color'] ?>;flex-shrink:0"></div><div><strong><?= htmlspecialchars($bk['title']) ?></strong><br><small style="color:var(--text-muted)"><?= htmlspecialchars($bk['author']) ?> · <?= htmlspecialchars($bk['isbn']) ?></small></div></div></td>
                <td><span class="badge badge-primary"><?= htmlspecialchars($bk['category']) ?></span></td>
                <td><strong><?= $bk['total_copies'] ?></strong></td>
                <td>
                    <select class="form-control ab-status-select" data-id="<?= $bk['id'] ?>" style="padding:8px 12px;font-size:.82rem;min-width:150px;">
                        <option value="ada" <?= $bk['status']==='ada'?'selected':'' ?>>🟢 Ada</option>
                        <option value="sedang_ditempah" <?= $bk['status']==='sedang_ditempah'?'selected':'' ?>>🟡 Sedang Ditempah</option>
                        <option value="tidak_tersedia" <?= $bk['status']==='tidak_tersedia'?'selected':'' ?>>🔴 Tidak Tersedia</option>
                    </select>
                </td>
                <td><button class="btn btn-danger btn-sm ab-delete-book" data-id="<?= $bk['id'] ?>" data-title="<?= htmlspecialchars($bk['title']) ?>"><i class="fa-solid fa-trash"></i></button></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php elseif ($active_tab === 'add'): ?>
<!-- ══════════ ADD BOOK FORM ══════════ -->
<section class="admin-section">
    <div class="section-header"><div><h2>➕ Tambah Buku Baru</h2><p>Isi maklumat buku untuk ditambah ke dalam koleksi perpustakaan.</p></div></div>

    <div class="card card-lg" style="max-width:800px;">
        <form id="addBookForm">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <div class="form-group" style="grid-column:span 2">
                    <label class="form-label">Tajuk Buku *</label>
                    <input type="text" name="title" class="form-control" placeholder="Cth: Introduction to Algorithms" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Penulis *</label>
                    <input type="text" name="author" class="form-control" placeholder="Nama penulis utama" required>
                </div>
                <div class="form-group">
                    <label class="form-label">ISBN *</label>
                    <input type="text" name="isbn" class="form-control" required placeholder="978-XXX-XXX-XXX-X">
                </div>
                <div class="form-group">
                    <label class="form-label">Penerbit</label>
                    <input type="text" name="publisher" class="form-control" placeholder="Syarikat penerbit">
                </div>
                <div class="form-group">
                    <label class="form-label">Tahun Terbit</label>
                    <input type="number" name="year_published" class="form-control" value="<?= date('Y') ?>" min="1900" max="2099">
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <div class="select-wrapper">
                        <select name="category" class="form-control">
                            <option>Sastera</option><option>Sains</option><option>Teknologi</option><option>Sejarah</option>
                            <option>Kejuruteraan</option><option>Perniagaan</option><option>Pendidikan</option><option>Seni & Reka Bentuk</option><option>Umum</option>
                        </select>
                        <i class="fa-solid fa-chevron-down select-icon"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Jumlah Naskah</label>
                    <input type="number" name="total_copies" class="form-control" value="1" min="1">
                </div>
                <div class="form-group" style="grid-column:span 2">
                    <label class="form-label">Penerangan / Sinopsis</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Tulis sinopsis ringkas tentang buku ini..."></textarea>
                </div>

                <!-- Visuals Section -->
                <div class="form-group" style="grid-column:span 2; margin-top: 10px; border-top: 1px dashed var(--border-soft); padding-top: 24px;">
                    <label class="form-label" style="font-size:1.1rem; color:var(--text-dark); margin-bottom:16px;"><i class="fa-solid fa-palette" style="color:var(--primary);margin-right:8px;"></i> Visual Buku</label>
                    <div style="display:grid; grid-template-columns: 1fr 2fr; gap: 24px; align-items:start;">
                        <div>
                            <label class="form-label">Warna Tema Kulit</label>
                            <div class="color-picker-wrap">
                                <input type="color" name="cover_color" class="color-input" value="#6d5fcc">
                                <span class="color-hint">Warna alternatif jika tiada gambar</span>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Gambar Kulit Buku</label>
                            <label class="file-upload-zone">
                                <input type="file" name="cover_image" accept="image/*" class="file-input-hidden" onchange="document.getElementById('fileNameDisplay').textContent = this.files[0] ? this.files[0].name : 'Klik atau seret gambar ke sini'">
                                <div class="upload-content">
                                    <div class="upload-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                                    <span id="fileNameDisplay" style="font-weight:700; color:var(--text-dark); font-size:1rem;">Klik untuk muat naik gambar</span>
                                    <small style="color:var(--text-muted); margin-top:6px; display:block;">Format disokong: JPG, PNG, WEBP (Max 2MB)</small>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="display:flex; justify-content:flex-end; margin-top:32px; border-top: 1px solid var(--border-soft); padding-top: 24px;">
                <button type="submit" class="btn btn-primary btn-lg" style="padding: 14px 36px; font-size: 1.05rem; box-shadow: 0 10px 20px rgba(109,95,204,.25);"><i class="fa-solid fa-bookmark"></i> Simpan Buku</button>
            </div>
            <div id="addBookResult" style="margin-top:16px;"></div>
        </form>
    </div>
</section>

<?php elseif ($active_tab === 'images'): ?>
<!-- ══════════ IMAGE MANAGEMENT ══════════ -->
<section class="admin-section">
    <div class="section-header"><div><h2>🖼️ Gambar Bilik</h2><p>Tukar gambar untuk setiap bilik di halaman tempahan.</p></div></div>

    <div class="ab-room-img-grid">
        <?php foreach ($all_rooms as $rm): ?>
        <div class="ab-room-img-card scroll-reveal">
            <div class="ab-room-img-preview">
                <?php if (!empty($room_images[$rm])): ?>
                    <img src="<?= htmlspecialchars($room_images[$rm]) ?>" alt="<?= htmlspecialchars($rm) ?>" id="preview-<?= md5($rm) ?>">
                <?php else: ?>
                    <div class="ab-room-img-empty" id="preview-<?= md5($rm) ?>"><i class="fa-solid fa-image"></i><span>Tiada gambar</span></div>
                <?php endif; ?>
            </div>
            <div class="ab-room-img-info">
                <h4><?= htmlspecialchars($rm) ?></h4>
                <label class="btn btn-primary btn-sm ab-room-upload-label">
                    <i class="fa-solid fa-upload"></i> Tukar Gambar
                    <input type="file" accept="image/*" class="ab-room-img-input" data-room="<?= htmlspecialchars($rm) ?>" data-hash="<?= md5($rm) ?>" hidden>
                </label>
                <span class="ab-room-img-status" id="status-<?= md5($rm) ?>"></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="admin-section">
    <div class="section-header"><div><h2>📚 Gambar Buku</h2><p>Tukar gambar kulit buku dalam koleksi perpustakaan.</p></div></div>

    <div class="ab-book-img-grid">
        <?php foreach ($books as $bk): ?>
        <div class="ab-book-img-card scroll-reveal">
            <div class="ab-book-img-preview">
                <?php if (!empty($bk['cover_image'])): ?>
                    <img src="<?= htmlspecialchars($bk['cover_image']) ?>" alt="" id="bkpreview-<?= $bk['id'] ?>">
                <?php else: ?>
                    <div class="ab-room-img-empty" style="background:<?= $bk['cover_color'] ?>" id="bkpreview-<?= $bk['id'] ?>"><i class="fa-solid fa-book-open" style="color:white;"></i></div>
                <?php endif; ?>
            </div>
            <div class="ab-room-img-info">
                <h4><?= htmlspecialchars(mb_strimwidth($bk['title'],0,30,'...')) ?></h4>
                <small style="color:var(--text-muted)"><?= htmlspecialchars($bk['author']) ?></small>
                <label class="btn btn-primary btn-sm ab-room-upload-label" style="margin-top:6px;">
                    <i class="fa-solid fa-camera"></i> Tukar
                    <input type="file" accept="image/*" class="ab-book-img-input" data-id="<?= $bk['id'] ?>" hidden>
                </label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

</main>

<!-- Approve Modal -->
<div class="bk-modal-overlay" id="approveOverlay">
    <div class="bk-modal bk-modal-sm">
        <button class="bk-modal-close" onclick="document.getElementById('approveOverlay').classList.remove('open')"><i class="fa-solid fa-xmark"></i></button>
        <div class="bk-modal-body" style="padding:30px;">
            <h2 style="font-size:1.3rem;margin-bottom:16px;"><i class="fa-solid fa-check-circle" style="color:#10b981;"></i> Luluskan Tempahan</h2>
            <p id="approveBookTitle" style="color:var(--text-muted);margin-bottom:18px;font-size:.9rem;"></p>
            <div class="form-group"><label class="form-label">Tarikh Pulang *</label><input type="date" id="approveDueDate" class="form-control" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= date('Y-m-d', strtotime('+14 days')) ?>"></div>
            <div class="form-group"><label class="form-label">Nota (opsional)</label><textarea id="approveNote" class="form-control" rows="2" placeholder="Sila pulangkan sebelum tarikh tamat..."></textarea></div>
            <input type="hidden" id="approveResId" value="">
            <button class="btn btn-primary btn-block" id="confirmApproveBtn"><i class="fa-solid fa-check"></i> Sahkan Kelulusan</button>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="bk-modal-overlay" id="rejectOverlay">
    <div class="bk-modal bk-modal-sm">
        <button class="bk-modal-close" onclick="document.getElementById('rejectOverlay').classList.remove('open')"><i class="fa-solid fa-xmark"></i></button>
        <div class="bk-modal-body" style="padding:30px;">
            <h2 style="font-size:1.3rem;margin-bottom:16px;color:#ef4444;"><i class="fa-solid fa-ban"></i> Tolak Tempahan</h2>
            <p id="rejectBookTitle" style="color:var(--text-muted);margin-bottom:18px;font-size:.9rem;"></p>
            <div class="form-group"><label class="form-label">Sebab Penolakan (opsional)</label><textarea id="rejectNote" class="form-control" rows="3" placeholder="Sebab permohonan ditolak..."></textarea></div>
            <input type="hidden" id="rejectResId" value="">
            <button class="btn btn-danger btn-block" id="confirmRejectBtn" style="width:100%;"><i class="fa-solid fa-xmark"></i> Sahkan Penolakan</button>
        </div>
    </div>
</div>

<style>
/* ── ADMIN HERO ── */
.admin-hero{background:linear-gradient(135deg,#1e1b4b 0%,#312e6b 40%,#4338ca 100%);border-radius:var(--radius-xl);padding:56px 60px;display:grid;grid-template-columns:1.3fr 1fr;gap:32px;align-items:center;position:relative;overflow:hidden;box-shadow:0 20px 60px rgba(30,27,75,.5)}
.admin-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");pointer-events:none}
.admin-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.12);backdrop-filter:blur(10px);padding:8px 18px;border-radius:var(--radius-pill);font-size:.82rem;font-weight:700;color:#c4bbf0;margin-bottom:18px;border:1px solid rgba(255,255,255,.1)}
.admin-hero-content h1{font-size:2.8rem;font-weight:800;line-height:1.08;color:white;margin-bottom:16px;letter-spacing:-.025em}
.admin-hero-content .text-hai{color:#a89fe8}
.admin-hero-content .text-discover{color:#e0e7ff}
.admin-hero-content .text-discover em{color:#ffc48a;font-style:italic}
.admin-hero-content>p{font-size:1.05rem;color:#a5b4fc;line-height:1.6;max-width:500px}
.admin-hero-visual{position:relative;display:flex;align-items:center;justify-content:center;min-height:200px}
.admin-hero-icon{width:120px;height:120px;background:linear-gradient(135deg,rgba(168,159,232,.3),rgba(109,95,204,.5));border-radius:32px;display:flex;align-items:center;justify-content:center;font-size:3rem;color:white;box-shadow:0 20px 60px rgba(109,95,204,.4);animation:hero-float 5s ease-in-out infinite;position:relative;z-index:2;backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.15)}
@keyframes hero-float{0%,100%{transform:translateY(0) rotate(0deg)}50%{transform:translateY(-12px) rotate(3deg)}}
.admin-orb{position:absolute;border-radius:50%;filter:blur(40px);opacity:.5}
.orb-1{width:200px;height:200px;background:#6d5fcc;top:-20px;right:-30px;animation:orbFloat 7s ease-in-out infinite}
.orb-2{width:120px;height:120px;background:#f4a4b8;bottom:-10px;left:10px;animation:orbFloat 5s ease-in-out infinite reverse}
.orb-3{width:80px;height:80px;background:#ffc48a;top:20px;left:40px;animation:orbFloat 9s ease-in-out infinite}
@keyframes orbFloat{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(10px,-15px) scale(1.1)}}

/* ── SECTIONS ── */
.admin-section{margin-bottom:52px}
.section-header{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:26px;gap:20px;flex-wrap:wrap}
.section-header h2{font-size:1.8rem;font-weight:800;color:var(--text-dark);margin-bottom:4px;letter-spacing:-.01em}
.section-header p{font-size:.95rem;color:var(--text-muted)}

/* ── STAT CARDS ── */
.admin-stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
.admin-stat-card{background:white;border:1px solid var(--border-card);border-radius:var(--radius-lg);padding:26px;box-shadow:var(--shadow-sm);position:relative;overflow:hidden}
.admin-stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--accent);border-radius:3px 3px 0 0}
.asc-icon{width:48px;height:48px;border-radius:14px;background:color-mix(in srgb,var(--accent) 12%,white);color:var(--accent);display:flex;align-items:center;justify-content:center;font-size:1.2rem;margin-bottom:16px}
.asc-value{font-size:2.2rem;font-weight:800;color:var(--text-dark);letter-spacing:-.02em;line-height:1;margin-bottom:6px}
.asc-label{font-size:.88rem;font-weight:600;color:var(--text-muted);margin-bottom:14px}
.asc-bar{height:4px;background:var(--border-soft);border-radius:2px;overflow:hidden;position:relative}
.asc-bar::after{content:'';position:absolute;left:0;top:0;bottom:0;width:var(--fill);background:var(--accent);border-radius:2px;transition:width 1.2s cubic-bezier(.22,1,.36,1)}

/* ── TABLES ── */
.admin-table-wrap{background:white;border:1px solid var(--border-card);border-radius:var(--radius-xl);box-shadow:var(--shadow-sm);overflow-x:auto}
.admin-table{width:100%;border-collapse:collapse;font-size:.9rem}
.admin-table th{text-align:left;padding:14px 18px;background:var(--bg-cream);color:var(--text-muted);font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}
.admin-table th:first-child{border-radius:16px 0 0 0}
.admin-table th:last-child{border-radius:0 16px 0 0}
.admin-table td{padding:14px 18px;border-bottom:1px solid var(--border-soft);color:var(--text-dark);font-weight:500;vertical-align:middle}
.admin-table tr:last-child td{border-bottom:none}
.admin-table tr:hover td{background:#faf9ff}
.td-id{font-weight:800;color:var(--primary);font-size:.85rem}
.td-user{display:flex;align-items:center;gap:10px;font-weight:600}
.avatar-mini{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#a89fe8,#6d5fcc);color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.78rem;flex-shrink:0}

/* ── TABS ── */
.status-tabs{display:flex;gap:10px;flex-wrap:wrap}
.status-tab{display:inline-flex;align-items:center;gap:10px;padding:12px 22px;background:white;border:1px solid var(--border-soft);border-radius:var(--radius-pill);font-size:.95rem;font-weight:700;color:var(--text-muted);text-decoration:none;transition:all .25s var(--ease);box-shadow:var(--shadow-sm)}
.status-tab:hover{color:var(--text-dark);border-color:var(--primary-light)}
.status-tab.active{background:var(--primary);color:white;border-color:var(--primary);box-shadow:0 10px 24px rgba(109,95,204,.35)}
.tab-count{background:rgba(255,255,255,.25);padding:2px 10px;border-radius:var(--radius-pill);font-size:.78rem}
.status-tab:not(.active) .tab-count{background:var(--primary-light);color:var(--primary)}

/* ── EMPTY ── */
.empty-card{background:white;border:2px dashed var(--border-soft);border-radius:var(--radius-lg);padding:60px 40px;text-align:center}
.empty-icon{font-size:3rem;margin-bottom:14px}
.empty-card h3{font-size:1.3rem;font-weight:700;color:var(--text-dark);margin-bottom:6px}

/* ── MODAL ── */
.bk-modal-overlay{position:fixed;inset:0;background:rgba(30,27,75,.5);backdrop-filter:blur(6px);z-index:1000;display:none;align-items:center;justify-content:center;padding:20px}
.bk-modal-overlay.open{display:flex}
.bk-modal{background:white;border-radius:var(--radius-xl);max-width:560px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-xl);position:relative;animation:modalIn .35s var(--ease)}
.bk-modal-sm{max-width:420px}
@keyframes modalIn{from{opacity:0;transform:scale(.95) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}
.bk-modal-close{position:absolute;top:14px;right:14px;z-index:10;width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.9);border:1px solid var(--border-soft);display:flex;align-items:center;justify-content:center;font-size:1rem;color:var(--text-dark);cursor:pointer;transition:all .2s}
.bk-modal-close:hover{background:var(--danger-bg);color:var(--danger)}
.badge-danger{background:var(--danger-bg);color:#991b1b}

/* ── Image Management ── */
.ab-img-cell{position:relative;width:56px;height:56px;flex-shrink:0}
.ab-thumb{width:56px;height:56px;border-radius:10px;object-fit:cover;border:2px solid var(--border-soft)}
.ab-thumb-placeholder{width:56px;height:56px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-size:1.2rem;opacity:.6}
.ab-img-upload-btn{position:absolute;bottom:-4px;right:-4px;width:24px;height:24px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-size:.6rem;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.2);transition:all .2s}
.ab-img-upload-btn:hover{transform:scale(1.15);background:#5b4dc0}

.ab-room-img-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px}
.ab-room-img-card{background:white;border:1px solid var(--border-card);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-sm)}
.ab-room-img-preview{height:180px;position:relative;overflow:hidden;background:var(--bg-cream)}
.ab-room-img-preview img{width:100%;height:100%;object-fit:cover;transition:transform .3s}
.ab-room-img-card:hover .ab-room-img-preview img{transform:scale(1.05)}
.ab-room-img-empty{height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:var(--text-muted);font-size:.85rem}
.ab-room-img-empty i{font-size:2rem;opacity:.3}
.ab-room-img-info{padding:16px}
.ab-room-img-info h4{font-size:.95rem;font-weight:800;color:var(--text-dark);margin-bottom:8px}
.ab-room-upload-label{cursor:pointer;display:inline-flex !important}
.ab-room-img-status{display:block;margin-top:6px;font-size:.78rem;font-weight:600}

.ab-book-img-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px}
.ab-book-img-card{background:white;border:1px solid var(--border-card);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-sm)}
.ab-book-img-preview{height:160px;position:relative;overflow:hidden;background:var(--bg-cream)}
.ab-book-img-preview img{width:100%;height:100%;object-fit:cover}

@media(max-width:1080px){
    .admin-hero{grid-template-columns:1fr;padding:40px 32px}
    .admin-hero-visual{display:none}
    .admin-stats-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:640px){
    .admin-hero{padding:28px 20px}
    .admin-hero-content h1{font-size:2rem}
    .admin-stats-grid{grid-template-columns:1fr 1fr}
}
/* ── CUSTOM FORM UI ── */
.select-wrapper { position: relative; }
.select-icon { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
select.form-control { appearance: none; padding-right: 40px; cursor: pointer; }

.color-picker-wrap { display: flex; flex-direction: column; gap: 8px; }
.color-input { -webkit-appearance: none; border: 2px solid var(--border-soft); width: 100%; height: 56px; border-radius: 12px; cursor: pointer; padding: 0; overflow: hidden; background: white; transition: all .2s; }
.color-input:hover { border-color: var(--primary); }
.color-input::-webkit-color-swatch-wrapper { padding: 4px; }
.color-input::-webkit-color-swatch { border: none; border-radius: 8px; }
.color-hint { font-size: .8rem; color: var(--text-muted); line-height: 1.4; }

.file-upload-zone { display: block; border: 2px dashed var(--border-soft); border-radius: 12px; padding: 32px 20px; text-align: center; cursor: pointer; transition: all .2s; background: #fdfcff; }
.file-upload-zone:hover { border-color: var(--primary); background: color-mix(in srgb, var(--primary) 4%, white); transform: translateY(-2px); }
.file-input-hidden { display: none; }
.upload-icon { font-size: 2.2rem; color: var(--primary); margin-bottom: 12px; opacity: .8; transition: transform .3s; }
.file-upload-zone:hover .upload-icon { transform: scale(1.1) translateY(-4px); opacity: 1; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── APPROVE ──
    document.querySelectorAll('.ab-approve-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('approveResId').value = btn.dataset.id;
            document.getElementById('approveBookTitle').textContent = 'Buku: "' + btn.dataset.title + '"';
            document.getElementById('approveOverlay').classList.add('open');
        });
    });

    document.getElementById('confirmApproveBtn').addEventListener('click', async function() {
        const btn = this;
        const resId = document.getElementById('approveResId').value;
        const dueDate = document.getElementById('approveDueDate').value;
        const note = document.getElementById('approveNote').value;
        if (!dueDate) { alert('Sila pilih tarikh pulang.'); return; }
        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        const fd = new FormData();
        fd.append('action','approve_reservation'); fd.append('reservation_id',resId);
        fd.append('due_date',dueDate); fd.append('admin_note',note);
        try {
            const r = await fetch('book_api.php',{method:'POST',body:fd});
            const d = await r.json();
            if (d.success) { location.reload(); } else { alert(d.error); btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-check"></i> Sahkan'; }
        } catch(e) { alert('Ralat'); btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-check"></i> Sahkan'; }
    });

    // ── REJECT ──
    document.querySelectorAll('.ab-reject-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('rejectResId').value = btn.dataset.id;
            document.getElementById('rejectBookTitle').textContent = 'Buku: "' + btn.dataset.title + '"';
            document.getElementById('rejectNote').value = '';
            document.getElementById('rejectOverlay').classList.add('open');
        });
    });

    document.getElementById('confirmRejectBtn').addEventListener('click', async function() {
        const btn = this;
        const resId = document.getElementById('rejectResId').value;
        const note = document.getElementById('rejectNote').value;
        
        btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        const fd = new FormData();
        fd.append('action','reject_reservation'); fd.append('reservation_id',resId); fd.append('admin_note',note);
        try {
            const r = await fetch('book_api.php',{method:'POST',body:fd});
            const d = await r.json();
            if (d.success) location.reload(); else { alert(d.error); btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-xmark"></i> Sahkan Penolakan'; }
        } catch(e) { alert('Ralat'); btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-xmark"></i> Sahkan Penolakan'; }
    });

    // ── RETURN ──
    document.querySelectorAll('.ab-return-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Tandakan buku ini sebagai dipulangkan?')) return;
            btn.disabled = true;
            const fd = new FormData();
            fd.append('action','return_book'); fd.append('reservation_id',btn.dataset.id);
            try {
                const r = await fetch('book_api.php',{method:'POST',body:fd});
                const d = await r.json();
                if (d.success) location.reload(); else alert(d.error);
            } catch(e) { alert('Ralat'); btn.disabled=false; }
        });
    });

    // ── UPDATE BOOK STATUS ──
    document.querySelectorAll('.ab-status-select').forEach(sel => {
        sel.addEventListener('change', async function() {
            const fd = new FormData();
            fd.append('action','update_book_status'); fd.append('book_id',this.dataset.id); fd.append('new_status',this.value);
            try {
                const r = await fetch('book_api.php',{method:'POST',body:fd});
                const d = await r.json();
                if (d.success) {
                    this.style.boxShadow='0 0 0 3px rgba(16,185,129,.3)';
                    setTimeout(()=>this.style.boxShadow='',1500);
                } else { alert(d.error); }
            } catch(e) { alert('Ralat'); }
        });
    });

    // ── DELETE BOOK ──
    document.querySelectorAll('.ab-delete-book').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Padam buku "' + btn.dataset.title + '"?')) return;
            btn.disabled = true;
            const fd = new FormData();
            fd.append('action','delete_book'); fd.append('book_id',btn.dataset.id);
            try {
                const r = await fetch('book_api.php',{method:'POST',body:fd});
                const d = await r.json();
                if (d.success) {
                    const row = document.getElementById('book-row-'+btn.dataset.id);
                    if (row) { row.style.transition='all .4s'; row.style.opacity='0'; setTimeout(()=>row.remove(),400); }
                } else { alert(d.error); btn.disabled=false; }
            } catch(e) { alert('Ralat'); btn.disabled=false; }
        });
    });

    // ── ADD BOOK ──
    const addForm = document.getElementById('addBookForm');
    if (addForm) {
        addForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('action','add_book');
            const btn = this.querySelector('button[type=submit]');
            btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menambah...';
            try {
                const r = await fetch('book_api.php',{method:'POST',body:fd});
                const d = await r.json();
                const result = document.getElementById('addBookResult');
                if (d.success) {
                    result.innerHTML = '<div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> '+d.message+'</div>';
                    addForm.reset();
                } else {
                    result.innerHTML = '<div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> '+(d.error||'Ralat')+'</div>';
                }
                btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-plus"></i> Tambah Buku';
            } catch(e) { btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-plus"></i> Tambah Buku'; }
        });
    }

    // ── UPLOAD ROOM IMAGE ──
    document.querySelectorAll('.ab-room-img-input').forEach(input => {
        input.addEventListener('change', async function() {
            const file = this.files[0];
            if (!file) return;
            const roomName = this.dataset.room;
            const hash = this.dataset.hash;
            const statusEl = document.getElementById('status-' + hash);
            if (statusEl) { statusEl.textContent = '⏳ Memuat naik...'; statusEl.style.color = 'var(--primary)'; }

            const fd = new FormData();
            fd.append('action', 'upload_room_image');
            fd.append('room_name', roomName);
            fd.append('image', file);
            try {
                const r = await fetch('image_api.php', {method:'POST', body:fd});
                const d = await r.json();
                if (d.success) {
                    if (statusEl) { statusEl.textContent = '✅ Berjaya!'; statusEl.style.color = '#10b981'; }
                    // Update preview
                    const previewEl = document.getElementById('preview-' + hash);
                    if (previewEl) {
                        const container = previewEl.parentElement;
                        container.innerHTML = '<img src="' + d.path + '?t=' + Date.now() + '" alt="" id="preview-' + hash + '">';
                    }
                    setTimeout(() => { if(statusEl) statusEl.textContent = ''; }, 3000);
                } else {
                    if (statusEl) { statusEl.textContent = '❌ ' + d.error; statusEl.style.color = '#ef4444'; }
                }
            } catch(e) {
                if (statusEl) { statusEl.textContent = '❌ Ralat rangkaian'; statusEl.style.color = '#ef4444'; }
            }
        });
    });

    // ── UPLOAD BOOK IMAGE (from table and images tab) ──
    document.querySelectorAll('.ab-book-img-input').forEach(input => {
        input.addEventListener('change', async function() {
            const file = this.files[0];
            if (!file) return;
            const bookId = this.dataset.id;

            const fd = new FormData();
            fd.append('action', 'upload_book_image');
            fd.append('book_id', bookId);
            fd.append('image', file);
            try {
                const r = await fetch('image_api.php', {method:'POST', body:fd});
                const d = await r.json();
                if (d.success) {
                    // Update any thumbnail in table
                    const cell = this.closest('.ab-img-cell');
                    if (cell) {
                        const oldThumb = cell.querySelector('.ab-thumb, .ab-thumb-placeholder');
                        if (oldThumb) {
                            const img = document.createElement('img');
                            img.src = d.path + '?t=' + Date.now();
                            img.className = 'ab-thumb';
                            oldThumb.replaceWith(img);
                        }
                    }
                    // Update preview in images tab
                    const bkPreview = document.getElementById('bkpreview-' + bookId);
                    if (bkPreview) {
                        const container = bkPreview.parentElement;
                        container.innerHTML = '<img src="' + d.path + '?t=' + Date.now() + '" alt="" id="bkpreview-' + bookId + '">';
                    }
                    // Flash success
                    const label = this.closest('label');
                    if (label) {
                        label.style.boxShadow = '0 0 0 3px rgba(16,185,129,.4)';
                        setTimeout(() => label.style.boxShadow = '', 2000);
                    }
                } else {
                    alert(d.error || 'Gagal muat naik gambar');
                }
            } catch(e) {
                alert('Ralat rangkaian');
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>
