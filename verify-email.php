<?php

declare(strict_types=1);
require __DIR__ . '/config.php';

getCurrentLocale();

$message = '';
$messageType = 'info';

$token = $_GET['token'] ?? '';

if (!is_string($token) || $token === '')
{
    $message = t('missing_verification_token');
    $messageType = 'error';
}
else
{
    $result = verifyUserEmail($token);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

?>
<!DOCTYPE html>
<html <?= getHtmlLanguageAttributes() ?>>
<head>
    <meta charset="utf-8">
    <title><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> - <?= et('verify_email') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="./style.css">
</head>

<body>
<main class="card">
    <?php renderLanguageSelector(); ?>
    <p><a href="index.php"><?= et('back_home') ?></a></p>
    <h1><?= et('verify_email') ?></h1>

    <div class="notice <?= e($messageType) ?>"><?= e($message) ?></div>

    <?php if ($messageType === 'success'): ?>
        <p><a class="button" href="login.php"><?= et('login') ?></a></p>
    <?php else: ?>
        <p class="muted"><?= et('expired_account_link_help') ?></p>
    <?php endif; ?>
</main>

</body>
</html>