<?php
session_start();
require_once __DIR__ . '/../_init.php';
secHeaders();

$error   = '';
$success = '';

// ─── Обработка логина ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {

    // Rate limit: 10 попыток за 15 минут с одного IP
    if (!rateLimit('admin_login', 10, 900)) {
        $error = 'Слишком много попыток входа. Попробуйте через 15 минут.';
    } else {
        $pw = $_POST['password'] ?? '';
        if (strlen($pw) > 0 && strlen($pw) <= 200 && password_verify($pw, cfg('admin_password'))) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['admin_ip'] = getClientIP();
            header('Location: /admin/');
            exit;
        } else {
            // Одинаковая задержка чтобы исключить timing attack
            usleep(300000);
            $error = 'Неверный пароль.';
        }
    }
}

// ─── Защита сессии (IP-binding) ───────────────────────────────────────────────
if (isAdmin() && isset($_SESSION['admin_ip']) && $_SESSION['admin_ip'] !== getClientIP()) {
    session_destroy();
    header('Location: /admin/');
    exit;
}

// ─── Если не залогинен — показываем форму входа ───────────────────────────────
if (!isAdmin()) { ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — Админ-панель</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #080810;
            color: #f0f0f0;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(ellipse 60% 40% at 50% 0%, rgba(201,168,76,0.07) 0%, transparent 60%);
            pointer-events: none;
        }
        .card {
            position: relative;
            background: #10101e;
            border: 1px solid #1e1e30;
            border-radius: 16px;
            padding: 48px 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.5);
        }
        .card-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #f0f0f0;
        }
        .card-sub {
            font-size: 13px;
            color: #666688;
            margin-bottom: 32px;
        }
        .field { margin-bottom: 20px; }
        label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #8888aa;
            margin-bottom: 8px;
        }
        input[type=password] {
            width: 100%;
            background: #080810;
            border: 1px solid #1e1e30;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 15px;
            color: #f0f0f0;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }
        input[type=password]:focus { border-color: #c9a84c; }
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #c9a84c, #a07830);
            border: none;
            border-radius: 8px;
            padding: 14px;
            font-size: 15px;
            font-weight: 600;
            color: #080810;
            cursor: pointer;
            font-family: inherit;
            transition: opacity 0.2s;
        }
        .btn-submit:hover { opacity: 0.9; }
        .error {
            background: rgba(220,60,60,0.12);
            border: 1px solid rgba(220,60,60,0.3);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            color: #ff7070;
            margin-bottom: 20px;
        }
        .lock-icon {
            width: 48px;
            height: 48px;
            border: 1px solid #1e1e30;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            background: rgba(201,168,76,0.05);
        }
        .lock-icon svg { width: 22px; height: 22px; stroke: #c9a84c; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    </style>
</head>
<body>
<div class="card">
    <div class="lock-icon">
        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    </div>
    <div class="card-title">Панель управления</div>
    <div class="card-sub">Введите пароль администратора</div>

    <?php if ($error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="login">
        <div class="field">
            <label>Пароль</label>
            <input type="password" name="password" autofocus autocomplete="current-password">
        </div>
        <button type="submit" class="btn-submit">Войти</button>
    </form>
</div>
</body>
</html>
<?php
    exit;
} // end if !isAdmin()

// ─── ЗАЛОГИНЕН — показываем дашборд ──────────────────────────────────────────

$db = getDB();

// Статистика
$totalVisits  = (int)$db->query("SELECT COUNT(*) FROM visitors")->fetchColumn();
$todayVisits  = (int)$db->query("SELECT COUNT(*) FROM visitors WHERE date = '" . date('Y-m-d') . "'")->fetchColumn();
$weekVisits   = (int)$db->query("SELECT COUNT(*) FROM visitors WHERE date >= '" . date('Y-m-d', strtotime('-6 days')) . "'")->fetchColumn();
$totalClicks  = (int)$db->query("SELECT COUNT(*) FROM clicks")->fetchColumn();

// Уникальные посетители (всего уникальных ip_hash за всё время)
$uniqueAll    = (int)$db->query("SELECT COUNT(DISTINCT ip_hash) FROM visitors")->fetchColumn();

// Клики по кнопкам
$clickStmt = $db->query("SELECT button_index, COUNT(*) as cnt FROM clicks GROUP BY button_index ORDER BY button_index");
$clicksByBtn = [];
while ($row = $clickStmt->fetch(PDO::FETCH_ASSOC)) {
    $clicksByBtn[(int)$row['button_index']] = (int)$row['cnt'];
}

// Последние 14 дней (для графика)
$chartData = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $stmt = $db->prepare("SELECT COUNT(*) FROM visitors WHERE date = ?");
    $stmt->execute([$d]);
    $chartData[] = ['date' => $d, 'label' => date('d.m', strtotime($d)), 'count' => (int)$stmt->fetchColumn()];
}
$chartMax = max(1, max(array_column($chartData, 'count')));

// Конфиг кнопок
$buttons = [];
for ($i = 0; $i < 3; $i++) {
    $buttons[$i] = [
        'text'    => cfg("button_{$i}_text"),
        'url'     => cfg("button_{$i}_url"),
        'enabled' => cfg("button_{$i}_enabled", '1'),
    ];
}

$siteTitle   = cfg('site_title');
$slogan      = cfg('slogan');
$sloganPos   = cfg('slogan_position', 'above');
$csrf        = csrfToken();
$setupExists = file_exists(SETUP_FILE);

// Флеш-сообщения из сессии (после редиректа)
if (!empty($_SESSION['flash_ok']))  { $success = $_SESSION['flash_ok'];  unset($_SESSION['flash_ok']); }
if (!empty($_SESSION['flash_err'])) { $error   = $_SESSION['flash_err']; unset($_SESSION['flash_err']); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg:    #080810; --bg2: #0f0f1a; --card: #10101e;
            --gold:  #c9a84c; --gold-lt: #e8c96b; --gold-dk: #a07830;
            --text:  #f0f0f0; --text-m: #8888aa;
            --border:#1e1e30; --danger: #e05050; --ok: #50c878;
        }
        body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; min-height: 100vh; }

        /* Layout */
        .layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 220px;
            background: var(--bg2);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 24px 0;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 10;
        }
        .sidebar-logo {
            padding: 0 24px 24px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 16px;
        }
        .sidebar-logo h2 { font-size: 14px; font-weight: 600; color: var(--gold); }
        .sidebar-logo p  { font-size: 11px; color: var(--text-m); margin-top: 2px; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 24px;
            font-size: 13px;
            color: var(--text-m);
            cursor: pointer;
            border-radius: 0;
            transition: all 0.15s;
            text-decoration: none;
            border-left: 2px solid transparent;
        }
        .nav-item:hover { color: var(--text); background: rgba(255,255,255,0.03); }
        .nav-item.active { color: var(--gold); border-left-color: var(--gold); background: rgba(201,168,76,0.05); }
        .nav-item svg { width: 15px; height: 15px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
        .nav-sep { height: 1px; background: var(--border); margin: 8px 24px; }
        .sidebar-bottom { margin-top: auto; padding: 0 24px; }
        .logout-btn {
            display: flex; align-items: center; gap: 8px;
            padding: 10px 12px;
            background: rgba(224,80,80,0.08);
            border: 1px solid rgba(224,80,80,0.2);
            border-radius: 8px;
            color: var(--danger);
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s;
        }
        .logout-btn:hover { background: rgba(224,80,80,0.15); }
        .logout-btn svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

        /* Main */
        .main { margin-left: 220px; flex: 1; padding: 32px 40px; }
        .page-title { font-size: 22px; font-weight: 600; margin-bottom: 4px; }
        .page-sub   { font-size: 13px; color: var(--text-m); margin-bottom: 32px; }

        /* Alerts */
        .alert {
            padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 24px;
            display: flex; align-items: center; gap: 10px;
        }
        .alert-ok  { background: rgba(80,200,120,0.1); border: 1px solid rgba(80,200,120,0.25); color: var(--ok); }
        .alert-err { background: rgba(224,80,80,0.1);  border: 1px solid rgba(224,80,80,0.25);  color: var(--danger); }
        .alert-warn{ background: rgba(201,168,76,0.1); border: 1px solid rgba(201,168,76,0.25); color: var(--gold); }
        .alert svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }

        /* Stats grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 32px; }
        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
        }
        .stat-label { font-size: 11px; color: var(--text-m); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 8px; }
        .stat-value { font-size: 28px; font-weight: 700; color: var(--text); }
        .stat-value.gold { color: var(--gold); }

        /* Chart */
        .chart-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
        }
        .chart-title { font-size: 14px; font-weight: 600; margin-bottom: 20px; color: var(--text-m); }
        .chart-bars { display: flex; align-items: flex-end; gap: 4px; height: 100px; }
        .chart-bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; }
        .chart-bar {
            width: 100%;
            background: linear-gradient(180deg, var(--gold), var(--gold-dk));
            border-radius: 3px 3px 0 0;
            min-height: 2px;
            transition: opacity 0.2s;
        }
        .chart-bar:hover { opacity: 0.8; }
        .chart-label { font-size: 9px; color: var(--text-m); white-space: nowrap; }

        /* Click stats */
        .click-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 32px; }
        .click-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px 20px;
        }
        .click-btn-name { font-size: 13px; font-weight: 500; margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .click-count { font-size: 24px; font-weight: 700; color: var(--gold); }
        .click-label { font-size: 11px; color: var(--text-m); margin-top: 2px; }

        /* Section */
        .section { margin-bottom: 40px; }
        .section-title { font-size: 15px; font-weight: 600; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }

        /* Form */
        .form-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 28px;
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field.full { grid-column: 1 / -1; }
        label { font-size: 12px; font-weight: 500; color: var(--text-m); text-transform: uppercase; letter-spacing: 0.5px; }
        input[type=text], input[type=url], input[type=password], textarea, select {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            color: var(--text);
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
            width: 100%;
        }
        input:focus, textarea:focus, select:focus { border-color: var(--gold); }
        textarea { resize: vertical; min-height: 80px; }
        select option { background: var(--bg2); }

        /* Toggle */
        .toggle-wrap { display: flex; align-items: center; gap: 10px; }
        .toggle { position: relative; width: 40px; height: 22px; cursor: pointer; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute; inset: 0;
            background: #2a2a3a;
            border-radius: 22px;
            transition: background 0.2s;
        }
        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 16px; height: 16px;
            left: 3px; top: 3px;
            background: #666;
            border-radius: 50%;
            transition: transform 0.2s, background 0.2s;
        }
        .toggle input:checked + .toggle-slider { background: rgba(201,168,76,0.3); }
        .toggle input:checked + .toggle-slider::before { transform: translateX(18px); background: var(--gold); }
        .toggle-label { font-size: 13px; color: var(--text-m); }

        /* Buttons */
        .btn-row { display: flex; gap: 12px; margin-top: 24px; }
        .btn-primary {
            background: linear-gradient(135deg, var(--gold), var(--gold-dk));
            border: none;
            border-radius: 8px;
            padding: 11px 24px;
            font-size: 14px;
            font-weight: 600;
            color: #080810;
            cursor: pointer;
            font-family: inherit;
            transition: opacity 0.2s;
        }
        .btn-primary:hover { opacity: 0.88; }
        .btn-secondary {
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 11px 24px;
            font-size: 14px;
            color: var(--text-m);
            cursor: pointer;
            font-family: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: border-color 0.2s, color 0.2s;
        }
        .btn-secondary:hover { border-color: var(--text-m); color: var(--text); }

        /* Tabs */
        .tabs { display: flex; gap: 4px; margin-bottom: 28px; border-bottom: 1px solid var(--border); }
        .tab {
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-m);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            transition: all 0.15s;
        }
        .tab:hover { color: var(--text); }
        .tab.active { color: var(--gold); border-bottom-color: var(--gold); }

        /* Tab panels */
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* Языковые под-вкладки */
        .lang-tabs { display: flex; gap: 4px; margin-bottom: 20px; }
        .lang-tab {
            padding: 7px 16px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.8px;
            color: var(--text-m);
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            background: transparent;
            font-family: inherit;
            transition: all 0.15s;
        }
        .lang-tab:hover { color: var(--text); border-color: var(--text-m); }
        .lang-tab.active { color: var(--gold); border-color: var(--gold); background: rgba(201,168,76,0.07); }
        .lang-panel { display: none; }
        .lang-panel.active { display: block; }

        /* Separator между кнопками в настройках */
        .btn-section {
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 16px;
        }
        .btn-section-header {
            font-size: 13px;
            font-weight: 600;
            color: var(--gold);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; height: auto; flex-direction: row; flex-wrap: wrap; padding: 16px; }
            .main { margin-left: 0; padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <h2>Панель управления</h2>
            <p>Кадровое агентство</p>
        </div>
        <a class="nav-item active" href="#" onclick="showTab('stats'); return false;">
            <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Статистика
        </a>
        <a class="nav-item" href="#" onclick="showTab('settings'); return false;">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            Настройки
        </a>
        <a class="nav-item" href="#" onclick="showTab('password'); return false;">
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Смена пароля
        </a>
        <div class="nav-sep"></div>
        <a class="nav-item" href="/" target="_blank">
            <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            Открыть сайт
        </a>
        <div class="sidebar-bottom">
            <a class="logout-btn" href="/admin/logout.php">
                <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Выйти
            </a>
        </div>
    </aside>

    <!-- Main -->
    <main class="main">

        <?php if ($setupExists): ?>
        <div class="alert alert-warn">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Временный пароль находится в файле <strong>data/SETUP_PASSWORD.txt</strong>. После смены пароля удалите этот файл!
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-ok">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <?= e($success) ?>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-err">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= e($error) ?>
        </div>
        <?php endif; ?>

        <!-- TABS -->
        <div class="tabs">
            <div class="tab active" id="tab-stats"    onclick="showTab('stats')">Статистика</div>
            <div class="tab"        id="tab-settings" onclick="showTab('settings')">Настройки сайта</div>
            <div class="tab"        id="tab-password" onclick="showTab('password')">Смена пароля</div>
        </div>

        <!-- ── STATS ─────────────────────────────────────────── -->
        <div class="tab-panel active" id="panel-stats">

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Визиты сегодня</div>
                    <div class="stat-value gold"><?= $todayVisits ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">За 7 дней</div>
                    <div class="stat-value"><?= $weekVisits ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Всего визитов</div>
                    <div class="stat-value"><?= $totalVisits ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Уникальных IP</div>
                    <div class="stat-value"><?= $uniqueAll ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Кликов по кнопкам</div>
                    <div class="stat-value"><?= $totalClicks ?></div>
                </div>
            </div>

            <!-- График посещений -->
            <div class="chart-card">
                <div class="chart-title">Посещения за 14 дней</div>
                <div class="chart-bars">
                    <?php foreach ($chartData as $d): ?>
                    <?php $h = $chartMax > 0 ? round(($d['count'] / $chartMax) * 100) : 0; ?>
                    <div class="chart-bar-wrap" title="<?= e($d['date']) ?>: <?= $d['count'] ?> визитов">
                        <div class="chart-bar" style="height: <?= $h ?>px; min-height: <?= $d['count'] > 0 ? 4 : 2 ?>px;"></div>
                        <div class="chart-label"><?= e($d['label']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Клики по кнопкам -->
            <div class="section-title">Клики по кнопкам</div>
            <div class="click-grid">
                <?php for ($i = 0; $i < 3; $i++): ?>
                <div class="click-card">
                    <div class="click-btn-name">
                        <?= e($buttons[$i]['text'] ?: "Кнопка " . ($i + 1)) ?>
                    </div>
                    <div class="click-count"><?= $clicksByBtn[$i] ?? 0 ?></div>
                    <div class="click-label">кликов всего</div>
                </div>
                <?php endfor; ?>
            </div>

        </div><!-- /panel-stats -->

        <!-- ── SETTINGS ──────────────────────────────────────── -->
        <div class="tab-panel" id="panel-settings">
            <?php
            $langNames = ['ru' => 'РУ', 'en' => 'EN', 'ka' => 'KA', 'tr' => 'TR'];
            ?>
            <form method="post" action="/admin/save.php">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="settings">

                <!-- Языковые вкладки -->
                <div class="lang-tabs">
                    <?php foreach ($langNames as $lc => $ln): ?>
                    <button type="button" class="lang-tab <?= $lc === 'ru' ? 'active' : '' ?>"
                            onclick="switchLang('<?= $lc ?>')"><?= $ln ?></button>
                    <?php endforeach; ?>
                </div>

                <!-- Контент для каждого языка -->
                <?php foreach ($langNames as $lc => $ln):
                    $p  = $lc === 'ru' ? '' : "{$lc}_";
                    $lt = cfgLang('site_title', $lc);
                    $ls = cfgLang('slogan', $lc);
                ?>
                <div class="lang-panel <?= $lc === 'ru' ? 'active' : '' ?>" id="lang-<?= $lc ?>">
                    <div class="form-card" style="margin-bottom:16px;">
                        <div class="section-title" style="margin-bottom:16px;">
                            Текст на <?= $ln ?> <?= $lc !== 'ru' ? '<span style="font-size:11px;color:var(--text-m);font-weight:400">(если пусто — используется русский)</span>' : '' ?>
                        </div>
                        <div class="form-grid">
                            <div class="field full">
                                <label>Название сайта</label>
                                <input type="text" name="<?= $p ?>site_title" value="<?= e($lt) ?>" maxlength="120">
                            </div>
                            <div class="field full">
                                <label>Лозунг</label>
                                <textarea name="<?= $p ?>slogan" maxlength="400"><?= e($ls) ?></textarea>
                            </div>
                        </div>
                        <?php for ($i = 0; $i < 3; $i++):
                            $bt = cfgLang("button_{$i}_text", $lc);
                        ?>
                        <div style="margin-top:16px;">
                            <div class="field">
                                <label>Кнопка <?= $i+1 ?> — текст</label>
                                <input type="text" name="<?= $p ?>button_<?= $i ?>_text" value="<?= e($bt) ?>" maxlength="80">
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Общие настройки (URL, позиция лозунга) — один раз -->
                <div class="form-card" style="margin-bottom:24px;">
                    <div class="section-title" style="margin-bottom:16px;">Ссылки и отображение</div>
                    <div class="form-grid">
                        <div class="field full">
                            <label>Позиция лозунга</label>
                            <select name="slogan_position">
                                <option value="above" <?= $sloganPos === 'above' ? 'selected' : '' ?>>Над кнопками</option>
                                <option value="below" <?= $sloganPos === 'below' ? 'selected' : '' ?>>Под кнопками</option>
                            </select>
                        </div>
                    </div>
                    <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="btn-section" style="margin-top:16px;">
                        <div class="btn-section-header">
                            <span>Кнопка <?= $i + 1 ?></span>
                            <label class="toggle" title="Включить/выключить">
                                <input type="checkbox" name="button_<?= $i ?>_enabled" value="1" <?= $buttons[$i]['enabled'] === '1' ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="field">
                            <label>URL (один для всех языков)</label>
                            <input type="url" name="button_<?= $i ?>_url" value="<?= e($buttons[$i]['url']) ?>" maxlength="500" placeholder="https://">
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn-primary">Сохранить все языки</button>
                    <a href="/" target="_blank" class="btn-secondary">Посмотреть сайт</a>
                </div>
            </form>
        </div><!-- /panel-settings -->

        <!-- ── PASSWORD ──────────────────────────────────────── -->
        <div class="tab-panel" id="panel-password">
            <div class="form-card" style="max-width: 480px;">
                <div class="section-title">Смена пароля администратора</div>
                <form method="post" action="/admin/save.php">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="password">
                    <div class="field" style="margin-bottom:16px;">
                        <label>Текущий пароль</label>
                        <input type="password" name="current_password" autocomplete="current-password">
                    </div>
                    <div class="field" style="margin-bottom:16px;">
                        <label>Новый пароль (минимум 10 символов)</label>
                        <input type="password" name="new_password" autocomplete="new-password">
                    </div>
                    <div class="field" style="margin-bottom:24px;">
                        <label>Повторите новый пароль</label>
                        <input type="password" name="new_password2" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn-primary">Сменить пароль</button>
                </form>
            </div>
        </div><!-- /panel-password -->

    </main>
</div>

<script>
function showTab(name) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    document.getElementById('panel-' + name).classList.add('active');
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
}

function switchLang(lc) {
    document.querySelectorAll('.lang-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.lang-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.lang-tab').forEach(t => {
        if (t.textContent.trim().toLowerCase() === lc || t.getAttribute('onclick').includes("'" + lc + "'")) {
            t.classList.add('active');
        }
    });
    document.getElementById('lang-' + lc).classList.add('active');
}
</script>

</body>
</html>
