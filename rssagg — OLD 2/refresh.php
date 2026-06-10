<?php
require __DIR__ . '/lib.php';

initEnv();

$items = refreshAll();

header('Content-Type: text/plain; charset=utf-8');
echo "OK\n";
echo "Items: " . count($items) . "\n";