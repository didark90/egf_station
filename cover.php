<?php

declare(strict_types=1);
require __DIR__ . "/config.php";

ensureUploadDirectory();

$requested  = $_GET['file'] ?? '';
$filename   = basename( (string) $requested);
$extension  = strtolower( pathinfo($filename, PATHINFO_EXTENSION) );

if ($filename === '' || $extension !== "egf")
{
    http_response_code(400);
    exit(t('invalid_file_request'));
}

$path       = realpath(UPLOAD_DIR . '/' . $filename);
$uploadRoot = realpath(UPLOAD_DIR);

if ($path === false || $uploadRoot === false || strncmp($path, $uploadRoot, strlen($uploadRoot) ) !== 0 || !is_file($path) )
{
    http_response_code(404);
    exit(t('file_not_found'));
}

if (!class_exists('ZipArchive') )
{
    http_response_code(500);
    exit(t('ziparchive_unavailable'));
}

$metadata = extractEgfGameMetadata($path);

if ($metadata['cover_path'] === null || $metadata['cover_media_type'] === null)
{
    http_response_code(404);
    exit(t('cover_not_found'));
}

$zip = new ZipArchive();

if ($zip->open($path) !== true)
{
    http_response_code(404);
    exit(t('could_not_open_egf_file'));
}

try
{
    $stat = $zip->statName($metadata['cover_path']);

    if (!is_array($stat) || (int) ($stat['size'] ?? 0) <= 0 || (int) ($stat['size'] ?? 0) > 5 * 1024 * 1024)
    {
        http_response_code(404);
        exit(t('invalid_cover'));
    }

    $cover = $zip->getFromName($metadata['cover_path']);

    if ( !is_string($cover) )
    {
        http_response_code(404);
        exit(t('cover_not_found'));
    }

    header('Content-Type: ' . $metadata['cover_media_type']);
    header( 'Content-Length: ' . strlen($cover) );
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');

    echo $cover;
}
finally
{
    $zip->close();
}

exit;
