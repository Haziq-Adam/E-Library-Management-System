<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id = $_SESSION['user_id'];

$available_rooms = [
    ['name'=>'Bilik Bacaan 1',  'icon'=>'📖','color'=>'#a89fe8','desc'=>'Zon senyap bertebat bunyi khas untuk pengajian individu berfokus.',   'capacity'=>'1–4 orang'],
    ['name'=>'Bilik Bacaan 2',  'icon'=>'📝','color'=>'#f4a4b8','desc'=>'Zon interaktif dengan paparan skrin untuk tugasan pembentangan.',    'capacity'=>'1–6 orang'],
    ['name'=>'Bilik Mesyuarat', 'icon'=>'🏢','color'=>'#8ed4c8','desc'=>'Bilik berkapasiti tinggi dengan meja persidangan 12 kerusi.',         'capacity'=>'6–12 orang'],
    ['name'=>'Ruang Seminar',   'icon'=>'🎓','color'=>'#ffc48a','desc'=>'Auditorium mini lengkap kemudahan AV untuk bengkel.',                'capacity'=>'10–30 orang'],
    ['name'=>'Makmal Komputer', 'icon'=>'💻','color'=>'#7fb3e8','desc'=>'Infrastruktur internet berkelajuan tinggi untuk tugasan teknikal.',   'capacity'=>'1–20 orang'],
    ['name'=>'Studio Rakaman',  'icon'=>'🎙️','color'=>'#ffb3d9','desc'=>'Ruang kalis bunyi khas untuk rakaman multimedia pelajar.',         'capacity'=>'1–4 orang'],
];

// Fetch room images from database
$room_images = [];
$ri_result = $conn->query('SELECT room_name, image_path FROM room_images');
if ($ri_result) {
    while ($ri = $ri_result->fetch_assoc()) {
        $room_images[$ri['room_name']] = $ri['image_path'];
    }
}

$operation_hours = [];
for ($i = 8; $i <= 22; $i++) $operation_hours[] = sprintf('%02d:00', $i);

$selected_date  = $_GET['preselect_date'] ?? date('Y-m-d');
$preselect_room = $_GET['preselect_room'] ?? $available_rooms[0]['name'];
$booking_message = '';
$booking_status  = '';

