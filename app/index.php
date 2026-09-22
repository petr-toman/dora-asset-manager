<?php
require_once __DIR__ . '/db.php';
db();
ob_start();
require __DIR__ . '/views/main.php';
$html = ob_get_clean();
echo str_replace('</body>', '<script src="/assets/export-map.js"></script></body>', $html);
