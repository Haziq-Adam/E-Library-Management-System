<?php
require_once 'db.php';
session_start();
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_matrik = trim($_POST['no_matrik'] ?? '');
    $password  = $_POST['password'] ?? '';

    $stmt = $conn->prepare('SELECT id, nama, no_matrik, password, role FROM users WHERE no_matrik = ? LIMIT 1');
    $stmt->bind_param('s', $no_matrik);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['nama']      = $user['nama'];
        $_SESSION['no_matrik'] = $user['no_matrik'];
        $_SESSION['role']      = $user['role'];
        $redirect = ($user['role'] === 'admin') ? 'admin_dashboard.php' : 'dashboard.php';
        header('Location: ' . $redirect); exit;
    } else {
        $error = 'Log masuk gagal. Sila semak ID dan Kata Laluan.';
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Masuk — E-Library</title>
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
            overflow: hidden;
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

        /* Left illustration panel */
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
            bottom: -100px; right: -100px;
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
        .auth-hero-content {
            position: relative;
            z-index: 2;
            margin: 40px 0;
        }
        .auth-hero-content h1 {
            font-size: 2.6rem;
            font-weight: 800;
            line-height: 1.1;
            color: #1e1b4b;
            margin-bottom: 16px;
        }
        .auth-hero-content h1 span { color: #6d5fcc; }
        .auth-hero-content p {
            font-size: 1.05rem;
            color: #4a4560;
            line-height: 1.6;
            max-width: 90%;
        }

        /* Floating illustration */
        .auth-illustration {
            position: relative;
            height: 200px;
            margin-top: auto;
        }
        .emoji-scene-auth {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            perspective: 700px;
            --mx: 0;
            --my: 0;
        }
        .emoji-auth-glow {
            position: absolute;
            top: 50%; left: 50%;
            width: 180px; height: 180px;
            margin-top: -90px;
            margin-left: -90px;
            transform: translate(calc(var(--mx) * 18px), calc(var(--my) * 18px));
            background: radial-gradient(circle, rgba(168, 159, 232, 0.5), rgba(196, 187, 240, 0.2) 50%, transparent 70%);
            border-radius: 50%;
            filter: blur(25px);
            animation: glow-pulse-auth 4s ease-in-out infinite;
            transition: transform 0.45s cubic-bezier(.22,1,.36,1);
        }
        @keyframes glow-pulse-auth {
            0%, 100% { opacity: 0.7; }
            50%      { opacity: 1; }
        }
        .emoji-auth-main {
            position: relative;
            animation: auth-bob 5s ease-in-out infinite;
            z-index: 2;
        }
        @keyframes auth-bob {
            0%, 100% { transform: translateY(0) rotate(-4deg); }
            50%      { transform: translateY(-14px) rotate(4deg); }
        }
        .emoji-auth-inner {
            display: inline-block;
            font-size: 6.5rem;
            line-height: 1;
            filter: drop-shadow(0 14px 28px rgba(109, 95, 204, 0.35)) drop-shadow(0 0 16px rgba(168, 159, 232, 0.4));
            transform-style: preserve-3d;
            transform:
                translate(calc(var(--mx) * 14px), calc(var(--my) * 10px))
                rotateY(calc(var(--mx) * 20deg))
                rotateX(calc(var(--my) * -16deg));
            transition: transform 0.35s cubic-bezier(.22,1,.36,1);
            will-change: transform;
        }
        .emoji-auth-float {
            position: absolute;
            filter: drop-shadow(0 4px 10px rgba(109, 95, 204, 0.3));
            animation: auth-float 7s ease-in-out infinite;
            transition: translate 0.5s cubic-bezier(.22,1,.36,1);
        }
        .ef1 { top: 15%; left: 15%; font-size: 1.6rem; animation-delay: 0s;    translate: calc(var(--mx) * 22px) calc(var(--my) * 22px); }
        .ef2 { top: 25%; right: 18%; font-size: 1.4rem; animation-delay: -2s;  translate: calc(var(--mx) * -20px) calc(var(--my) * 18px); }
        .ef3 { bottom: 25%; left: 12%; font-size: 1.5rem; animation-delay: -4s; translate: calc(var(--mx) * 24px) calc(var(--my) * -18px); }
        .ef4 { bottom: 20%; right: 15%; font-size: 1.3rem; animation-delay: -5.5s; translate: calc(var(--mx) * -22px) calc(var(--my) * -20px); }
        @keyframes auth-float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.9; }
            50%      { transform: translate(-6px, -10px) rotate(10deg); opacity: 1; }
        }

        .auth-stats {
            display: flex;
            gap: 20px;
            position: relative;
            z-index: 2;
            margin-top: 20px;
        }
        .auth-stat {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(10px);
            padding: 12px 18px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.9);
        }
        .auth-stat strong { display: block; font-size: 1.3rem; font-weight: 800; color: #6d5fcc; }
        .auth-stat span { font-size: 0.78rem; font-weight: 600; color: #4a4560; }

        /* Right form panel */
        .auth-form-wrap {
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .auth-form-wrap h2 {
            font-size: 2rem;
            font-weight: 800;
            color: #1e1b4b;
            margin-bottom: 8px;
        }
        .auth-form-wrap .subtitle {
            color: #8b87a3;
            font-size: 0.98rem;
            margin-bottom: 36px;
        }
        .auth-form-wrap .form-group { margin-bottom: 18px; }
        .auth-form-wrap .form-link {
            text-align: center;
            margin-top: 24px;
            font-size: 0.95rem;
            color: #8b87a3;
        }
        .auth-form-wrap .form-link a {
            color: #6d5fcc;
            font-weight: 700;
            text-decoration: none;
        }
        .auth-form-wrap .form-link a:hover { text-decoration: underline; }

        @media (max-width: 900px) {
            .auth-shell { grid-template-columns: 1fr; max-width: 480px; border-radius: 32px; }
            .auth-hero { padding: 40px 32px; }
            .auth-hero-content h1 { font-size: 1.9rem; }
            .auth-illustration { display: none; }
            .auth-form-wrap { padding: 40px 32px; }
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
    <svg class="cloud cloud-3" viewBox="0 0 200 100" xmlns="http://www.w3.org/2000/svg">
        <ellipse cx="60" cy="60" rx="45" ry="32" fill="#ffffff"/>
        <ellipse cx="115" cy="52" rx="52" ry="36" fill="#ffffff"/>
        <ellipse cx="160" cy="65" rx="35" ry="24" fill="#ffffff"/>
    </svg>
</div>

<div class="auth-shell">

    <!-- LEFT: Hero / Illustration -->
    <div class="auth-hero">
        <div class="auth-hero-brand">
            <span class="dot"><i class="fa-solid fa-book-open"></i></span>
            E-Library
        </div>

        <div class="auth-hero-content">
            <h1>Discover a <span>New World</span> of Learning.</h1>
            <p>Tempah bilik fasiliti perpustakaan dengan pantas dan teratur — semuanya dalam satu portal yang indah.</p>
        </div>

        <!-- Floating character SVG -->
        <div class="auth-illustration">
            <div class="emoji-scene-auth">
                <div class="emoji-auth-glow"></div>
                <div class="emoji-auth-main"><span class="emoji-auth-inner">📚</span></div>
                <div class="emoji-auth-float ef1">✨</div>
                <div class="emoji-auth-float ef2">⭐</div>
                <div class="emoji-auth-float ef3">💫</div>
                <div class="emoji-auth-float ef4">✨</div>
            </div>
        </div>

        <div class="auth-stats">
            <div class="auth-stat">
                <strong>6+</strong>
                <span>Bilik Fasiliti</span>
            </div>
            <div class="auth-stat">
                <strong>24/7</strong>
                <span>Akses Portal</span>
            </div>
        </div>
    </div>

    <!-- RIGHT: Form -->
    <div class="auth-form-wrap">
        <h2>Selamat Kembali 👋</h2>
        <p class="subtitle">Log masuk ke akaun STB anda untuk mula menempah.</p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label class="form-label">No. Matrik / ID</label>
                <div class="input-icon">
                    <input type="text" name="no_matrik" class="form-control" placeholder="Masukkan No. Matrik" required>
                    <i class="icon fa-solid fa-id-badge"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Kata Laluan</label>
                <div class="input-icon">
                    <input type="password" name="password" class="form-control" placeholder="Masukkan kata laluan" required>
                    <i class="icon fa-solid fa-lock"></i>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 8px;">
                Log Masuk <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <div class="form-link">
            Belum ada akaun? <a href="register.php">Cipta akaun baru</a>
        </div>
    </div>
</div>

<script>
/* ── Emoji Scene Mouse Tracking ── */
(function () {
    const scenes = document.querySelectorAll('.emoji-scene, .emoji-scene-auth');
    if (!scenes.length) return;
    scenes.forEach((s) => { s.style.setProperty('--mx', '0'); s.style.setProperty('--my', '0'); });
    let rafId = null;
    const state = new Map();
    scenes.forEach((s) => state.set(s, { tx: 0, ty: 0, cx: 0, cy: 0 }));

    function onMove(e) {
        scenes.forEach((scene) => {
            const r = scene.getBoundingClientRect();
            const cx = r.left + r.width / 2;
            const cy = r.top + r.height / 2;
            const range = Math.max(window.innerWidth, window.innerHeight) / 2;
            let nx = Math.max(-1, Math.min(1, (e.clientX - cx) / range));
            let ny = Math.max(-1, Math.min(1, (e.clientY - cy) / range));
            const s = state.get(scene); s.tx = nx; s.ty = ny;
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
            if (Math.abs(s.tx - s.cx) > 0.002 || Math.abs(s.ty - s.cy) > 0.002) more = true;
        });
        rafId = more ? requestAnimationFrame(tick) : null;
    }
    window.addEventListener('mousemove', onMove, { passive: true });
})();
</script>

<script src="animations.js"></script>
</body>
</html>
