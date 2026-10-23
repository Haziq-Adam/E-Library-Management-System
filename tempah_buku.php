<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE)
    session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch all books
$books = $conn->query('SELECT * FROM books ORDER BY title ASC')->fetch_all(MYSQLI_ASSOC);

// Fetch categories
$cats = $conn->query('SELECT DISTINCT category FROM books ORDER BY category ASC')->fetch_all(MYSQLI_ASSOC);
$categories = array_column($cats, 'category');

// Fetch user's active reservations
$stmt = $conn->prepare('SELECT book_id FROM book_reservations WHERE user_id = ? AND status IN ("pending","approved")');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$my_res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$my_reserved_books = array_column($my_res, 'book_id');

$page_title = 'Tempahan Buku';
include 'header.php';
?>

<main class="container" style="padding-top:20px;">

    <!-- ═══ HERO BANNER ═══ -->
    <section class="bk-hero fade-in">


        <div class="bk-hero-content" style="position: relative; z-index: 10;">
            <div class="bk-hero-badge"><i class="fa-solid fa-book-open-reader"></i> Katalog Perpustakaan</div>
            <h1><span class="bk-hero-sub">Tempah</span><br><span class="bk-hero-main"><em>Buku</em> Perpustakaan.</span>
            </h1>
            <p>Cari dan tempah buku dari koleksi e-Library Kolej ATX. Permohonan perlu diluluskan oleh admin sebelum
                anda boleh mengambil buku.</p>
            <div class="bk-hero-chips">
                <span class="bk-chip"><i class="fa-solid fa-circle-check"></i>
                    <?= count(array_filter($books, fn($b) => $b['status'] === 'ada')) ?> Tersedia</span>
                <span class="bk-chip bk-chip-warn"><i class="fa-solid fa-clock"></i>
                    <?= count(array_filter($books, fn($b) => $b['status'] === 'sedang_ditempah')) ?> Ditempah</span>
                <span class="bk-chip bk-chip-danger"><i class="fa-solid fa-lock"></i>
                    <?= count(array_filter($books, fn($b) => $b['status'] === 'tidak_tersedia')) ?> Tidak Tersedia</span>
            </div>
        </div>
        <div class="bk-hero-visual" style="position: relative; z-index: 5;">
            <div class="spline-container">
                <script type="module" src="https://unpkg.com/@splinetool/viewer@1.9.71/build/spline-viewer.js"></script>
                <spline-viewer url="https://prod.spline.design/VD5lwBvo-m5v5-g0/scene.splinecode"
                    background="transparent" events-target="global"></spline-viewer>
            </div>
            <div class="bk-hero-orb orb-a"></div>
            <div class="bk-hero-orb orb-b"></div>
            <div class="bk-hero-orb orb-c"></div>
        </div>
    </section>

    <!-- Search & Filter -->
    <div class="bk-toolbar fade-in">
        <div class="bk-search-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="bookSearch" class="form-control" placeholder="Cari tajuk, penulis, atau ISBN...">
        </div>
        <div class="bk-filter-wrap">
            <select id="catFilter" class="form-control">
                <option value="">Semua Kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bk-stats-pill">
            <i class="fa-solid fa-book"></i>
            <span id="bookCount"><?= count($books) ?></span> buku
        </div>
    </div>

    <!-- Book Grid -->
    <div class="bk-grid" id="bookGrid">
        <?php foreach ($books as $book):
            $is_reserved = in_array($book['id'], $my_reserved_books);
            $status_class = ['ada' => 'st-available', 'sedang_ditempah' => 'st-reserved', 'tidak_tersedia' => 'st-unavailable'][$book['status']];
            $status_label = ['ada' => 'Tersedia', 'sedang_ditempah' => 'Sedang Ditempah', 'tidak_tersedia' => 'Tidak Tersedia'][$book['status']];
            $status_icon = ['ada' => 'fa-circle-check', 'sedang_ditempah' => 'fa-clock', 'tidak_tersedia' => 'fa-circle-xmark'][$book['status']];
            ?>
            <div class="bk-card scroll-reveal lift-on-hover"
                data-title="<?= htmlspecialchars(strtolower($book['title'])) ?>"
                data-author="<?= htmlspecialchars(strtolower($book['author'])) ?>"
                data-isbn="<?= htmlspecialchars(strtolower($book['isbn'])) ?>"
                data-category="<?= htmlspecialchars($book['category']) ?>">

                <div class="bk-cover"
                    style="background: linear-gradient(135deg, <?= $book['cover_color'] ?>30, <?= $book['cover_color'] ?>10);">
                    <div class="bk-cover-spine" style="background: <?= $book['cover_color'] ?>;"></div>
                    <?php if (!empty($book['cover_image'])): ?>
                        <img src="<?= htmlspecialchars($book['cover_image']) ?>" alt="<?= htmlspecialchars($book['title']) ?>"
                            class="bk-cover-img" loading="lazy">
                    <?php else: ?>
                        <div class="bk-cover-icon" style="color: <?= $book['cover_color'] ?>;">
                            <i class="fa-solid fa-book-open"></i>
                        </div>
                    <?php endif; ?>
                    <div class="bk-status-badge <?= $status_class ?>">
                        <i class="fa-solid <?= $status_icon ?>"></i> <?= $status_label ?>
                    </div>
                </div>

                <div class="bk-info">
                    <span class="bk-category"><?= htmlspecialchars($book['category']) ?></span>
                    <h3 class="bk-title"><?= htmlspecialchars($book['title']) ?></h3>
                    <p class="bk-author"><i class="fa-solid fa-user-pen"></i> <?= htmlspecialchars($book['author']) ?></p>

                    <div class="bk-details">
                        <span><i class="fa-solid fa-building-columns"></i>
                            <?= htmlspecialchars($book['publisher']) ?></span>
                        <span><i class="fa-solid fa-calendar"></i> <?= $book['year_published'] ?></span>
                        <span><i class="fa-solid fa-barcode"></i> <?= htmlspecialchars($book['isbn']) ?></span>
                        <span><i class="fa-solid fa-copy"></i> <?= $book['total_copies'] ?> naskah</span>
                    </div>

                    <p class="bk-desc"><?= htmlspecialchars(mb_strimwidth($book['description'], 0, 120, '...')) ?></p>

                    <div class="bk-actions">
                        <?php if ($is_reserved): ?>
                            <button class="btn btn-ghost btn-sm" disabled>
                                <i class="fa-solid fa-check"></i> Sudah Ditempah
                            </button>
                        <?php elseif ($book['status'] === 'ada'): ?>
                            <button class="btn btn-primary btn-sm bk-reserve-btn" data-id="<?= $book['id'] ?>"
                                data-title="<?= htmlspecialchars($book['title']) ?>">
                                <i class="fa-solid fa-hand"></i> Mohon Tempah
                            </button>
                        <?php else: ?>
                            <button class="btn btn-ghost btn-sm" disabled style="opacity:.5;">
                                <i class="fa-solid fa-lock"></i> Tidak Tersedia
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-ghost btn-sm bk-detail-btn" data-id="<?= $book['id'] ?>"
                            data-title="<?= htmlspecialchars($book['title']) ?>"
                            data-author="<?= htmlspecialchars($book['author']) ?>"
                            data-publisher="<?= htmlspecialchars($book['publisher']) ?>"
                            data-year="<?= $book['year_published'] ?>" data-isbn="<?= htmlspecialchars($book['isbn']) ?>"
                            data-category="<?= htmlspecialchars($book['category']) ?>"
                            data-desc="<?= htmlspecialchars($book['description']) ?>"
                            data-copies="<?= $book['total_copies'] ?>" data-status="<?= $book['status'] ?>"
                            data-color="<?= $book['cover_color'] ?>">
                            <i class="fa-solid fa-eye"></i> Info
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Empty state -->
    <div class="bk-empty" id="bookEmpty" style="display:none;">
        <div class="empty-icon">🔍</div>
        <h3>Tiada buku ditemui</h3>
        <p>Cuba tukar kata kunci carian atau penapis kategori.</p>
    </div>

    <!-- Detail Modal -->
    <div class="bk-modal-overlay" id="detailOverlay">
        <div class="bk-modal">
            <button class="bk-modal-close" id="detailClose"><i class="fa-solid fa-xmark"></i></button>
            <div class="bk-modal-cover" id="modalCover">
                <div class="bk-modal-cover-icon"><i class="fa-solid fa-book-open"></i></div>
            </div>
            <div class="bk-modal-body">
                <span class="bk-category" id="modalCat"></span>
                <h2 id="modalTitle"></h2>
                <div class="bk-modal-meta">
                    <span><i class="fa-solid fa-user-pen"></i> <span id="modalAuthor"></span></span>
                    <span><i class="fa-solid fa-building-columns"></i> <span id="modalPublisher"></span></span>
                    <span><i class="fa-solid fa-calendar"></i> <span id="modalYear"></span></span>
                    <span><i class="fa-solid fa-barcode"></i> <span id="modalIsbn"></span></span>
                    <span><i class="fa-solid fa-copy"></i> <span id="modalCopies"></span> naskah</span>
                </div>
                <div class="bk-modal-status" id="modalStatusWrap">
                    <span id="modalStatus"></span>
                </div>
                <h4>Sinopsis</h4>
                <p id="modalDesc"></p>
            </div>
        </div>
    </div>

    <!-- Confirm Reserve Modal -->
    <div class="bk-modal-overlay" id="reserveOverlay">
        <div class="bk-modal bk-modal-sm">
            <button class="bk-modal-close" id="reserveClose"><i class="fa-solid fa-xmark"></i></button>
            <div class="bk-modal-body" style="text-align:center;padding:40px 30px;">
                <div style="font-size:3rem;margin-bottom:16px;">📚</div>
                <h2 style="font-size:1.4rem;margin-bottom:8px;">Mohon Tempah Buku</h2>
                <p id="reserveBookTitle" style="color:var(--text-muted);margin-bottom:24px;"></p>
                <p style="font-size:.88rem;color:var(--text-muted);margin-bottom:24px;line-height:1.6;">
                    Permohonan anda akan dihantar kepada admin untuk kelulusan. Anda akan menerima kod tempahan unik
                    selepas diluluskan.
                </p>
                <input type="hidden" id="reserveBookId" value="">
                <button class="btn btn-primary btn-lg btn-block" id="confirmReserveBtn">
                    <i class="fa-solid fa-paper-plane"></i> Hantar Permohonan
                </button>
                <div id="reserveResult" style="margin-top:16px;display:none;"></div>
            </div>
        </div>
    </div>

