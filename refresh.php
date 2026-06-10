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

$items = refreshAll();

header('Content-Type: text/plain; charset=utf-8');
echo "OK\n";
echo "Items: " . count($items) . "\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";