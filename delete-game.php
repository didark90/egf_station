<?php

declare(strict_types=1);
require __DIR__ . '/config.php';

startAppSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    http_response_code(405);
    exit(t('method_not_allowed'));
}

$csrfToken = $_POST['csrf_token'] ?? '';

if (!is_string($csrfToken) || !verifyCsrfToken($csrfToken))
{
    http_response_code(403);
    exit(t('invalid_security_token'));
}

$filename = $_POST['file'] ?? '';

if (!is_string($filename) || $filename === '')
{
    header('Location: account.php?delete=failed');
    exit;
}

$result = deleteUploadedEgfFile($filename);

if ($result['success'])
{
    header('Location: account.php?delete=success');
    exit;
}

header('Location: account.php?delete=failed');
exit;
