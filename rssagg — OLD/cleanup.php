<?php
require __DIR__ . '/lib.php';

initEnv();

$res = cleanupOldFiles();

header('Content-Type: text/plain; charset=utf-8');
echo "TXT deleted: {$res['txt']}\n";
echo "IMG deleted: {$res['img']}\n";
echo "JSON deleted: {$res['json']}\n";