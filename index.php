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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:       #080810;
            --bg2:      #0f0f1a;
            --gold:     #c9a84c;
            --gold-lt:  #e8c96b;
            --gold-dk:  #a07830;
            --text:     #f0f0f0;
            --text-m:   #8888aa;
            --border:   #1e1e30;
            --card:     #10101e;
        }

        html, body {
            height: 100%;
        }

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
            overflow-x: hidden;
        }

        /* Фоновый паттерн */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 50% at 50% 0%, rgba(201,168,76,0.08) 0%, transparent 60%),
                linear-gradient(135deg, #080810 0%, #0f0f1a 50%, #080810 100%);
            pointer-events: none;
            z-index: 0;
        }

        /* Сетка */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(201,168,76,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(201,168,76,0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none;
            z-index: 0;
        }

        .container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 680px;
            padding: 48px 32px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0;
        }

        /* Логотип / заголовок */
        .brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 48px;
        }

        .brand-icon {
            width: 64px;
            height: 64px;
            border: 2px solid var(--gold);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            position: relative;
            background: rgba(201,168,76,0.06);
        }

        .brand-icon svg {
            width: 32px;
            height: 32px;
            fill: var(--gold);
        }

        .brand-title {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: var(--text);
            text-align: center;
        }

        .brand-title span {
            color: var(--gold);
        }

        .brand-line {
            width: 48px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            margin-top: 16px;
        }

        /* Слоган */
        .slogan {
            text-align: center;
            font-size: 16px;
            font-weight: 400;
            color: var(--text-m);
            line-height: 1.7;
            max-width: 520px;
            white-space: pre-line;
        }

        .slogan-above { margin-bottom: 40px; }
        .slogan-below { margin-top: 40px; }

        /* Кнопки */
        .buttons {
            display: flex;
            flex-direction: column;
            gap: 16px;
            width: 100%;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 28px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text);
            text-decoration: none;
            font-size: 17px;
            font-weight: 500;
            letter-spacing: 0.1px;
            cursor: pointer;
            transition: all 0.22s ease;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(201,168,76,0.07), transparent);
            opacity: 0;
            transition: opacity 0.22s ease;
        }

        .btn:hover {
            border-color: var(--gold-dk);
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(201,168,76,0.12), 0 2px 8px rgba(0,0,0,0.4);
        }

        .btn:hover::before { opacity: 1; }

        .btn:active {
            transform: translateY(0);
            box-shadow: none;
        }

        .btn-text {
            position: relative;
            z-index: 1;
        }

        .btn-arrow {
            position: relative;
            z-index: 1;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(201,168,76,0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: background 0.22s ease;
        }

        .btn:hover .btn-arrow {
            background: rgba(201,168,76,0.22);
        }

        .btn-arrow svg {
            width: 16px;
            height: 16px;
            stroke: var(--gold);
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* Нумерация кнопок */
        .btn-num {
            position: relative;
            z-index: 1;
            font-size: 12px;
            color: var(--gold);
            font-weight: 600;
            margin-right: 16px;
            opacity: 0.8;
            min-width: 20px;
        }

        .btn-left {
            display: flex;
            align-items: center;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 24px;
            color: rgba(255,255,255,0.15);
            font-size: 12px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* Loading state для кнопок */
        .btn.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        @media (max-width: 480px) {
            .container { padding: 32px 20px; }
            .brand-title { font-size: 22px; }
            .btn { padding: 18px 20px; font-size: 15px; }
            .slogan { font-size: 14px; }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="brand">
        <div class="brand-icon">
            <!-- Иконка: люди/карьера -->
            <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                <circle cx="10" cy="8" r="4"/>
                <path d="M2 24c0-4.4 3.6-8 8-8s8 3.6 8 8"/>
                <circle cx="24" cy="8" r="3"/>
                <path d="M24 16c3.3 0 6 2.7 6 6"/>
                <line x1="20" y1="8" x2="26" y2="14" stroke-width="1.5" stroke="currentColor" fill="none"/>
            </svg>
        </div>
        <h1 class="brand-title"><?= e($title) ?></h1>
        <div class="brand-line"></div>
    </div>

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

<div class="footer">Кадровое агентство</div>

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
