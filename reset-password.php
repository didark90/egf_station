<?php

declare(strict_types=1);
require __DIR__ . '/config.php';

getCurrentLocale();

$token = $_GET['token'] ?? $_POST['token'] ?? '';

$message = '';
$messageType = 'info';
$passwordUpdated = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $newPassword = $_POST['new_password'] ?? '';
    $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

    if ($newPassword !== $newPasswordConfirm)
    {
        $message = t('passwords_do_not_match');
        $messageType = 'error';
    }
    else
    {
        $result = resetPasswordWithToken( (string) $token, (string) $newPassword );

        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        $passwordUpdated = (bool) $result['success'];
    }
}

?>
<!DOCTYPE html>
<html <?= getHtmlLanguageAttributes() ?>>
<head>
    <meta charset="utf-8">
    <title><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> - <?= et('choose_new_password') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="./style.css">
</head>

<body>
<main class="card">
    <?php renderLanguageSelector(); ?>
    <p><a href="login.php"><?= et('back_login') ?></a></p>
    <h1><?= et('choose_new_password') ?></h1>

    <?php if ($message !== ''): ?>
        <div class="notice <?= e($messageType) ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($passwordUpdated): ?>
        <p><a class="button" href="login.php"><?= et('login') ?></a></p>
    <?php elseif (!is_string($token) || $token === ''): ?>
        <div class="notice error"><?= et('missing_password_reset_token') ?></div>
    <?php else: ?>
        <form method="post" class="form">
            <input type="hidden" name="token" value="<?= e( (string) $token ) ?>">

            <label for="new_password"><?= et('new_password') ?></label>
            <input
                type="password"
                id="new_password"
                name="new_password"
                required
                minlength="8"
            >

            <label for="new_password_confirm"><?= et('confirm_new_password') ?></label>
            <input
                type="password"
                id="new_password_confirm"
                name="new_password_confirm"
                required
                minlength="8"
            >

            <button type="submit"><?= et('update_password') ?></button>
        </form>
    <?php endif; ?>
</main>

</body>
</html>