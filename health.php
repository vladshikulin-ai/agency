<?php
/**
 * health.php — диагностика. Не использует _init.php / сессии / rate limit.
 * Открывать как https://x24.sx/health.php
 */
header('Content-Type: text/plain; charset=utf-8');
$t0 = microtime(true);

echo "=== PHP ===\n";
echo "version: " . PHP_VERSION . "\n";
echo "sapi: " . PHP_SAPI . "\n";
echo "time: " . date('Y-m-d H:i:s') . "\n";
echo "\n";

echo "=== Filesystem ===\n";
$data_dir = __DIR__ . '/data/';
$db_path = $data_dir . 'site.db';
echo "cwd: " . __DIR__ . "\n";
echo "data/ exists: " . (is_dir($data_dir) ? 'yes' : 'NO') . "\n";
echo "data/ writable: " . (is_writable($data_dir) ? 'yes' : 'NO') . "\n";
echo "db exists: " . (file_exists($db_path) ? 'yes' : 'NO') . "\n";
if (file_exists($db_path)) {
    echo "db size: " . number_format(filesize($db_path)) . " bytes\n";
    echo "db writable: " . (is_writable($db_path) ? 'yes' : 'NO') . "\n";
    // WAL / journal files
    foreach (['-wal', '-journal', '-shm'] as $ext) {
        $f = $db_path . $ext;
        if (file_exists($f)) echo "db{$ext}: " . number_format(filesize($f)) . " bytes\n";
    }
}
echo "\n";

if (file_exists($db_path)) {
    echo "=== DB tables ===\n";
    try {
        $t = microtime(true);
        $db = new PDO('sqlite:' . $db_path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA busy_timeout=3000');
        echo "connect: " . round((microtime(true) - $t) * 1000, 1) . "ms\n";

        $tables = ['config', 'visitors', 'clicks', 'rate_limits'];
        foreach ($tables as $tb) {
            try {
                $t = microtime(true);
                $n = (int)$db->query("SELECT COUNT(*) FROM $tb")->fetchColumn();
                $ms = round((microtime(true) - $t) * 1000, 1);
                echo "$tb: " . number_format($n) . " rows ({$ms}ms)" . ($ms > 500 ? ' ← SLOW' : '') . "\n";
            } catch (Throwable $e) {
                echo "$tb: ERROR " . $e->getMessage() . "\n";
            }
        }

        // Индексы
        echo "\n=== Indexes ===\n";
        $idx = $db->query("SELECT name, tbl_name FROM sqlite_master WHERE type='index' ORDER BY tbl_name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($idx as $i) echo "{$i['tbl_name']}.{$i['name']}\n";
    } catch (Throwable $e) {
        echo "DB ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Request ===\n";
echo "HTTPS: " . ($_SERVER['HTTPS'] ?? 'off') . "\n";
echo "X-Forwarded-Proto: " . ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '-') . "\n";
echo "CF-Connecting-IP: " . ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '-') . "\n";
echo "REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? '-') . "\n";

echo "\ntotal: " . round((microtime(true) - $t0) * 1000, 1) . "ms\n";
