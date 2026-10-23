<?php
require_once 'db.php';
session_start();
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

$msg = ''; $status = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama       = trim($_POST['nama']       ?? '');
    $no_matrik  = trim($_POST['no_matrik']  ?? '');
    $email      = trim($_POST['email']      ?? '');
    $no_telefon = trim($_POST['no_telefon'] ?? '');
    $password   = $_POST['password'] ?? '';
    $role       = $_POST['role']     ?? '';

    if (empty($nama) || empty($no_matrik) || empty($email) || empty($no_telefon) || empty($password) || empty($role)) {
        $msg = 'Sila lengkapkan semua maklumat.'; $status = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Format e-mel tidak sah.'; $status = 'error';
    } elseif (strlen($password) < 6) {
        $msg = 'Kata laluan perlu sekurang-kurangnya 6 aksara.'; $status = 'error';
    } elseif (!in_array($role, ['pelajar', 'pensyarah'], true)) {
        $msg = 'Peranan tidak sah.'; $status = 'error';
    } else {
        // Check duplicate no_matrik
        $stmt = $conn->prepare('SELECT id FROM users WHERE no_matrik = ? OR email = ?');
        $stmt->bind_param('ss', $no_matrik, $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $msg = 'No. Matrik atau e-mel sudah didaftarkan.'; $status = 'error';
            $stmt->close();
        } else {
            $stmt->close();
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare('INSERT INTO users (nama, no_matrik, email, no_telefon, password, role) VALUES (?, ?, ?, ?, ?, ?)');
            $ins->bind_param('ssssss', $nama, $no_matrik, $email, $no_telefon, $hashed, $role);
            if ($ins->execute()) {
                $msg = 'Pendaftaran berjaya! Menghalakan…'; $status = 'success';
                echo "<script>setTimeout(() => { window.location.href='login.php'; }, 1600);</script>";
            } else {
                $msg = 'Ralat pangkalan data. Sila cuba lagi.'; $status = 'error';
            }
            $ins->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akaun — E-Library Kolej ATX</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="animations.css">
    <style>
        body {
            background: linear-gradient(135deg, #8b7fd9 0%, #a89fe8 50%, #c4bbf0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .auth-shell {
            width: 100%;
            max-width: 1080px;
            background: white;
            border-radius: 40px;
            box-shadow: 0 40px 100px rgba(30, 27, 75, 0.25);
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            position: relative;
            z-index: 10;
            animation: rise .8s cubic-bezier(.22,1,.36,1) both;
        }
        @keyframes rise { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        .auth-hero {
            background: linear-gradient(160deg, #eef0ff 0%, #e0e7ff 50%, #d6cfff 100%);
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .auth-hero::before {
            content: '';
            position: absolute;
            top: -100px; left: -100px;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.6), transparent 70%);
            border-radius: 50%;
        }
        .auth-hero-brand {
            display: flex; align-items: center; gap: 12px;
            font-size: 1.2rem; font-weight: 800; color: #1e1b4b;
            position: relative; z-index: 2;
        }
        .auth-hero-brand .dot {
            width: 38px; height: 38px;
            border-radius: 12px;
            background: linear-gradient(135deg, #a89fe8, #6d5fcc);
            display: flex; align-items: center; justify-content: center;
            color: white;
            box-shadow: 0 6px 18px rgba(109, 95, 204, 0.35);
        }
        /* Kolej ATX badge */
        .auth-hero-brand .atx-badge {
            font-size: 0.7rem;
            font-weight: 700;
            background: linear-gradient(135deg, #6d5fcc, #a89fe8);
            color: white;
            padding: 3px 8px;
            border-radius: 6px;
            letter-spacing: 0.5px;
        }
        .auth-hero-content { position: relative; z-index: 2; margin: 40px 0; }
        .auth-hero-content h1 {
            font-size: 2.4rem; font-weight: 800; line-height: 1.1; color: #1e1b4b; margin-bottom: 16px;
        }
        .auth-hero-content h1 span { color: #6d5fcc; }
        .auth-hero-content p {
            font-size: 1rem; color: #4a4560; line-height: 1.6; max-width: 90%;
        }
        .auth-illustration { position: relative; height: 200px; margin-top: auto; }
        .emoji-scene-auth {
            position: relative; width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
            perspective: 700px; --mx: 0; --my: 0;
        }
        .emoji-auth-glow {
            position: absolute; top: 50%; left: 50%;
            width: 180px; height: 180px; margin-top: -90px; margin-left: -90px;
            transform: translate(calc(var(--mx) * 18px), calc(var(--my) * 18px));
            background: radial-gradient(circle, rgba(244, 164, 184, 0.5), rgba(196, 187, 240, 0.2) 50%, transparent 70%);
            border-radius: 50%; filter: blur(25px);
            animation: glow-pulse-auth 4s ease-in-out infinite;
            transition: transform 0.45s cubic-bezier(.22,1,.36,1);
        }
        @keyframes glow-pulse-auth { 0%,100%{opacity:.7} 50%{opacity:1} }
        .emoji-auth-main { position: relative; animation: auth-bob 5s ease-in-out infinite; z-index: 2; }
        @keyframes auth-bob { 0%,100%{transform:translateY(0) rotate(4deg) scale(1)} 50%{transform:translateY(-14px) rotate(-4deg) scale(1.05)} }
        .emoji-auth-inner {
            display: inline-block; font-size: 6.5rem; line-height: 1;
            filter: drop-shadow(0 14px 28px rgba(109,95,204,.35)) drop-shadow(0 0 16px rgba(255,215,0,.4));
            transform-style: preserve-3d;
            transform: translate(calc(var(--mx)*14px),calc(var(--my)*10px)) rotateY(calc(var(--mx)*20deg)) rotateX(calc(var(--my)*-16deg));
            transition: transform 0.35s cubic-bezier(.22,1,.36,1); will-change: transform;
        }
        .emoji-auth-float {
            position: absolute; filter: drop-shadow(0 4px 10px rgba(109,95,204,.3));
            animation: auth-float 7s ease-in-out infinite;
            transition: translate 0.5s cubic-bezier(.22,1,.36,1);
        }
        .ef1{top:12%;left:18%;font-size:1.6rem;animation-delay:0s;translate:calc(var(--mx)*22px) calc(var(--my)*22px)}
        .ef2{top:22%;right:15%;font-size:1.5rem;animation-delay:-2s;translate:calc(var(--mx)*-20px) calc(var(--my)*18px)}
        .ef3{bottom:22%;left:14%;font-size:1.4rem;animation-delay:-4s;translate:calc(var(--mx)*24px) calc(var(--my)*-18px)}
        .ef4{bottom:18%;right:18%;font-size:1.3rem;animation-delay:-5.5s;translate:calc(var(--mx)*-22px) calc(var(--my)*-20px)}
        @keyframes auth-float{0%,100%{transform:translate(0,0) rotate(0);opacity:.9}50%{transform:translate(6px,-10px) rotate(-10deg);opacity:1}}

        .auth-form-wrap {
            padding: 50px; display: flex; flex-direction: column; justify-content: center;
            overflow-y: auto; max-height: 100vh;
        }
        .auth-form-wrap h2 { font-size: 1.9rem; font-weight: 800; color: #1e1b4b; margin-bottom: 6px; }
        .auth-form-wrap .subtitle { color: #8b87a3; font-size: 0.95rem; margin-bottom: 24px; }
        .auth-form-wrap .form-group { margin-bottom: 16px; }
        .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .auth-form-wrap .form-link {
            text-align: center; margin-top: 20px; font-size: 0.95rem; color: #8b87a3;
        }
        .auth-form-wrap .form-link a { color: #6d5fcc; font-weight: 700; }
        .auth-form-wrap .form-link a:hover { text-decoration: underline; }

        @media (max-width: 900px) {
            .auth-shell { grid-template-columns: 1fr; max-width: 480px; border-radius: 32px; }
            .auth-hero { padding: 40px 32px; }
            .auth-hero-content h1 { font-size: 1.8rem; }
            .auth-illustration { display: none; }
            .auth-form-wrap { padding: 40px 32px; }
            .form-row-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="bg-decor" aria-hidden="true">
    <svg class="cloud cloud-1" viewBox="0 0 200 100" xmlns="http://www.w3.org/2000/svg">
        <ellipse cx="60" cy="60" rx="45" ry="32" fill="#ffffff"/>
        <ellipse cx="110" cy="50" rx="55" ry="38" fill="#ffffff"/>
        <ellipse cx="155" cy="62" rx="38" ry="26" fill="#ffffff"/>
    </svg>
    <svg class="cloud cloud-2" viewBox="0 0 200 100" xmlns="http://www.w3.org/2000/svg">
        <ellipse cx="50" cy="55" rx="40" ry="28" fill="#ffffff"/>
        <ellipse cx="100" cy="45" rx="50" ry="35" fill="#ffffff"/>
        <ellipse cx="150" cy="58" rx="42" ry="30" fill="#ffffff"/>
    </svg>
</div>

<div class="auth-shell">
    <div class="auth-hero">
        <div class="auth-hero-brand">
            <!-- Kolej ATX Logo SVG placeholder -->
            <span class="dot">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 2L3 7v11h5v-5h4v5h5V7L10 2z" fill="white" opacity="0.9"/>
                    <path d="M10 2L3 7h14L10 2z" fill="white"/>
                </svg>
            </span>
            e-LIBRARY &nbsp;<span class="atx-badge">KOLEJ ATX</span>
        </div>
        <div class="auth-hero-content">
            <h1>Join the <span>New World</span> of E-Library.</h1>
            <p>Cipta akaun pelajar atau pensyarah anda dan mula menempah bilik fasiliti dengan pantas.</p>
        </div>
        <div class="auth-illustration">
            <div class="emoji-scene-auth">
                <div class="emoji-auth-glow"></div>
                <div class="emoji-auth-main"><span class="emoji-auth-inner">✨</span></div>
                <div class="emoji-auth-float ef1">🌟</div>
                <div class="emoji-auth-float ef2">💫</div>
                <div class="emoji-auth-float ef3">⭐</div>
                <div class="emoji-auth-float ef4">✨</div>
            </div>
        </div>
    </div>

    <div class="auth-form-wrap">
        <h2>Cipta Akaun ✨</h2>
        <p class="subtitle">Sertai ekosistem STB E-Library dalam beberapa langkah mudah.</p>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $status === 'success' ? 'success' : 'error' ?>">
                <i class="fa-solid <?= $status === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label class="form-label">Nama Penuh</label>
                <div class="input-icon">
                    <input type="text" name="nama" class="form-control" placeholder="cth. Ahmad bin Ali" required
                           value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
                    <i class="icon fa-solid fa-user"></i>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">No. Matrik</label>
                    <div class="input-icon">
                        <input type="text" name="no_matrik" class="form-control" placeholder="cth. 2023001234" required
                               value="<?= htmlspecialchars($_POST['no_matrik'] ?? '') ?>">
                        <i class="icon fa-solid fa-id-badge"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Peranan</label>
                    <select name="role" class="form-control" required>
                        <option value="" disabled <?= empty($_POST['role']) ? 'selected' : '' ?>>Pilih peranan</option>
                        <option value="pelajar"   <?= ($_POST['role'] ?? '') === 'pelajar'   ? 'selected' : '' ?>>Pelajar</option>
                        <option value="pensyarah" <?= ($_POST['role'] ?? '') === 'pensyarah' ? 'selected' : '' ?>>Pensyarah</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">E-mel</label>
                <div class="input-icon">
                    <input type="email" name="email" class="form-control" placeholder="cth. ahmad@student.atx.edu.my" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <i class="icon fa-solid fa-envelope"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">No. Telefon</label>
                <div class="input-icon">
                    <input type="tel" name="no_telefon" class="form-control" placeholder="cth. 0123456789" required
                           value="<?= htmlspecialchars($_POST['no_telefon'] ?? '') ?>">
                    <i class="icon fa-solid fa-phone"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Kata Laluan</label>
                <div class="input-icon">
                    <input type="password" name="password" class="form-control" placeholder="Minimum 6 aksara" required>
                    <i class="icon fa-solid fa-lock"></i>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 8px;">
                Daftar Sekarang <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <div class="form-link">
            Sudah ada akaun? <a href="login.php">Log Masuk</a>
        </div>
    </div>
</div>

<script>
(function () {
    const scenes = document.querySelectorAll('.emoji-scene-auth');
    if (!scenes.length) return;
    let rafId = null;
    const state = new Map();
    scenes.forEach((s) => { s.style.setProperty('--mx','0'); s.style.setProperty('--my','0'); state.set(s,{tx:0,ty:0,cx:0,cy:0}); });
    function onMove(e) {
        scenes.forEach((scene) => {
            const r = scene.getBoundingClientRect();
            const range = Math.max(window.innerWidth, window.innerHeight) / 2;
            const s = state.get(scene);
            s.tx = Math.max(-1, Math.min(1, (e.clientX - r.left - r.width/2) / range));
            s.ty = Math.max(-1, Math.min(1, (e.clientY - r.top  - r.height/2) / range));
        });
        if (!rafId) tick();
    }
    function tick() {
        let more = false;
        scenes.forEach((scene) => {
            const s = state.get(scene);
            s.cx += (s.tx - s.cx) * 0.12;
            s.cy += (s.ty - s.cy) * 0.12;
            scene.style.setProperty('--mx', s.cx.toFixed(3));
            scene.style.setProperty('--my', s.cy.toFixed(3));
            if (Math.abs(s.tx-s.cx)>0.002 || Math.abs(s.ty-s.cy)>0.002) more=true;
        });
        rafId = more ? requestAnimationFrame(tick) : null;
    }
    window.addEventListener('mousemove', onMove, {passive:true});
})();
</script>
<script src="animations.js"></script>
</body>
</html>
