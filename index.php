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

// ─── Конфиг ──────────────────────────────────────────────────────────────────
$title       = cfg('site_title', 'Кадровое Агентство');
$slogan      = cfg('slogan', '');
$sloganPos   = cfg('slogan_position', 'above');

$buttons = [];
for ($i = 0; $i < 3; $i++) {
    if (cfg("button_{$i}_enabled", '1') === '1') {
        $buttons[] = [
            'index' => $i,
            'text'  => cfg("button_{$i}_text", "Кнопка " . ($i + 1)),
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
            --bg:      #000000;
            --accent:  #0057ff;
            --accent2: #00c2ff;
            --white:   #ffffff;
            --dim:     rgba(255,255,255,0.42);
            --dim2:    rgba(255,255,255,0.07);
            --border:  rgba(255,255,255,0.10);
        }

        html, body { height: 100%; }

        body {
            background: var(--bg);
            color: var(--white);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }

        /* Фоновый свет сверху */
        body::before {
            content: '';
            position: fixed;
            top: -200px; left: 50%;
            transform: translateX(-50%);
            width: 800px; height: 500px;
            background: radial-gradient(ellipse, rgba(0,87,255,0.18) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* Тонкая сетка */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
            background-size: 80px 80px;
            pointer-events: none;
            z-index: 0;
        }

        /* ── Контейнер ── */
        .wrap {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 640px;
            padding: 60px 32px 80px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        /* ── Бейдж-лейбл ── */
        .label {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 12px;
            border: 1px solid rgba(0,87,255,0.5);
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: var(--accent2);
            background: rgba(0,87,255,0.10);
            margin-bottom: 28px;
        }

        .label-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--accent2);
            box-shadow: 0 0 6px var(--accent2);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.4; }
        }

        /* ── Заголовок ── */
        .headline {
            font-size: clamp(36px, 6vw, 58px);
            font-weight: 900;
            line-height: 1.08;
            letter-spacing: -1.5px;
            color: var(--white);
            margin-bottom: 20px;
        }

        .headline em {
            font-style: normal;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ── Слоган ── */
        .slogan {
            font-size: 16px;
            font-weight: 400;
            color: var(--dim);
            line-height: 1.7;
            max-width: 480px;
            white-space: pre-line;
        }

        .slogan-above { margin-bottom: 48px; }
        .slogan-below { margin-top: 40px; }

        /* ── Разделитель ── */
        .divider {
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, var(--accent) 0%, rgba(0,194,255,0.3) 40%, transparent 100%);
            margin-bottom: 40px;
        }

        /* ── Кнопки ── */
        .buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 22px 24px;
            background: var(--dim2);
            border: 1px solid var(--border);
            border-left: 3px solid transparent;
            border-radius: 4px;
            color: var(--white);
            text-decoration: none;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: -0.2px;
            cursor: pointer;
            transition: all 0.18s ease;
            position: relative;
            overflow: hidden;
        }

        .btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(0,87,255,0.08), transparent);
            opacity: 0;
            transition: opacity 0.18s ease;
        }

        .btn:hover {
            border-left-color: var(--accent);
            background: rgba(0,87,255,0.08);
            border-color: rgba(0,87,255,0.35);
            border-left-color: var(--accent);
            transform: translateX(4px);
            box-shadow: -4px 0 24px rgba(0,87,255,0.25);
        }

        .btn:hover::after { opacity: 1; }

        .btn:active { transform: translateX(2px); }

        .btn-left {
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            z-index: 1;
        }

        .btn-num {
            font-size: 11px;
            font-weight: 700;
            color: var(--accent2);
            letter-spacing: 1px;
            opacity: 0.7;
            min-width: 18px;
        }

        .btn-text { position: relative; z-index: 1; }

        .btn-arrow {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px; height: 36px;
            border: 1px solid var(--border);
            border-radius: 3px;
            transition: all 0.18s ease;
            flex-shrink: 0;
        }

        .btn:hover .btn-arrow {
            border-color: var(--accent);
            background: rgba(0,87,255,0.2);
        }

        .btn-arrow svg {
            width: 15px; height: 15px;
            stroke: var(--white);
            fill: none;
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
            transition: transform 0.18s ease;
        }

        .btn:hover .btn-arrow svg { transform: translateX(2px); }

        /* ── Футер ── */
        .footer-line {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            opacity: 0.4;
        }

        .footer-text {
            position: fixed;
            bottom: 20px;
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.18);
        }

        /* ── Анимация входа ── */
        .wrap > * {
            animation: fadeUp 0.5s ease both;
        }
        .label      { animation-delay: 0.0s; }
        .headline   { animation-delay: 0.07s; }
        .slogan-above, .divider { animation-delay: 0.14s; }
        .buttons    { animation-delay: 0.20s; }
        .slogan-below { animation-delay: 0.26s; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 480px) {
            .wrap { padding: 48px 20px 72px; }
            .headline { letter-spacing: -1px; }
            .btn { padding: 18px 18px; font-size: 15px; }
        }
    </style>
</head>
<body>

<div class="wrap">

    <div class="label">
        <span class="label-dot"></span>
        Кадровое агентство
    </div>

    <h1 class="headline"><?= e($title) ?></h1>

    <?php if ($sloganPos === 'above' && $slogan): ?>
        <p class="slogan slogan-above"><?= e($slogan) ?></p>
    <?php endif; ?>

    <div class="divider"></div>

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
                <div class="btn-left">
                    <span class="btn-num">0<?= $idx + 1 ?></span>
                    <span class="btn-text"><?= e($btn['text']) ?></span>
                </div>
                <div class="btn-arrow">
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
<div class="footer-text">Кадровое агентство</div>

<script>
(function() {
    const buttons = document.querySelectorAll('.btn[data-btn]');

    buttons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            const idx   = btn.getAttribute('data-btn');
            const csrf  = btn.getAttribute('data-csrf');
            const url   = btn.href;

            // Трекинг — fire & forget (не блокируем переход)
            try {
                navigator.sendBeacon
                    ? navigator.sendBeacon('/track.php', new URLSearchParams({btn: idx, csrf: csrf}))
                    : fetch('/track.php', {method:'POST', body: new URLSearchParams({btn: idx, csrf: csrf}), keepalive: true});
            } catch(ex) { /* silent */ }
        });
    });
})();
</script>

</body>
</html>
