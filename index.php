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

// ─── Карточки доверия (по языку) ─────────────────────────────────────────────
$trustCards = [
    'ru' => [
        ['icon' => 'shield', 'label' => 'Гарантия работы',    'sub' => 'трудоустройство 100%'],
        ['icon' => 'zap',    'label' => 'Уровень зарплаты',   'sub' => 'фиксируем официально'],
        ['icon' => 'star',   'label' => 'Стабильность',       'sub' => 'без задержек выплат'],
        ['icon' => 'check',  'label' => 'Честные условия',    'sub' => 'всё прозрачно'],
    ],
    'en' => [
        ['icon' => 'shield', 'label' => 'Job Guarantee',      'sub' => '100% placement'],
        ['icon' => 'zap',    'label' => 'Fixed Salary',       'sub' => 'officially confirmed'],
        ['icon' => 'star',   'label' => 'Stability',          'sub' => 'no payment delays'],
        ['icon' => 'check',  'label' => 'Fair Terms',         'sub' => 'fully transparent'],
    ],
    'ge' => [
        ['icon' => 'shield', 'label' => 'სამუშაოს გარანტია', 'sub' => '100% დასაქმება'],
        ['icon' => 'zap',    'label' => 'ხელფასის დონე',      'sub' => 'ოფიციალურად'],
        ['icon' => 'star',   'label' => 'სტაბილურობა',        'sub' => 'გადახდის გარეშე'],
        ['icon' => 'check',  'label' => 'სამართლიანი პირობები','sub' => 'გამჭვირვალედ'],
    ],
    'tr' => [
        ['icon' => 'shield', 'label' => 'İş Garantisi',       'sub' => '%100 istihdam'],
        ['icon' => 'zap',    'label' => 'Maaş Seviyesi',      'sub' => 'resmi olarak'],
        ['icon' => 'star',   'label' => 'Kararlılık',         'sub' => 'gecikme yok'],
        ['icon' => 'check',  'label' => 'Adil Koşullar',      'sub' => 'şeffaf süreç'],
    ],
];
$trust = $trustCards[$lang] ?? $trustCards['ru'];

