<?php
/**
 * admin/save.php — обработчик сохранения настроек
 * Только POST, только для авторизованных
 */
session_start();
require_once __DIR__ . '/../_init.php';

// Только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/');
    exit;
}

// Проверка авторизации
requireAdmin();

// Проверка CSRF
csrfCheck();

// Rate limit: 30 изменений в минуту
if (!rateLimit('admin_save', 30, 60)) {
    $_SESSION['flash_err'] = 'Слишком много запросов. Подождите минуту.';
    header('Location: /admin/');
    exit;
}

$action = $_POST['action'] ?? '';

// ─── Сохранение настроек сайта ───────────────────────────────────────────────
if ($action === 'settings') {

    $sloganPos = in_array($_POST['slogan_position'] ?? '', ['above', 'below']) ? $_POST['slogan_position'] : 'above';
    setCfg('slogan_position', $sloganPos);

    // Сохраняем контент для каждого языка
    foreach (SUPPORTED_LANGS as $l) {
        $prefix = $l === 'ru' ? '' : "{$l}_";

        $siteTitle = trim($_POST["{$prefix}site_title"] ?? '');
        $slogan    = trim($_POST["{$prefix}slogan"]     ?? '');

        if (mb_strlen($siteTitle) > 120) $siteTitle = mb_substr($siteTitle, 0, 120);
        if (mb_strlen($slogan)    > 400) $slogan    = mb_substr($slogan, 0, 400);

        setCfg("{$prefix}site_title", $siteTitle);
        setCfg("{$prefix}slogan",     $slogan);

        for ($i = 0; $i < 3; $i++) {
            $text = trim($_POST["{$prefix}button_{$i}_text"] ?? '');
            if (mb_strlen($text) > 80) $text = mb_substr($text, 0, 80);
            setCfg("{$prefix}button_{$i}_text", $text);
        }
    }

    // URL и включение — общие для всех языков
    for ($i = 0; $i < 3; $i++) {
        $url     = trim($_POST["button_{$i}_url"] ?? '');
        $enabled = isset($_POST["button_{$i}_enabled"]) ? '1' : '0';

        if (strlen($url) > 500) $url = '';
        if ($url !== '' && !preg_match('#^https?://#i', $url)) $url = '';
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) $url = '';

        setCfg("button_{$i}_url",     $url);
        setCfg("button_{$i}_enabled", $enabled);
    }

    $_SESSION['flash_ok'] = 'Настройки сохранены.';
    header('Location: /admin/');
    exit;
}

// ─── Смена пароля ────────────────────────────────────────────────────────────
if ($action === 'password') {

    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password']     ?? '';
    $new2     = $_POST['new_password2']    ?? '';

    // Базовые проверки
    if (!password_verify($current, cfg('admin_password'))) {
        $_SESSION['flash_err'] = 'Текущий пароль неверный.';
        header('Location: /admin/');
        exit;
    }

    if (strlen($new) < 10) {
        $_SESSION['flash_err'] = 'Новый пароль должен быть не менее 10 символов.';
        header('Location: /admin/');
        exit;
    }

    if ($new !== $new2) {
        $_SESSION['flash_err'] = 'Пароли не совпадают.';
        header('Location: /admin/');
        exit;
    }

    if (strlen($new) > 200) {
        $_SESSION['flash_err'] = 'Пароль слишком длинный.';
        header('Location: /admin/');
        exit;
    }

    $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
    setCfg('admin_password', $hash);

    // Удаляем файл с временным паролем если есть
    if (file_exists(SETUP_FILE)) {
        unlink(SETUP_FILE);
    }

    // Инвалидируем текущую сессию и перелогиниваем
    session_regenerate_id(true);
    $_SESSION['admin']       = true;
    $_SESSION['admin_ip']    = getClientIP();
    $_SESSION['admin_ua']    = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200);
    $_SESSION['admin_since'] = time();
    $_SESSION['flash_ok'] = 'Пароль успешно изменён. Файл SETUP_PASSWORD.txt удалён.';
    header('Location: /admin/');
    exit;
}

// Если action неизвестен
header('Location: /admin/');
exit;
