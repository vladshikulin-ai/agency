<?php
/**
 * track.php — AJAX endpoint для трекинга кликов по кнопкам
 * Принимает только POST
 */
session_start();
require_once __DIR__ . '/_init.php';

header('Content-Type: application/json');
header('X-Robots-Tag: noindex');

// Только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('{}');
}

// Rate limit: 60 трек-запросов в минуту с одного IP
if (!rateLimit('track', 60, 60)) {
    http_response_code(429);
    die('{}');
}

// CSRF (проверяем токен из сессии)
$token = $_POST['csrf'] ?? '';
if (!hash_equals(csrfToken(), $token)) {
    http_response_code(403);
    die('{}');
}

// Валидация номера кнопки
$btnIndex = filter_input(INPUT_POST, 'btn', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0, 'max_range' => 2]
]);
if ($btnIndex === false || $btnIndex === null) {
    http_response_code(400);
    die('{}');
}

// Записываем клик
$ipHash = hashIP(getClientIP());
getDB()->prepare("INSERT INTO clicks (button_index, ip_hash, timestamp) VALUES (?, ?, ?)")
       ->execute([$btnIndex, $ipHash, time()]);

echo '{"ok":true}';
