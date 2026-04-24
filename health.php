<?php
/**
 * health.php — защищённая диагностика. Требует токен.
 * Токен хранится в data/health_token.txt (создаётся при первом запуске
 * с запросом ?init=1, показывается один раз).
 */

$tok_file = __DIR__ . '/data/health_token.txt';
$given = $_GET['t'] ?? '';

// Первый запуск — сгенерить токен
if (!file_exists($tok_file) && isset($_GET['init']) && $_GET['init'] === '1') {
    if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0750, true);
    $tok = bin2hex(random_bytes(16));
    file_put_contents($tok_file, $tok);
    chmod($tok_file, 0600);
    header('Content-Type: text/plain; charset=utf-8');
    echo "=== TOKEN CREATED ===\n\nSave this URL — only shown once:\nhttps://" . ($_SERVER['HTTP_HOST'] ?? '') . "/health.php?t={$tok}\n\nDelete /data/health_token.txt to regenerate.\n";
    exit;
}

// Проверка токена
if (!file_exists($tok_file)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo "Health not initialized. Visit /health.php?init=1 to set up.\n";
    exit;
}
$stored = trim((string)file_get_contents($tok_file));
if (!$stored || !$given || !hash_equals($stored, $given)) {
    http_response_code(404);
    exit;
}

// Всё ок — рендерим диагностику
header('Content-Type: text/plain; charset=utf-8');
$t0 = microtime(true);

echo "=== PHP ===\n";
echo "version: " . PHP_VERSION . "\n";
echo "sapi: " . PHP_SAPI . "\n";
echo "time: " . date('Y-m-d H:i:s') . "\n\n";

echo "=== Filesystem ===\n";
$data_dir = __DIR__ . '/data/';
$db_path = $data_dir . 'site.db';
echo "data/ exists: " . (is_dir($data_dir) ? 'yes' : 'NO') . "\n";
echo "data/ writable: " . (is_writable($data_dir) ? 'yes' : 'NO') . "\n";
echo "db exists: " . (file_exists($db_path) ? 'yes' : 'NO') . "\n";
if (file_exists($db_path)) {
    echo "db size: " . number_format(filesize($db_path)) . " bytes\n";
    foreach (['-wal', '-journal', '-shm'] as $ext) {
        $f = $db_path . $ext;
        if (file_exists($f)) echo "db{$ext}: " . number_format(filesize($f)) . " bytes\n";
    }
}
echo "\n";

if (file_exists($db_path)) {
    echo "=== DB tables ===\n";
    try {
        $db = new PDO('sqlite:' . $db_path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA busy_timeout=3000');
        foreach (['config', 'visitors', 'clicks', 'rate_limits'] as $tb) {
            try {
                $t = microtime(true);
                $n = (int)$db->query("SELECT COUNT(*) FROM $tb")->fetchColumn();
                $ms = round((microtime(true) - $t) * 1000, 1);
                echo "$tb: " . number_format($n) . " rows ({$ms}ms)" . ($ms > 500 ? ' ← SLOW' : '') . "\n";
            } catch (Throwable $e) {
                echo "$tb: ERROR\n";
            }
        }
        echo "\n=== Indexes ===\n";
        $idx = $db->query("SELECT name, tbl_name FROM sqlite_master WHERE type='index' ORDER BY tbl_name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($idx as $i) echo "{$i['tbl_name']}.{$i['name']}\n";
    } catch (Throwable $e) {
        echo "DB ERROR\n";
    }
}

echo "\n=== Request ===\n";
echo "HTTPS: " . ($_SERVER['HTTPS'] ?? 'off') . "\n";
echo "X-Forwarded-Proto: " . ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '-') . "\n";
echo "CF-Connecting-IP: " . ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '-') . "\n";

echo "\ntotal: " . round((microtime(true) - $t0) * 1000, 1) . "ms\n";