$trustIcons = [
    'shield' => '<path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7L12 2z"/>',
    'star'   => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
    'zap'    => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
    'check'  => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:      #0f0f0f;
            --bg2:     #171717;
            --gold:    #d4a843;
            --gold-lt: #f0cc70;
            --gold-dk: #9a7420;
            --gold-dim:rgba(212,168,67,0.12);
            --text:    #f0f0f0;
            --muted:   #888888;
            --border:  rgba(212,168,67,0.2);
            --card:    #1a1a1a;
        }

        html, body { height: 100%; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* ── Частицы ── */
        #particles {
            position: fixed; inset: 0;
            z-index: 0; pointer-events: none;
        }

        /* ── Свет сверху ── */
        .bg-glow {
            position: fixed;
            top: -180px; left: 50%;
            transform: translateX(-50%);
            width: 600px; height: 500px;
            background: radial-gradient(ellipse, rgba(212,168,67,0.10) 0%, transparent 65%);
            pointer-events: none; z-index: 0;
        }

        /* ── Тонкая горизонтальная линия по центру ── */
        .bg-line {
            position: fixed;
            top: 50%; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(212,168,67,0.06), transparent);
            pointer-events: none; z-index: 0;
        }

        /* ── Обёртка ── */
        .wrap {
            position: relative; z-index: 1;
            width: 100%; max-width: 600px;
            padding: 64px 32px 100px;
            display: flex; flex-direction: column;
            align-items: center; text-align: center;
        }

        /* ── Логотип ── */
        .logo-ring {
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 32px;
            animation: fadeUp 0.45s ease both;
        }
        .logo-sign {
            font-size: 72px;
            font-weight: 900;
            line-height: 1;
            background: linear-gradient(180deg, var(--gold-lt) 0%, var(--gold-dk) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 0 18px rgba(212,168,67,0.4));
            animation: spinY 3s ease-in-out infinite;
            display: inline-block;
        }
        @keyframes spinY {
            0%   { transform: rotateY(0deg); }
            45%  { transform: rotateY(180deg); }
            55%  { transform: rotateY(180deg); }
            100% { transform: rotateY(360deg); }
        }

        /* ── Заголовок ── */
        .headline {
            font-size: clamp(30px, 5.5vw, 52px);
            font-weight: 900;
            line-height: 1.08;
            letter-spacing: -1.5px;
            color: var(--text);
            margin-bottom: 18px;
            animation: fadeUp 0.45s 0.07s ease both;
        }
        .headline mark {
            background: linear-gradient(90deg, var(--gold), var(--gold-lt));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ── Слоган ── */
        .slogan {
            font-size: 15px;
            font-weight: 400;
            font-style: italic;
            color: var(--muted);
            line-height: 1.75;
            max-width: 420px;
            letter-spacing: 0.1px;
            white-space: pre-line;
        }
        .slogan-above { margin-bottom: 44px; animation: fadeUp 0.45s 0.13s ease both; }
        .slogan-below { margin-top: 36px;    animation: fadeUp 0.45s 0.32s ease both; }

        /* ── Кнопки ── */
        .buttons {
            display: flex; flex-direction: column;
            align-items: center;
            gap: 10px; width: 100%;
            animation: fadeUp 0.45s 0.20s ease both;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            width: 72%;
            background: var(--card);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 10px;
            color: var(--text);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(90deg, rgba(212,168,67,0.07), transparent);
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .btn:hover {
            border-color: rgba(212,168,67,0.45);
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(0,0,0,0.4), 0 0 0 1px rgba(212,168,67,0.18);
        }
        .btn:hover::before { opacity: 1; }
        .btn:active { transform: translateY(0); }

        .btn-text { position: relative; z-index: 1; }

        .btn-arrow {
            position: relative; z-index: 1;
            display: flex; align-items: center; justify-content: center;
            width: 26px; height: 26px;
            border-radius: 5px;
            background: rgba(212,168,67,0.1);
            border: 1px solid rgba(212,168,67,0.2);
            flex-shrink: 0;
            transition: all 0.2s ease;
        }
        .btn:hover .btn-arrow {
            background: rgba(212,168,67,0.22);
            border-color: var(--gold);
        }
        .btn-arrow svg {
            width: 12px; height: 12px;
            stroke: var(--gold);
            fill: none; stroke-width: 2.5;
            stroke-linecap: round; stroke-linejoin: round;
            transition: transform 0.2s ease;
        }
        .btn:hover .btn-arrow svg { transform: translateX(2px); }

        /* ── Плашки-награды (низ) ── */
        .awards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px;
            width: 100%;
            margin-top: 44px;
            animation: fadeUp 0.45s 0.28s ease both;
            border: 1px solid rgba(212,168,67,0.15);
            border-radius: 12px;
            overflow: hidden;
        }

        .award {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 18px 12px;
            background: rgba(212,168,67,0.04);
            position: relative;
            min-height: 80px;
        }

        .award:nth-child(odd) { border-right: 1px solid rgba(212,168,67,0.12); }
        .award:nth-child(1),
        .award:nth-child(2)   { border-bottom: 1px solid rgba(212,168,67,0.12); }

        /* Лавровая ветка SVG слева и справа */
        .award-inner {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }

        .laurel {
            width: 18px; height: 28px;
            opacity: 0.6;
        }
        .laurel-right { transform: scaleX(-1); }

        .award-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: var(--gold-lt);
            white-space: nowrap;
            text-align: center;
        }

        .award-sub {
            font-size: 9px;
            color: var(--muted);
            letter-spacing: 0.8px;
            text-transform: uppercase;
            text-align: center;
        }

        /* ── Переключатель языков ── */
        .lang-sw {
            position: fixed;
            top: 20px; right: 20px;
            z-index: 100;
            display: flex;
            background: rgba(20,20,20,0.85);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            backdrop-filter: blur(12px);
        }

        .lang-btn {
            padding: 8px 12px;
            font-size: 10px; font-weight: 700;
            letter-spacing: 1px;
            color: var(--muted);
            text-decoration: none;
            border-right: 1px solid rgba(255,255,255,0.05);
            transition: all 0.15s;
        }
        .lang-btn:last-child { border-right: none; }
        .lang-btn:hover { color: var(--text); background: rgba(212,168,67,0.08); }
        .lang-btn.active { color: #000; background: linear-gradient(135deg, var(--gold-lt), var(--gold)); }

        /* ── Золотая черта внизу ── */
        .footer-line {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            opacity: 0.35;
        }

        /* ── Анимация ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 520px) {
            .wrap { padding: 52px 20px 90px; }
            .headline { letter-spacing: -0.8px; }
            .btn { padding: 17px 18px; }
            .award { padding: 0 12px; }
            .award-title { font-size: 10px; }
            .lang-sw { top: 12px; right: 12px; }
            .lang-btn { padding: 7px 9px; }
        }
    </style>
</head>
<body>

<canvas id="particles"></canvas>
<div class="bg-glow"></div>
<div class="bg-line"></div>

<!-- Переключатель языков -->
<nav class="lang-sw">
    <a href="?lang=ru" class="lang-btn <?= $lang === 'ru' ? 'active' : '' ?>">РУ</a>
    <a href="?lang=en" class="lang-btn <?= $lang === 'en' ? 'active' : '' ?>">EN</a>
    <a href="?lang=ge" class="lang-btn <?= $lang === 'ge' ? 'active' : '' ?>">GE</a>
    <a href="?lang=tr" class="lang-btn <?= $lang === 'tr' ? 'active' : '' ?>">TR</a>
</nav>

<div class="wrap">

    <!-- Логотип -->
    <div class="logo-ring">
        <span class="logo-sign">$</span>
    </div>

    <!-- Заголовок -->
    <h1 class="headline"><?= e($title) ?></h1>

    <!-- Слоган выше -->
    <?php if ($sloganPos === 'above' && $slogan): ?>
        <p class="slogan slogan-above"><?= e($slogan) ?></p>
    <?php endif; ?>

    <!-- Кнопки -->
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
                <div class="btn-arrow">
                    <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Слоган ниже -->
    <?php if ($sloganPos === 'below' && $slogan): ?>
        <p class="slogan slogan-below"><?= e($slogan) ?></p>
    <?php endif; ?>

    <!-- Плашки-награды -->
    <div class="awards">
        <?php
        // SVG лавровой ветки
        $laurel = '<svg class="laurel" viewBox="0 0 18 28" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M9 26 C9 26 9 20 9 14" stroke="#d4a843" stroke-width="1" stroke-linecap="round"/>
            <path d="M9 22 C6 20 4 17 5 14" stroke="#d4a843" stroke-width="1.2" stroke-linecap="round" fill="none"/>
            <path d="M9 18 C6 16 5 13 6 10" stroke="#d4a843" stroke-width="1.2" stroke-linecap="round" fill="none"/>
            <path d="M9 14 C7 11 7 8 9 6" stroke="#d4a843" stroke-width="1.2" stroke-linecap="round" fill="none"/>
            <path d="M9 22 C12 20 14 17 13 14" stroke="#d4a843" stroke-width="1.2" stroke-linecap="round" fill="none"/>
            <path d="M9 18 C12 16 13 13 12 10" stroke="#d4a843" stroke-width="1.2" stroke-linecap="round" fill="none"/>
            <path d="M9 14 C11 11 11 8 9 6" stroke="#d4a843" stroke-width="1.2" stroke-linecap="round" fill="none"/>
            <circle cx="9" cy="5" r="1.5" fill="#d4a843" opacity="0.7"/>
        </svg>';
        foreach ($trust as $card): ?>
        <div class="award">
            <div class="award-inner">
                <?= $laurel ?>
                <div class="award-title"><?= e($card['label']) ?></div>
                <svg class="laurel laurel-right" viewBox="0 0 18 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 26 C9 26 9 20 9 14" stroke="#d4a843" stroke-width="1" stroke-linecap="round"/>
                    <path d="M9 22 C6 20 4 17 5 14" stroke="#d4a843" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                    <path d="M9 18 C6 16 5 13 6 10" stroke="#d4a843" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                    <path d="M9 14 C7 11 7 8 9 6" stroke="#d4a843" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                    <path d="M9 22 C12 20 14 17 13 14" stroke="#d4a843" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                    <path d="M9 18 C12 16 13 13 12 10" stroke="#d4a843" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                    <path d="M9 14 C11 11 11 8 9 6" stroke="#d4a843" stroke-width="1.2" stroke-linecap="round" fill="none"/>
                    <circle cx="9" cy="5" r="1.5" fill="#d4a843" opacity="0.7"/>
                </svg>
            </div>
            <div class="award-sub"><?= e($card['sub']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

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
