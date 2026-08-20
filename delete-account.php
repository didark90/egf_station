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

$currentPassword = $_POST['current_password'] ?? '';

$result = deleteCurrentUserAccount( (string) $currentPassword );

if ($result['success'])
{
    header('Location: index.php?account_deleted=1');
    exit;
}

header('Location: account.php?delete_account=failed');
exit;