/* ── SERVER-SIDE BOOKING ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    $room_name = trim($_POST['room_name']    ?? '');
    $b_date    = trim($_POST['booking_date'] ?? '');
    $slots_csv = trim($_POST['slots']        ?? '');

    $req_slots = array_values(array_filter(array_map('trim', explode(',', $slots_csv))));

    if (!$room_name || !$req_slots) {
        $booking_message = 'Sila pilih bilik dan sekurang-kurangnya satu slot masa.';
        $booking_status  = 'error';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $b_date) || strtotime($b_date) < strtotime(date('Y-m-d'))) {
        $booking_message = 'Tarikh tidak sah atau sudah lepas.';
        $booking_status  = 'error';
    } else {
        // Validate all slots exist in operation_hours
        foreach ($req_slots as $s) {
            if (!in_array($s, $operation_hours, true)) {
                $booking_message = 'Slot tidak sah: ' . htmlspecialchars($s);
                $booking_status  = 'error'; break;
            }
        }
        if ($booking_status !== 'error') {
            // Validate consecutive
            $idxs = array_map(fn($s) => array_search($s, $operation_hours), $req_slots);
            sort($idxs);
            for ($i = 1; $i < count($idxs); $i++) {
                if ($idxs[$i] !== $idxs[$i-1]+1) {
                    $booking_message = 'Slot mesti berturut-turut.';
                    $booking_status  = 'error'; break;
                }
            }
        }
        if ($booking_status !== 'error' && $b_date === date('Y-m-d')) {
            $curH = (int)date('H');
            foreach ($req_slots as $s) {
                if ((int)explode(':',$s)[0] <= $curH) {
                    $booking_message = 'Slot jam ' . htmlspecialchars($s) . ' sudah lepas hari ini.';
                    $booking_status  = 'error'; break;
                }
            }
        }
        if ($booking_status !== 'error') {
            // Check conflicts
            foreach ($req_slots as $s) {
                $c = $conn->prepare('SELECT id FROM bookings WHERE room_name=? AND booking_date=? AND time_slot=? AND status="active"');
                $c->bind_param('sss', $room_name, $b_date, $s);
                $c->execute(); $c->store_result();
                if ($c->num_rows > 0) {
                    $booking_message = 'Konflik: Slot jam ' . htmlspecialchars($s) . ' sudah ditempah.';
                    $booking_status  = 'error'; $c->close(); break;
                }
                $c->close();
            }
        }
        if ($booking_status !== 'error') {
            $conn->begin_transaction();
            $ok = true;
            $ins = $conn->prepare('INSERT INTO bookings (user_id,room_name,booking_date,time_slot,status) VALUES (?,?,?,?,"active")');
            foreach ($req_slots as $s) {
                $ins->bind_param('isss', $user_id, $room_name, $b_date, $s);
                if (!$ins->execute()) { $ok = false; break; }
            }
            $ins->close();
            if ($ok) {
                $conn->commit();
                $sorted = $req_slots; sort($sorted);
                $until  = sprintf('%02d:00', (int)explode(':',end($sorted))[0]+1);
                $booking_message = '✅ Berjaya! ' . htmlspecialchars($room_name) . ' ditempah dari ' . reset($sorted) . ' hingga ' . $until . ' (' . count($sorted) . ' jam).';
                $booking_status  = 'success';
                $preselect_room  = $room_name;
                $selected_date   = $b_date;
            } else {
                $conn->rollback();
                $booking_message = 'Ralat pangkalan data. Sila cuba lagi.';
                $booking_status  = 'error';
            }
        }
    }
}

$page_title = 'Tempahan Bilik';
include 'header.php';
?>

<main class="container" style="padding-top:40px;">

<div class="page-head fade-in">
    <span class="eyebrow">📅 Tempahan</span>
    <h1>Pilih <em>Bilik</em> &amp; Tempah Slot Anda.</h1>
    <p>Pilih bilik, tetapkan tarikh, kemudian klik slot masa yang dikehendaki. Slot merah sudah ditempah &amp; tidak boleh dipilih.</p>
</div>

<?php if ($booking_message): ?>
<div class="alert alert-<?= $booking_status === 'success' ? 'success' : 'error' ?>">
    <i class="fa-solid <?= $booking_status === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
    <?= htmlspecialchars($booking_message) ?>
</div>
<?php endif; ?>

<div class="booking-layout">

    <!-- LEFT: Room Gallery -->
    <section class="room-gallery">
        <h2 class="gallery-title">🏛️ Pilih Bilik</h2>
        <div class="room-grid">
            <?php foreach ($available_rooms as $idx => $room):
                $active = ($preselect_room === $room['name']); ?>
            <div class="room-card <?= $active ? 'selected' : '' ?> scroll-reveal"
                 id="room-<?= $idx ?>"
                 onclick='selectRoom(<?= $idx ?>, <?= json_encode($room['name']) ?>)'>
                <div class="room-visual" style="background:linear-gradient(135deg,<?= $room['color'] ?>40,<?= $room['color'] ?>15);">
                    <?php if (!empty($room_images[$room['name']])): ?>
                        <img src="<?= htmlspecialchars($room_images[$room['name']]) ?>" alt="<?= htmlspecialchars($room['name']) ?>" class="room-img" loading="lazy">
                        <div class="room-img-overlay"></div>
                    <?php endif; ?>
                    <div class="room-emoji"><?= $room['icon'] ?></div>
                    <?php if ($active): ?>
                        <div class="room-check-badge"><i class="fa-solid fa-check"></i></div>
                    <?php endif; ?>
                </div>
                <div class="room-body">
                    <h3><?= htmlspecialchars($room['name']) ?></h3>
                    <p><?= htmlspecialchars($room['desc']) ?></p>
                    <div class="room-footer">
                        <span class="badge badge-success" style="font-size:.7rem;gap:4px;">
                            <i class="fa-solid fa-circle" style="font-size:5px"></i>Tersedia
                        </span>
                        <span class="room-cap">
                            <i class="fa-solid fa-users" style="font-size:.72rem"></i>
                            <?= $room['capacity'] ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- RIGHT: Booking Panel -->
    <aside class="booking-panel">
        <div class="booking-form-card">

            <div class="form-header">
                <div class="form-header-icon"><i class="fa-solid fa-calendar-plus"></i></div>
                <div>
                    <h3>Borang Tempahan</h3>
                    <p>Pilih tarikh &amp; klik slot masa</p>
                </div>
            </div>

            <!-- Selected room pill -->
            <div class="selected-room-pill" id="roomPill">
                <span id="pillIcon">📖</span>
                <div>
                    <strong id="pillName"><?= htmlspecialchars($preselect_room) ?></strong>
                    <small id="pillCap"><?= htmlspecialchars(array_values(array_filter($available_rooms, fn($r) => $r['name'] === $preselect_room))[0]['capacity'] ?? $available_rooms[0]['capacity']) ?></small>
                </div>
            </div>

            <!-- Date -->
            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label">
                    <i class="fa-regular fa-calendar" style="color:var(--primary);margin-right:5px;"></i>
                    Tarikh Penggunaan
                </label>
                <input type="date" id="bookingDate" class="form-control"
                       value="<?= htmlspecialchars($selected_date) ?>"
                       min="<?= date('Y-m-d') ?>">
            </div>

            <!-- Slot header -->
            <div class="slot-header">
                <label class="form-label" style="margin:0;">
                    <i class="fa-regular fa-clock" style="color:var(--primary);margin-right:5px;"></i>
                    Slot Masa
                </label>
                <span id="slotSpinner" style="display:none;">
                    <i class="fa-solid fa-spinner fa-spin" style="color:var(--primary);font-size:.9rem;"></i>
                </span>
            </div>

            <!-- Legend -->
            <div class="slot-legend">
                <span><span class="ld ld-free"></span>Tersedia</span>
                <span><span class="ld ld-taken"></span>Ditempah</span>
                <span><span class="ld ld-mine"></span>Milik Anda</span>
                <span><span class="ld ld-past"></span>Lepas</span>
                <span><span class="ld ld-sel"></span>Dipilih</span>
            </div>

            <!-- Slot grid -->
            <div class="slot-grid" id="slotGrid"></div>

            <!-- Non-consecutive warning -->
            <div class="ncw" id="ncw" style="display:none;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Slot mesti berturut-turut! Pilihan semula ditetapkan ke slot terakhir.
            </div>

            <!-- Live summary -->
            <div class="bk-summary" id="bkSummary" style="display:none;">
                <div class="bks-row"><i class="fa-regular fa-clock"></i><span id="bksTime">—</span></div>
                <div class="bks-row"><i class="fa-solid fa-hourglass-half"></i><span id="bksDur">—</span></div>
            </div>

            <!-- Form -->
            <form method="POST" action="booking.php" id="bookingForm">
                <input type="hidden" name="submit_booking" value="1">
                <input type="hidden" name="room_name"    id="fRoom"  value="<?= htmlspecialchars($preselect_room) ?>">
                <input type="hidden" name="booking_date" id="fDate"  value="<?= htmlspecialchars($selected_date) ?>">
                <input type="hidden" name="slots"        id="fSlots" value="">

                <button type="submit" id="submitBtn" class="btn btn-primary btn-block btn-lg"
                        disabled style="margin-top:16px;opacity:.45;cursor:not-allowed;">
                    <i class="fa-solid fa-check"></i> Sahkan Tempahan
                </button>
            </form>

            <div class="form-tip">
                <i class="fa-solid fa-lightbulb"></i>
                <span>Klik satu atau lebih slot <strong>berturut-turut</strong>, kemudian tekan Sahkan Tempahan.</span>
            </div>

        </div>
    </aside>
</div>
</main>

<style>
/* ─── Page head ─── */
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
.page-head h1{font-size:2.6rem;font-weight:800;color:var(--text-dark);line-height:1.1;margin-top:0 !important;margin-bottom:10px;letter-spacing:-.02em}
.page-head h1 em{font-style:italic;color:var(--primary)}
.page-head p{font-size:1.05rem;color:var(--text-muted);max-width:660px}

