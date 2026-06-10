<?php
require __DIR__ . '/lib.php';

initEnv();

$token = $_GET['token'] ?? '';
$secret = cfg()['cron_token'] ?? '';

if ($secret === '' || !hash_equals($secret, $token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden";
    exit;
}

$res = cleanupOldFiles();

header('Content-Type: text/plain; charset=utf-8');
echo "OK\n";
echo "TXT deleted: {$res['txt']}\n";
echo "IMG deleted: {$res['img']}\n";
echo "JSON deleted: {$res['json']}\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";