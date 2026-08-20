<?php

declare(strict_types=1);
require __DIR__ . '/config.php';

getCurrentLocale();
startAppSession();

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = loginUser( (string) $username, (string) $password );

    if ($result['success'])
    {
        header('Location: upload.php');
        exit;
    }

    $message = $result['message'];
    $messageType = 'error';
}

?>
<!DOCTYPE html>
<html <?= getHtmlLanguageAttributes() ?>>
<head>
    <meta charset="utf-8">
    <title><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> - <?= et('login') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="./style.css">
</head>

<body>
<main class="card">
    <?php renderLanguageSelector(); ?>
    <p><a href="index.php"><?= et('back_home') ?></a></p>
    <h1><?= et('login') ?></h1>

    <?php if ($message !== ''): ?>
        <div class="notice <?= e($messageType) ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
        <label for="username"><?= et('username') ?></label>
        <input type="text" id="username" name="username" required>

        <label for="password"><?= et('password') ?></label>
        <input type="password" id="password" name="password" required>

        <div class="form-actions-row">
            <button type="submit"><?= et('login') ?></button>
            <a href="forgot-password.php"><?= et('forgot_password') ?></a>
        </div>
    </form>

    <?php if (REGISTRATION_ENABLED): ?>
        <p class="muted"><?= et('no_account_yet') ?> <a href="register.php"><?= et('create_account') ?></a>.</p>
    <?php endif; ?>
</main>

</body>
</html>