</main>

<style>
    /* ═══ HERO ═══ */
    .bk-hero {
        background: linear-gradient(135deg, #1e1b4b 0%, #312e6b 40%, #4338ca 100%);
        border-radius: var(--radius-xl);
        padding: 52px 56px;
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 36px;
        align-items: center;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(30, 27, 75, .5);
        margin-bottom: 36px
    }

    .bk-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: var(--radius-xl);
        overflow: hidden;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        pointer-events: none
    }

    .bk-hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, .1);
        backdrop-filter: blur(10px);
        padding: 8px 18px;
        border-radius: var(--radius-pill);
        font-size: .82rem;
        font-weight: 700;
        color: #c4bbf0;
        margin-bottom: 18px;
        border: 1px solid rgba(255, 255, 255, .1)
    }

    .bk-hero h1 {
        font-size: 2.6rem;
        font-weight: 800;
        line-height: 1.08;
        color: white;
        margin-bottom: 16px;
        letter-spacing: -.025em
    }

    .bk-hero-sub {
        color: #a89fe8
    }

    .bk-hero-main {
        color: #e0e7ff
    }

    .bk-hero-main em {
        color: #ffc48a;
        font-style: italic
    }

    .bk-hero-content>p {
        font-size: 1rem;
        color: #a5b4fc;
        line-height: 1.6;
        max-width: 460px
    }

    .bk-hero-chips {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        flex-wrap: wrap
    }

    .bk-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: var(--radius-pill);
        font-size: .78rem;
        font-weight: 700;
        background: rgba(16, 185, 129, .15);
        color: #6ee7b7;
        border: 1px solid rgba(16, 185, 129, .2)
    }

    .bk-chip-warn {
        background: rgba(245, 158, 11, .15);
        color: #fcd34d;
        border-color: rgba(245, 158, 11, .2)
    }

    .bk-chip-danger {
        background: rgba(239, 68, 68, .15);
        color: #fca5a5;
        border-color: rgba(239, 68, 68, .2)
    }

    /* ═══ HERO VISUAL & SPLINE ═══ */
    .bk-hero-visual {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 300px;
        overflow: visible
    }

    .spline-container {
        position: absolute;
        top: -50px;
        right: 80px;
        width: 600px;
        height: 600px;
        z-index: 2;
        pointer-events: auto;
        transform: scale(1.4);
        transform-origin: center center
    }

    .spline-container spline-viewer {
        width: 100%;
        height: 100%;
        background: transparent !important;
        pointer-events: auto
    }

    spline-viewer::part(logo) {
        display: none !important
    }

    .bk-hero-orb {
        position: absolute;
        border-radius: 50%;
        filter: blur(40px);
        opacity: .4;
        pointer-events: none
    }

    .orb-a {
        width: 180px;
        height: 180px;
        background: #6d5fcc;
        top: -30px;
        right: -20px;
        animation: orbDrift 7s ease-in-out infinite
    }

    .orb-b {
        width: 100px;
        height: 100px;
        background: #f4a4b8;
        bottom: 0;
        left: 20px;
        animation: orbDrift 5s ease-in-out infinite reverse
    }

    .orb-c {
        width: 70px;
        height: 70px;
        background: #ffc48a;
        top: 30px;
        left: 50px;
        animation: orbDrift 9s ease-in-out infinite
    }

    @keyframes orbDrift {

        0%,
        100% {
            transform: translate(0, 0) scale(1)
        }

        50% {
            transform: translate(10px, -15px) scale(1.1)
        }
    }

    /* ═══ Toolbar ═══ */
    .bk-toolbar {
        display: flex;
        gap: 14px;
        margin-bottom: 28px;
        flex-wrap: wrap;
        align-items: center;
        position: relative;
        z-index: 10
    }

    .bk-search-wrap {
        flex: 1;
        min-width: 240px;
        position: relative
    }

    .bk-search-wrap i {
        position: absolute;
        top: 50%;
        left: 16px;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: .95rem;
        pointer-events: none
    }

    .bk-search-wrap .form-control {
        padding-left: 44px
    }

    .bk-filter-wrap {
        min-width: 180px
    }

    .bk-stats-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        background: white;
        border: 1px solid var(--border-card);
        border-radius: var(--radius-pill);
        font-size: .88rem;
        font-weight: 700;
        color: var(--primary);
        box-shadow: var(--shadow-sm)
    }

    /* ═══ Book Grid ═══ */
    .bk-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 22px;
        margin-bottom: 60px;
        position: relative;
        z-index: 10
    }

    .bk-card {
        background: white;
        border: 1px solid var(--border-card);
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        transition: all .35s var(--ease);
        display: flex;
        flex-direction: column
    }

    .bk-card:hover {
        box-shadow: 0 20px 50px rgba(109, 95, 204, .18);
        transform: translateY(-6px)
    }

    .bk-cover {
        height: 180px;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden
    }

    .bk-cover-spine {
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 6px;
        z-index: 2
    }

    .bk-cover-img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform .4s var(--ease)
    }

    .bk-card:hover .bk-cover-img {
        transform: scale(1.08)
    }

    .bk-cover-icon {
        font-size: 2.8rem;
        opacity: .25
    }

    .bk-status-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: var(--radius-pill);
        font-size: .75rem;
        font-weight: 800;
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, .3);
        box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
        z-index: 5;
        text-transform: capitalize;
        color: white !important
    }

    .st-available {
        background: rgba(16, 185, 129, .95) !important
    }

    .st-reserved {
        background: rgba(245, 158, 11, .95) !important
    }

    .st-unavailable {
        background: rgba(239, 68, 68, .95) !important
    }

    .bk-status-badge i {
        font-size: .65rem;
        filter: drop-shadow(0 1px 2px rgba(0, 0, 0, .2))
    }

    .bk-info {
        padding: 18px;
        flex: 1;
        display: flex;
        flex-direction: column
    }

    .bk-category {
        display: inline-block;
        font-size: .7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .8px;
        color: var(--primary);
        background: rgba(109, 95, 204, .15);
        padding: 4px 12px;
        border-radius: var(--radius-pill);
        margin-bottom: 10px;
        width: fit-content;
        border: 1px solid rgba(109, 95, 204, .1)
    }

    .bk-title {
        font-size: 1.1rem;
        font-weight: 900;
        color: #1e1b4b;
        margin-bottom: 8px;
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        letter-spacing: -0.2px
    }

    .bk-author {
        font-size: .88rem;
        color: #475569;
        font-weight: 700;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 6px
    }

    .bk-details {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 16px;
        font-size: .78rem;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 12px
    }

    .bk-details span {
        display: flex;
        align-items: center;
        gap: 6px
    }

    .bk-details i {
        font-size: .75rem;
        color: var(--primary);
        opacity: .9
    }

    .bk-desc {
        font-size: .82rem;
        color: var(--text-muted);
        line-height: 1.5;
        margin-bottom: 14px;
        flex: 1;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden
    }

    .bk-actions {
        display: flex;
        gap: 8px;
        margin-top: auto;
        padding-top: 12px;
        border-top: 1px solid var(--border-soft)
    }

    /* ═══ Modal ═══ */
    .bk-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(30, 27, 75, .5);
        backdrop-filter: blur(6px);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px
    }

    .bk-modal-overlay.open {
        display: flex
    }

    .bk-modal {
        background: white;
        border-radius: var(--radius-xl);
        max-width: 560px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: var(--shadow-xl);
        position: relative;
        animation: modalIn .35s var(--ease)
    }

    .bk-modal-sm {
        max-width: 420px
    }

    @keyframes modalIn {
        from {
            opacity: 0;
            transform: scale(.95) translateY(10px)
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0)
        }
    }

    .bk-modal-close {
        position: absolute;
        top: 14px;
        right: 14px;
        z-index: 10;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .9);
        border: 1px solid var(--border-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        color: var(--text-dark);
        cursor: pointer;
        transition: all .2s
    }

    .bk-modal-close:hover {
        background: var(--danger-bg);
        color: var(--danger)
    }

    .bk-modal-cover {
        height: 160px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative
    }

    .bk-modal-cover-icon {
        font-size: 3.5rem;
        opacity: .3
    }

    .bk-modal-body {
        padding: 28px
    }

    .bk-modal-body h2 {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text-dark);
        margin-bottom: 14px;
        line-height: 1.25
    }

    .bk-modal-body h4 {
        font-size: .95rem;
        font-weight: 800;
        color: var(--text-dark);
        margin-bottom: 8px;
        margin-top: 18px
    }

    .bk-modal-body p {
        font-size: .92rem;
        color: var(--text-body);
        line-height: 1.65
    }

    .bk-modal-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 18px;
        font-size: .84rem;
        color: var(--text-muted);
        font-weight: 600;
        margin-bottom: 14px
    }

    .bk-modal-meta span {
        display: flex;
        align-items: center;
        gap: 6px
    }

    .bk-modal-meta i {
        color: var(--primary);
        opacity: .6
    }

    .bk-modal-status {
        margin-bottom: 10px
    }

    .bk-modal-status span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: var(--radius-pill);
        font-size: .8rem;
        font-weight: 700
    }

    /* ═══ Empty ═══ */
    .bk-empty {
        text-align: center;
        padding: 60px 20px
    }

    .bk-empty .empty-icon {
        font-size: 3rem;
        margin-bottom: 12px
    }

    .bk-empty h3 {
        font-size: 1.3rem;
        font-weight: 800;
        color: var(--text-dark);
        margin-bottom: 6px
    }

    .bk-empty p {
        color: var(--text-muted)
    }

    @media(max-width:900px) {
        .bk-hero {
            grid-template-columns: 1fr;
            padding: 36px 28px
        }

        .bk-hero-visual {
            display: none
        }
    }

    @media(max-width:640px) {
        .bk-grid {
            grid-template-columns: 1fr
        }

        .bk-toolbar {
            flex-direction: column
        }

        .bk-search-wrap,
        .bk-filter-wrap {
            width: 100%
        }

        .bk-hero {
            padding: 28px 20px
        }

        .bk-hero h1 {
            font-size: 2rem
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const grid = document.getElementById('bookGrid');
        const cards = grid.querySelectorAll('.bk-card');
        const searchInput = document.getElementById('bookSearch');
        const catFilter = document.getElementById('catFilter');
        const countEl = document.getElementById('bookCount');
        const emptyEl = document.getElementById('bookEmpty');

        // Search & Filter
        function filterBooks() {
            const q = searchInput.value.toLowerCase().trim();
            const cat = catFilter.value;
            let visible = 0;
            cards.forEach(card => {
                const title = card.dataset.title;
                const author = card.dataset.author;
                const isbn = card.dataset.isbn;
                const category = card.dataset.category;
                const matchSearch = !q || title.includes(q) || author.includes(q) || isbn.includes(q);
                const matchCat = !cat || category === cat;
                const show = matchSearch && matchCat;
                card.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            countEl.textContent = visible;
            emptyEl.style.display = visible === 0 ? 'block' : 'none';
            grid.style.display = visible === 0 ? 'none' : '';
        }
        searchInput.addEventListener('input', filterBooks);
        catFilter.addEventListener('change', filterBooks);

        // Detail Modal
        const detailOverlay = document.getElementById('detailOverlay');
        document.querySelectorAll('.bk-detail-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('modalTitle').textContent = btn.dataset.title;
                document.getElementById('modalAuthor').textContent = btn.dataset.author;
                document.getElementById('modalPublisher').textContent = btn.dataset.publisher;
                document.getElementById('modalYear').textContent = btn.dataset.year;
                document.getElementById('modalIsbn').textContent = btn.dataset.isbn;
                document.getElementById('modalCopies').textContent = btn.dataset.copies;
                document.getElementById('modalCat').textContent = btn.dataset.category;
                document.getElementById('modalDesc').textContent = btn.dataset.desc;
                const cover = document.getElementById('modalCover');
                cover.style.background = 'linear-gradient(135deg, ' + btn.dataset.color + '30, ' + btn.dataset.color + '10)';
                const statusMap = { ada: { label: 'Tersedia', cls: 'st-available', icon: 'fa-circle-check' }, sedang_ditempah: { label: 'Sedang Ditempah', cls: 'st-reserved', icon: 'fa-clock' }, tidak_tersedia: { label: 'Tidak Tersedia', cls: 'st-unavailable', icon: 'fa-circle-xmark' } };
                const st = statusMap[btn.dataset.status];
                document.getElementById('modalStatus').className = st.cls;
                document.getElementById('modalStatus').innerHTML = '<i class="fa-solid ' + st.icon + '"></i> ' + st.label;
                document.getElementById('modalStatusWrap').querySelector('span').className = 'bk-status-badge ' + st.cls;
                detailOverlay.classList.add('open');
            });
        });
        document.getElementById('detailClose').addEventListener('click', () => detailOverlay.classList.remove('open'));
        detailOverlay.addEventListener('click', e => { if (e.target === detailOverlay) detailOverlay.classList.remove('open'); });

        // Reserve Modal
        const reserveOverlay = document.getElementById('reserveOverlay');
        document.querySelectorAll('.bk-reserve-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('reserveBookId').value = btn.dataset.id;
                document.getElementById('reserveBookTitle').textContent = '"' + btn.dataset.title + '"';
                document.getElementById('reserveResult').style.display = 'none';
                document.getElementById('confirmReserveBtn').disabled = false;
                document.getElementById('confirmReserveBtn').style.display = '';
                reserveOverlay.classList.add('open');
            });
        });
        document.getElementById('reserveClose').addEventListener('click', () => reserveOverlay.classList.remove('open'));
        reserveOverlay.addEventListener('click', e => { if (e.target === reserveOverlay) reserveOverlay.classList.remove('open'); });

        // Confirm Reserve
        document.getElementById('confirmReserveBtn').addEventListener('click', async function () {
            const btn = this;
            const bookId = document.getElementById('reserveBookId').value;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menghantar...';

            try {
                const fd = new FormData();
                fd.append('action', 'reserve_book');
                fd.append('book_id', bookId);
                const r = await fetch('book_api.php', { method: 'POST', body: fd });
                const d = await r.json();
                const result = document.getElementById('reserveResult');
                result.style.display = 'block';
                if (d.success) {
                    result.innerHTML = '<div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> ' + d.message + '<br><strong>Kod: ' + d.code + '</strong></div>';
                    btn.style.display = 'none';
                    setTimeout(() => location.reload(), 2000);
                } else {
                    result.innerHTML = '<div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> ' + (d.error || 'Ralat') + '</div>';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Hantar Permohonan';
                }
            } catch (e) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Hantar Permohonan';
            }
        });
    });
</script>



<?php include 'footer.php'; ?>