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
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if ($password !== $passwordConfirm)
    {
        $message = t('passwords_do_not_match');
        $messageType = 'error';
    }
    else
    {
        $result = createUserAccount( (string) $username, (string) $email, (string) $password );

        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';

        if ($result['success'] && !EMAIL_VERIFICATION_REQUIRED)
        {
            loginUser( (string) $username, (string) $password );
            header('Location: upload.php');
    
            exit;
        }
    }
}

?>
<!DOCTYPE html>
<html <?= getHtmlLanguageAttributes() ?>>
<head>
    <meta charset="utf-8">
    <title><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> - <?= et('create_account') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="./style.css">
</head>

<body>
<main class="card">
    <?php renderLanguageSelector(); ?>
    <p><a href="index.php"><?= et('back_home') ?></a></p>
    <h1><?= et('create_account') ?></h1>

    <?php if (!REGISTRATION_ENABLED): ?>
        <div class="notice error"><?= et('registration_disabled') ?></div>
    <?php else: ?>
        <?php if ($message !== ''): ?>
            <div class="notice <?= e($messageType) ?>"><?= e($message) ?></div>
        <?php endif; ?>

        <form method="post" class="form">
            <label for="username"><?= et('username') ?></label>
            <input type="text" id="username" name="username" required minlength="3" maxlength="32">

            <label for="email"><?= et('email') ?></label>
            <input type="email" id="email" name="email" required>

            <label for="password"><?= et('password') ?></label>
            <input type="password" id="password" name="password" required minlength="8">

            <label for="password_confirm"><?= et('confirm_password') ?></label>
            <input type="password" id="password_confirm" name="password_confirm" required minlength="8">

            <button type="submit"><?= et('create_account') ?></button>
        </form>

        <p class="muted"><?= et('already_have_account') ?> <a href="login.php"><?= et('login') ?></a>.</p>
    <?php endif; ?>
</main>

</body>
</html>