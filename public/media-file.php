<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin_if_enabled();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$asset = $id > 0 ? find_media_asset($id) : null;

if (!$asset || ($asset['kind'] ?? '') !== 'video') {
    http_response_code(404);
    echo 'Video not found';
    exit;
}

$path = (string)($asset['local_path'] ?? '');
if ($path === '' || !is_file($path)) {
    http_response_code(404);
    echo 'Video file missing on disk';
    exit;
}

$mime = (string)($asset['mime_type'] ?? 'video/mp4');
$size = filesize($path);

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)$size);
header('Content-Disposition: inline; filename="' . addslashes((string)($asset['original_name'] ?: basename($path))) . '"');
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: bytes');

readfile($path);
