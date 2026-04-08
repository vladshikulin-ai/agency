<?php
session_start();
require_once __DIR__ . '/_init.php';

secHeaders();

// ─── Rate limiting: 150 запросов/мин с одного IP ─────────────────────────────
if (!rateLimit('page', 150, 60)) {
    http_response_code(429);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>429</title></head><body style="background:#0d0d0d;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0"><h2>Слишком много запросов. Попробуйте позже.</h2></body></html>');
}

// ─── Трекинг уникальных посетителей (по дню) ─────────────────────────────────
$ipHash = hashIP(getClientIP());
$today  = date('Y-m-d');
$db     = getDB();

$stmt = $db->prepare("SELECT id FROM visitors WHERE ip_hash = ? AND date = ?");
$stmt->execute([$ipHash, $today]);
if (!$stmt->fetchColumn()) {
    $db->prepare("INSERT INTO visitors (ip_hash, date, timestamp) VALUES (?, ?, ?)")
       ->execute([$ipHash, $today, time()]);
}

// ─── Язык ────────────────────────────────────────────────────────────────────
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $cookieLang = $_GET['lang'];
    setcookie('lang', $cookieLang, time() + 60 * 60 * 24 * 365, '/', '', true, true);
    header('Location: /');
    exit;
}
$lang = detectLang();

// ─── Конфиг ──────────────────────────────────────────────────────────────────
$title     = cfgLang('site_title', $lang, 'Кадровое Агентство');
$slogan    = cfgLang('slogan', $lang, '');
$sloganPos = cfg('slogan_position', 'above');

$buttons = [];
for ($i = 0; $i < 3; $i++) {
    if (cfg("button_{$i}_enabled", '1') === '1') {
        $buttons[] = [
            'index' => $i,
            'text'  => cfgLang("button_{$i}_text", $lang, "Button " . ($i + 1)),
            'url'   => cfg("button_{$i}_url", '#'),
        ];
    }
}

$csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:       #f5f3ee;
            --bg2:      #ede9e0;
            --gold:     #c8a84b;
            --gold-dk:  #a07828;
            --gold-lt:  #e8d08a;
            --dark:     #1a1610;
            --dark2:    #3d3420;
            --muted:    #7a6e58;
            --card:     #ffffff;
            --border:   rgba(200,168,75,0.25);
        }

        html, body { height: 100%; }

        body {
            background: var(--bg);
            color: var(--dark);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* ── Canvas для частиц (под всем) ── */
        #particles {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }

        /* ── Мягкий радиальный свет в центре ── */
        .bg-glow {
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -60%);
            width: 700px; height: 700px;
            background: radial-gradient(ellipse, rgba(200,168,75,0.13) 0%, transparent 65%);
            pointer-events: none;
            z-index: 0;
        }

        /* ── Контейнер ── */
        .wrap {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 580px;
            padding: 60px 32px 80px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        /* ── Логотип-монета ── */
        .logo-ring {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold-lt), var(--gold-dk));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
            box-shadow: 0 8px 32px rgba(200,168,75,0.35), 0 2px 8px rgba(0,0,0,0.08);
            animation: fadeUp 0.4s ease both;
        }

        .logo-ring svg {
            width: 34px; height: 34px;
            fill: #fff;
        }

        /* ── Заголовок ── */
        .headline {
            font-size: clamp(28px, 5vw, 46px);
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -1px;
            color: var(--dark);
            margin-bottom: 16px;
            animation: fadeUp 0.4s 0.07s ease both;
        }

        .headline span {
            background: linear-gradient(135deg, var(--gold), var(--gold-dk));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ── Слоган ── */
        .slogan {
            font-size: 16px;
            font-weight: 400;
            color: var(--muted);
            line-height: 1.7;
            max-width: 440px;
            white-space: pre-line;
        }
        .slogan-above { margin-bottom: 40px; animation: fadeUp 0.4s 0.14s ease both; }
        .slogan-below { margin-top: 32px;    animation: fadeUp 0.4s 0.30s ease both; }

        /* ── Кнопки ── */
        .buttons {
            display: flex;
            flex-direction: column;
            gap: 14px;
            width: 100%;
            animation: fadeUp 0.4s 0.20s ease both;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 18px 28px;
            background: var(--card);
            border: 1.5px solid var(--border);
            border-radius: 14px;
            color: var(--dark);
            text-decoration: none;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.22s ease;
            box-shadow: 0 2px 12px rgba(200,168,75,0.08), 0 1px 3px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(200,168,75,0.08), transparent);
            opacity: 0;
            transition: opacity 0.22s ease;
        }

        .btn:hover {
            border-color: var(--gold);
            transform: translateY(-3px);
            box-shadow: 0 12px 36px rgba(200,168,75,0.22), 0 3px 10px rgba(0,0,0,0.08);
            color: var(--dark);
        }

        .btn:hover::before { opacity: 1; }
        .btn:active { transform: translateY(-1px); }

        .btn-text {
            position: relative;
            z-index: 1;
        }

        .btn-icon {
            position: relative;
            z-index: 1;
            width: 28px; height: 28px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--gold-lt), var(--gold));
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: transform 0.22s ease;
        }

        .btn:hover .btn-icon { transform: translateX(3px); }

        .btn-icon svg {
            width: 13px; height: 13px;
            stroke: #fff;
            fill: none;
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* ── Золотая линия внизу ── */
        .footer-line {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), var(--gold-lt), var(--gold), transparent);
            opacity: 0.6;
        }

        /* ── Переключатель языков ── */
        .lang-sw {
            position: fixed;
            top: 20px; right: 20px;
            z-index: 100;
            display: flex;
            background: rgba(255,255,255,0.8);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            backdrop-filter: blur(8px);
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        .lang-btn {
            padding: 8px 13px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            color: var(--muted);
            text-decoration: none;
            border-right: 1px solid var(--border);
            transition: all 0.15s;
        }

        .lang-btn:last-child { border-right: none; }
        .lang-btn:hover { color: var(--dark); background: rgba(200,168,75,0.08); }

        .lang-btn.active {
            color: #fff;
            background: linear-gradient(135deg, var(--gold), var(--gold-dk));
        }

        /* ── Анимация ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 480px) {
            .wrap { padding: 48px 20px 72px; }
            .headline { letter-spacing: -0.5px; }
            .btn { padding: 16px 20px; font-size: 15px; }
            .lang-sw { top: 12px; right: 12px; }
            .lang-btn { padding: 7px 10px; }
        }
    </style>
</head>
<body>

<canvas id="particles"></canvas>
<div class="bg-glow"></div>

<!-- Переключатель языков -->
<nav class="lang-sw">
    <a href="?lang=ru" class="lang-btn <?= $lang === 'ru' ? 'active' : '' ?>">РУ</a>
    <a href="?lang=en" class="lang-btn <?= $lang === 'en' ? 'active' : '' ?>">EN</a>
    <a href="?lang=ge" class="lang-btn <?= $lang === 'ge' ? 'active' : '' ?>">GE</a>
    <a href="?lang=tr" class="lang-btn <?= $lang === 'tr' ? 'active' : '' ?>">TR</a>
</nav>

<div class="wrap">

    <div class="logo-ring">
        <svg viewBox="0 0 32 32"><path d="M16 3C8.8 3 3 8.8 3 16s5.8 13 13 13 13-5.8 13-13S23.2 3 16 3zm1 18.9V23h-2v-1.1c-2.3-.4-4-2-4-4.9h2c0 2 1.1 3 3 3s3-1 3-2.5c0-1.6-1-2.5-3-3.1C13.6 13.6 11 12.3 11 9.5c0-2.5 1.7-4.1 4-4.4V4h2v1.1c2.3.4 4 2 4 4.9h-2c0-2-1-3-3-3s-3 .9-3 2.5c0 1.5 1 2.3 3.1 2.9C19.4 13 22 14.3 22 17.5c0 2.6-1.7 4.1-4 4.4z"/></svg>
    </div>

    <h1 class="headline"><?= e($title) ?></h1>

    <?php if ($sloganPos === 'above' && $slogan): ?>
        <p class="slogan slogan-above"><?= e($slogan) ?></p>
    <?php endif; ?>

    <div class="buttons">
        <?php foreach ($buttons as $idx => $btn): ?>
            <a
                href="<?= e($btn['url']) ?>"
                class="btn"
                target="_blank"
                rel="noopener noreferrer"
                data-btn="<?= (int)$btn['index'] ?>"
                data-csrf="<?= e($csrfToken) ?>"
            >
                <span class="btn-text"><?= e($btn['text']) ?></span>
                <div class="btn-icon">
                    <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($sloganPos === 'below' && $slogan): ?>
        <p class="slogan slogan-below"><?= e($slogan) ?></p>
    <?php endif; ?>

</div>

<div class="footer-line"></div>

<script>
// ── Золотые частицы ──────────────────────────────────────────────────────────
(function() {
    const canvas = document.getElementById('particles');
    const ctx    = canvas.getContext('2d');
    let W, H, particles = [];

    function resize() {
        W = canvas.width  = window.innerWidth;
        H = canvas.height = window.innerHeight;
    }

    function rand(a, b) { return a + Math.random() * (b - a); }

    function createParticle() {
        return {
            x:     rand(0, W),
            y:     rand(0, H),
            r:     rand(1, 3.5),
            alpha: rand(0.08, 0.55),
            vx:    rand(-0.12, 0.12),
            vy:    rand(-0.22, -0.06),
            pulse: rand(0, Math.PI * 2),
            pulseSpeed: rand(0.008, 0.025),
        };
    }

    function init() {
        resize();
        const count = Math.floor(W * H / 9000);
        particles = Array.from({length: count}, createParticle);
    }

    function draw() {
        ctx.clearRect(0, 0, W, H);
        particles.forEach(function(p) {
            p.x += p.vx;
            p.y += p.vy;
            p.pulse += p.pulseSpeed;
            const a = p.alpha * (0.6 + 0.4 * Math.sin(p.pulse));

            // Перезапуск если вышла за экран
            if (p.y < -10 || p.x < -10 || p.x > W + 10) {
                p.x = rand(0, W);
                p.y = H + 10;
            }

            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(200, 168, 75, ${a})`;
            ctx.fill();
        });
        requestAnimationFrame(draw);
    }

    window.addEventListener('resize', init);
    init();
    draw();
})();

// ── Трекинг кликов ───────────────────────────────────────────────────────────
(function() {
    document.querySelectorAll('.btn[data-btn]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const idx  = btn.getAttribute('data-btn');
            const csrf = btn.getAttribute('data-csrf');
            try {
                navigator.sendBeacon
                    ? navigator.sendBeacon('/track.php', new URLSearchParams({btn: idx, csrf: csrf}))
                    : fetch('/track.php', {method:'POST', body: new URLSearchParams({btn: idx, csrf: csrf}), keepalive: true});
            } catch(e) {}
        });
    });
})();
</script>

</body>
</html>