/* ─── Layout ─── */
.booking-layout{display:grid;grid-template-columns:1fr 400px;gap:28px;align-items:flex-start;margin-bottom:60px}

/* ─── Room cards ─── */
.gallery-title{font-size:1.25rem;font-weight:800;color:var(--text-dark);margin-bottom:16px}
.room-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
.room-card{background:white;border:2px solid var(--border-card);border-radius:var(--radius-lg);overflow:hidden;cursor:pointer;box-shadow:var(--shadow-sm);transition:all .28s var(--ease)}
.room-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-lg);border-color:var(--primary-light)}
.room-card.selected{border-color:var(--primary);box-shadow:0 14px 36px rgba(109,95,204,.2)}
.room-visual{height:140px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden}
.room-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:transform .45s var(--ease)}
.room-card:hover .room-img{transform:scale(1.08)}
.room-img-overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.05) 0%,rgba(0,0,0,.3) 100%);z-index:1}
.room-emoji{font-size:3rem;filter:drop-shadow(0 5px 10px rgba(0,0,0,.08));animation:bob 4s ease-in-out infinite;position:relative;z-index:2;text-shadow:0 2px 10px rgba(0,0,0,.15)}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
.room-check-badge{position:absolute;top:8px;right:8px;width:26px;height:26px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-size:.75rem;box-shadow:0 4px 10px rgba(109,95,204,.4);animation:popIn .3s var(--ease)}
@keyframes popIn{from{transform:scale(0)}to{transform:scale(1)}}
.room-body{padding:14px}
.room-body h3{font-size:.95rem;font-weight:800;color:var(--text-dark);margin-bottom:4px}
.room-body p{font-size:.8rem;color:var(--text-muted);line-height:1.45;margin-bottom:10px;min-height:46px}
.room-footer{display:flex;justify-content:space-between;align-items:center;padding-top:8px;border-top:1px solid var(--border-soft)}
.room-cap{font-size:.74rem;font-weight:600;color:var(--text-muted);display:flex;align-items:center;gap:4px}

