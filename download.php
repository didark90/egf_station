<?php

declare(strict_types=1);
require __DIR__ . '/config.php';

ensureUploadDirectory();

$requested  = $_GET['file'] ?? '';
$filename   = basename( (string) $requested );
$extension  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$inline     = isset($_GET['inline']) || isset($_GET['play']);

if ($filename === '' || $extension !== "egf")
{
    http_response_code(400);

    exit(t('invalid_file_request'));
}

$path       = realpath(UPLOAD_DIR . '/' . $filename);
$uploadRoot = realpath(UPLOAD_DIR);

if ($path === false || $uploadRoot === false || strncmp($path, $uploadRoot, strlen($uploadRoot)) !== 0 || !is_file($path))
{
    http_response_code(404);
    
    exit(t('file_not_found'));
}

if ($inline)
{
    // Suitable for the Embedded EGF Reader (fetch / XHR)
    header('Content-Type: application/zip');
    header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
}
else
{
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
}

header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');

readfile($path);

exit;

?>
