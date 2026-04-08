<?php
/**
 * _init.php — общие функции, БД, безопасность
 * Не открывать напрямую — защищено .htaccess
 */

define('DATA_DIR', __DIR__ . '/data/');
define('DB_PATH', DATA_DIR . 'site.db');
define('SETUP_FILE', DATA_DIR . 'SETUP_PASSWORD.txt');
define('INIT_LOADED', true);

// ─── PDO / SQLite ────────────────────────────────────────────────────────────

function getDB(): PDO {
    static $db = null;
    if ($db === null) {
        if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0750, true);
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('PRAGMA synchronous=NORMAL');
        $db->exec('PRAGMA foreign_keys=ON');
        initDB($db);
    }
    return $db;
}

function initDB(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS config (
        key   TEXT PRIMARY KEY,
        value TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS visitors (
        id        INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_hash   TEXT NOT NULL,
        date      TEXT NOT NULL,
        timestamp INTEGER NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS clicks (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        button_index INTEGER NOT NULL,
        ip_hash      TEXT NOT NULL,
        timestamp    INTEGER NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
        id        INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_hash   TEXT NOT NULL,
        action    TEXT NOT NULL,
        timestamp INTEGER NOT NULL
    )");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_vis_date   ON visitors(date)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_vis_ip     ON visitors(ip_hash, date)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_clk_btn    ON clicks(button_index)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_rl         ON rate_limits(ip_hash, action, timestamp)");

    // Первый запуск — заполнить конфиг дефолтами
    if ((int)$db->query("SELECT COUNT(*) FROM config")->fetchColumn() === 0) {
        $password = bin2hex(random_bytes(8));
        $hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $defaults = [
            'admin_password'  => $hash,
            'site_title'      => 'КадрПро — Кадровое Агентство',
            'slogan'          => "Ваш успех — наша работа.\nНайдите лучших специалистов или работу мечты.",
            'slogan_position' => 'above',
            'button_0_text'   => 'Найти работу',
            'button_0_url'    => 'https://example.com',
            'button_0_enabled'=> '1',
            'button_1_text'   => 'Разместить вакансию',
            'button_1_url'    => 'https://example.com',
            'button_1_enabled'=> '1',
            'button_2_text'   => 'О нас',
            'button_2_url'    => 'https://example.com',
            'button_2_enabled'=> '1',
        ];

        $stmt = $db->prepare("INSERT INTO config (key, value) VALUES (?, ?)");
        foreach ($defaults as $k => $v) $stmt->execute([$k, $v]);

        file_put_contents(SETUP_FILE,
            "=== ПЕРВЫЙ ЗАПУСК ===\n" .
            "Пароль администратора: $password\n" .
            "URL панели: /admin/\n\n" .
            "После первого входа смените пароль и удалите этот файл!\n"
        );
    }

    // Переводы — INSERT OR IGNORE (не перезаписывают уже сохранённые значения)
    $translations = [
        // English
        'en_site_title'    => 'KadrPro — HR Agency',
        'en_slogan'        => "Your success is our work.\nFind the best specialists or your dream job.",
        'en_button_0_text' => 'Find a Job',
        'en_button_1_text' => 'Post a Vacancy',
        'en_button_2_text' => 'About Us',
        // Georgian
        'ge_site_title'    => 'KadrPro — საკადრო სააგენტო',
        'ge_slogan'        => "თქვენი წარმატება — ჩვენი საქმეა.\nიპოვეთ საუკეთესო სპეციალისტები ან სამოცნაბო სამუშაო.",
        'ge_button_0_text' => 'სამუშაოს პოვნა',
        'ge_button_1_text' => 'ვაკანსიის განთავსება',
        'ge_button_2_text' => 'ჩვენ შესახებ',
        // Turkish
        'tr_site_title'    => 'KadrPro — İnsan Kaynakları Ajansı',
        'tr_slogan'        => "Başarınız bizim işimiz.\nEn iyi uzmanları veya hayalinizdeki işi bulun.",
        'tr_button_0_text' => 'İş Bul',
        'tr_button_1_text' => 'İlan Ver',
        'tr_button_2_text' => 'Hakkımızda',
    ];
    $stmt = $db->prepare("INSERT OR IGNORE INTO config (key, value) VALUES (?, ?)");
    foreach ($translations as $k => $v) $stmt->execute([$k, $v]);
}

// ─── Config ──────────────────────────────────────────────────────────────────

function cfg(string $key, string $default = ''): string {
    $stmt = getDB()->prepare("SELECT value FROM config WHERE key = ?");
    $stmt->execute([$key]);
    $r = $stmt->fetchColumn();
    return $r !== false ? $r : $default;
}

function setCfg(string $key, string $value): void {
    $stmt = getDB()->prepare("INSERT OR REPLACE INTO config (key, value) VALUES (?, ?)");
    $stmt->execute([$key, $value]);
}

// ─── IP / Privacy ────────────────────────────────────────────────────────────

function getClientIP(): string {
    // Cloudflare передаёт реальный IP в этом заголовке
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    // Валидация
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function hashIP(string $ip): string {
    // Соль + месяц — GDPR-совместимо (данные обезличены)
    return hash('sha256', 'v1_' . date('Y-m') . '_' . $ip);
}

function hashIPRaw(string $ip): string {
    // Без ротации — для rate limiting
    return hash('sha256', 'rl_' . $ip);
}

// ─── Rate Limiting ───────────────────────────────────────────────────────────

/**
 * Возвращает false если лимит превышен, true если всё ок.
 * $max  запросов за $window секунд.
 */
function rateLimit(string $action, int $max, int $window): bool {
    $db     = getDB();
    $ip     = getClientIP();
    $ipHash = hashIPRaw($ip);
    $now    = time();
    $since  = $now - $window;

    // Чистим старые записи (чтобы база не росла)
    $db->prepare("DELETE FROM rate_limits WHERE action = ? AND timestamp < ?")
       ->execute([$action, $since]);

    // Считаем запросы за окно
    $stmt = $db->prepare("SELECT COUNT(*) FROM rate_limits WHERE ip_hash = ? AND action = ? AND timestamp > ?");
    $stmt->execute([$ipHash, $action, $since]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= $max) return false;

    $db->prepare("INSERT INTO rate_limits (ip_hash, action, timestamp) VALUES (?, ?, ?)")
       ->execute([$ipHash, $action, $now]);

    return true;
}

function rateLimitOrDie(string $action, int $max, int $window): void {
    if (!rateLimit($action, $max, $window)) {
        http_response_code(429);
        header('Retry-After: ' . $window);
        header('Content-Type: application/json');
        die(json_encode(['error' => 'Too many requests']));
    }
}

// ─── Security Headers ────────────────────────────────────────────────────────

function secHeaders(): void {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'");
}

// ─── CSRF ────────────────────────────────────────────────────────────────────

function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfCheck(): void {
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('CSRF check failed');
    }
}

// ─── Admin Auth ──────────────────────────────────────────────────────────────

function isAdmin(): bool {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: /admin/');
        exit;
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─── Multilingual ─────────────────────────────────────────────────────────────

define('SUPPORTED_LANGS', ['ru', 'en', 'ge', 'tr']);

function detectLang(): string {
    // 1. Cookie (выбор пользователя)
    if (!empty($_COOKIE['lang']) && in_array($_COOKIE['lang'], SUPPORTED_LANGS, true)) {
        return $_COOKIE['lang'];
    }
    // 2. Accept-Language браузера
    $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    foreach (preg_split('/[,;]/', $accept) as $part) {
        $code = strtolower(substr(trim($part), 0, 2));
        if (in_array($code, SUPPORTED_LANGS, true)) {
            return $code;
        }
    }
    return 'ru';
}

/**
 * Получить конфиг с учётом языка.
 * Если перевод пустой — откат на русский.
 */
function cfgLang(string $key, string $lang, string $default = ''): string {
    if ($lang === 'ru') return cfg($key, $default);
    $val = cfg("{$lang}_{$key}", '');
    return $val !== '' ? $val : cfg($key, $default);
}