/* ─── Booking panel ─── */
.booking-panel{position:sticky;top:110px}
.booking-form-card{background:white;border:1px solid var(--border-card);border-radius:var(--radius-xl);padding:26px;box-shadow:var(--shadow-lg)}
.form-header{display:flex;align-items:center;gap:13px;margin-bottom:18px;padding-bottom:16px;border-bottom:1px solid var(--border-soft)}
.form-header-icon{width:44px;height:44px;border-radius:13px;background:linear-gradient(135deg,#a89fe8,#6d5fcc);color:white;display:flex;align-items:center;justify-content:center;font-size:1.05rem;box-shadow:0 8px 18px rgba(109,95,204,.3)}
.form-header h3{font-size:1.1rem;font-weight:800;color:var(--text-dark);margin-bottom:2px}
.form-header p{font-size:.82rem;color:var(--text-muted)}

/* ─── Room pill ─── */
.selected-room-pill{display:flex;align-items:center;gap:12px;padding:11px 14px;background:var(--primary-soft);border:1.5px solid var(--primary-light);border-radius:var(--radius-sm);margin-bottom:16px;transition:all .25s var(--ease)}
.selected-room-pill span:first-child{font-size:1.8rem;line-height:1}
.selected-room-pill strong{display:block;font-size:.9rem;font-weight:800;color:var(--primary)}
.selected-room-pill small{font-size:.74rem;color:var(--text-muted);font-weight:600}

/* ─── Slot section ─── */
.slot-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:9px}
.slot-legend{display:flex;flex-wrap:wrap;gap:6px 12px;margin-bottom:12px}
.slot-legend span{display:flex;align-items:center;gap:5px;font-size:.72rem;font-weight:600;color:var(--text-muted)}
.ld{display:inline-block;width:10px;height:10px;border-radius:3px;flex-shrink:0}
.ld-free{background:#ede9ff;border:1.5px solid #a89fe8}
.ld-taken{background:#fee2e2;border:1.5px solid #fca5a5}
.ld-mine{background:#d1fae5;border:1.5px solid #6ee7b7}
.ld-past{background:#f3f4f6;border:1.5px solid #d1d5db}
.ld-sel{background:var(--primary);border:1.5px solid var(--primary)}

/* ─── Slot grid ─── */
.slot-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin-bottom:10px}
.slt{padding:9px 4px;border-radius:10px;font-size:.8rem;font-weight:700;border:2px solid transparent;cursor:pointer;text-align:center;font-family:inherit;line-height:1.25;transition:all .2s var(--ease);position:relative;overflow:hidden}
/* free */
.slt.free{background:#f0edff;color:#5b4dc0;border-color:#c9c2f5}
.slt.free:hover{background:var(--primary);color:white;border-color:var(--primary);transform:translateY(-2px);box-shadow:0 6px 16px rgba(109,95,204,.3)}
/* selected */
.slt.sel{background:var(--primary);color:white;border-color:var(--primary);box-shadow:0 5px 14px rgba(109,95,204,.35);animation:sPulse .3s var(--ease)}
@keyframes sPulse{0%{transform:scale(.93)}60%{transform:scale(1.05)}100%{transform:scale(1)}}
/* taken */
.slt.taken{background:#fee2e2;color:#b91c1c;border-color:#fca5a5;cursor:not-allowed}
/* mine */
.slt.mine{background:#d1fae5;color:#065f46;border-color:#6ee7b7;cursor:not-allowed}
/* past */
.slt.past{background:#f9fafb;color:#9ca3af;border-color:#e5e7eb;cursor:not-allowed;text-decoration:line-through}
/* owner tag */
.slt .slt-sub{display:block;font-size:.62rem;font-weight:500;opacity:.8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* ─── Non-consecutive warning ─── */
.ncw{display:flex;align-items:center;gap:8px;padding:9px 13px;background:#fff7ed;border:1px solid #fed7aa;border-radius:9px;font-size:.8rem;font-weight:600;color:#c2410c;margin-bottom:8px}

/* ─── Summary box ─── */
.bk-summary{background:var(--primary-soft);border:1px solid var(--primary-light);border-radius:var(--radius-sm);padding:12px 14px;animation:fadeUp .28s var(--ease)}
@keyframes fadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.bks-row{display:flex;align-items:center;gap:9px;font-size:.85rem;font-weight:700;color:var(--primary);margin-bottom:3px}
.bks-row:last-child{margin:0}
.bks-row i{width:14px;text-align:center;opacity:.7}

/* ─── Submit ─── */
#submitBtn:not([disabled]){opacity:1!important;cursor:pointer!important}

/* ─── Tip ─── */
.form-tip{margin-top:14px;padding:11px 13px;background:var(--primary-soft);border-radius:var(--radius-sm);font-size:.78rem;color:var(--primary);display:flex;align-items:flex-start;gap:8px;line-height:1.5}

/* ─── Responsive ─── */
@media(max-width:1100px){.booking-layout{grid-template-columns:1fr}.booking-panel{position:static}}
@media(max-width:640px){.room-grid{grid-template-columns:1fr}.page-head h1{font-size:2rem}.booking-form-card{padding:18px}}
</style>

<script>
(function(){
'use strict';
const HOURS = <?= json_encode($operation_hours) ?>;
const ROOMS = <?= json_encode(array_values($available_rooms)) ?>;
let room     = <?= json_encode($preselect_room) ?>;
let date     = document.getElementById('bookingDate').value;
let selSlots = [];
let occupied = {};
let timer    = null;

const grid    = document.getElementById('slotGrid');
const spinner = document.getElementById('slotSpinner');
const ncw     = document.getElementById('ncw');
const summary = document.getElementById('bkSummary');
const bksTime = document.getElementById('bksTime');
const bksDur  = document.getElementById('bksDur');
const btnSub  = document.getElementById('submitBtn');
const fRoom   = document.getElementById('fRoom');
const fDate   = document.getElementById('fDate');
const fSlots  = document.getElementById('fSlots');

/* room select */
window.selectRoom = function(idx, name){
    room = name; fRoom.value = name;
    document.querySelectorAll('.room-card').forEach((c,i)=>{
        c.classList.toggle('selected', i===idx);
        const old = c.querySelector('.room-check-badge');
        if(old) old.remove();
        if(i===idx){
            const visu = c.querySelector('.room-visual');
            const d = document.createElement('div');
            d.className = 'room-check-badge';
            d.innerHTML = '<i class="fa-solid fa-check"></i>';
            visu.appendChild(d);
        }
    });
    const r = ROOMS[idx];
    document.getElementById('pillIcon').textContent = r.icon;
    document.getElementById('pillName').textContent = r.name;
    document.getElementById('pillCap').textContent  = r.capacity;
    const pill = document.getElementById('roomPill');
    pill.style.transform='scale(.96)';
    setTimeout(()=>pill.style.transform='',160);
    selSlots=[]; debounce();
    if(window.innerWidth<1100) document.querySelector('.booking-panel').scrollIntoView({behavior:'smooth',block:'start'});
};

/* date change */
document.getElementById('bookingDate').addEventListener('change',function(){
    date=this.value; fDate.value=this.value; selSlots=[]; debounce();
});

function debounce(){ clearTimeout(timer); timer=setTimeout(load,260); }

async function load(){
    spinner.style.display='inline'; grid.style.opacity='.5';
    try{
        const r=await fetch('booking_slots.php?room='+encodeURIComponent(room)+'&date='+encodeURIComponent(date));
        if(!r.ok) throw new Error();
        const d=await r.json();
        occupied={};
        (d.occupied||[]).forEach(s=>{ occupied[s]=d.owners?.[s]??null; });
    }catch(e){ occupied={}; }
    spinner.style.display='none'; grid.style.opacity='1';
    render();
}

function render(){
    const today=new Date().toISOString().split('T')[0];
    const curH =new Date().getHours();
    const isToday=(date===today);
    grid.innerHTML='';

    HOURS.forEach(h=>{
        const hr=parseInt(h,10);
        const btn=document.createElement('button');
        btn.type='button'; btn.dataset.h=h;

        let st='free';
        if(isToday && hr<=curH)               st='past';
        else if(occupied[h]!==undefined)       st=occupied[h]?.is_you?'mine':'taken';
        else if(selSlots.includes(h))          st='sel';

        btn.className='slt '+st;
        btn.innerHTML='<span>'+h+'</span>';

        if(st==='taken'&&occupied[h]?.nama){
            btn.innerHTML+='<span class="slt-sub">'+occupied[h].nama.split(' ')[0]+'</span>';
        }
        if(st==='mine') btn.innerHTML+='<span class="slt-sub">Anda</span>';

        if(st==='free'||st==='sel') btn.addEventListener('click',()=>toggle(h));
        grid.appendChild(btn);
    });
    syncSummary();
}

function toggle(h){
    if(selSlots.includes(h)){
        selSlots=selSlots.filter(s=>s!==h);
    } else {
        selSlots.push(h);
    }
    /* check consecutive */
    if(selSlots.length>1){
        const idxs=selSlots.map(s=>HOURS.indexOf(s)).sort((a,b)=>a-b);
        let ok=true;
        for(let i=1;i<idxs.length;i++) if(idxs[i]!==idxs[i-1]+1){ok=false;break;}
        if(!ok){ ncw.style.display='flex'; selSlots=[h]; }
        else     ncw.style.display='none';
    } else {
        ncw.style.display='none';
    }
    render();
}

function syncSummary(){
    if(!selSlots.length){
        summary.style.display='none';
        btnSub.disabled=true; btnSub.style.opacity='.45'; btnSub.style.cursor='not-allowed';
        fSlots.value=''; return;
    }
    const sorted=[...selSlots].sort();
    const until=String(parseInt(sorted[sorted.length-1],10)+1).padStart(2,'0')+':00';
    bksTime.textContent=sorted[0]+' → '+until;
    bksDur.textContent=sorted.length+' jam  ('+sorted.join(', ')+')';
    summary.style.display='block';
    btnSub.disabled=false; btnSub.style.opacity='1'; btnSub.style.cursor='pointer';
    fSlots.value=sorted.join(',');
    fDate.value=date; fRoom.value=room;
}

/* init */
load();
})();
</script>

<?php include 'footer.php'; ?>
