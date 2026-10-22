<?php
declare(strict_types=1);

require __DIR__ . '/inc/layout.php';

use UMP\Core\Auth;
use UMP\Repositories\Attachments;

Auth::require(); // any signed-in UMP user may view documents

$row = Attachments::find((int) input('id'));
$path = $row ? Attachments::path($row) : '';
if (!$row || !is_file($path)) {
    http_response_code(404);
    exit('Not found');
}

$inline = input('download') === '';
header('Content-Type: ' . $row['mime']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($row['original_name']) . '"; filename*=UTF-8\'\'' . rawurlencode($row['original_name']));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");
header('Cache-Control: private, max-age=300');
readfile($path);
