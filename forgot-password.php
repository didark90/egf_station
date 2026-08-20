<?php

declare(strict_types=1);
require __DIR__ . '/config.php';

getCurrentLocale();

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $email = $_POST['email'] ?? '';

    $result = requestPasswordReset( (string) $email );

    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

?>
<!DOCTYPE html>
<html <?= getHtmlLanguageAttributes() ?>>
<head>
    <meta charset="utf-8">
    <title><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> - <?= et('reset_password') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="./style.css">
</head>

<body>
<main class="card">
    <?php renderLanguageSelector(); ?>
    <p><a href="login.php"><?= et('back_login') ?></a></p>
    <h1><?= et('reset_password') ?></h1>

    <?php if ($message !== ''): ?>
        <div class="notice <?= e($messageType) ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
        <label for="email"><?= et('account_email_address') ?></label>
        <input type="email" id="email" name="email" required>

        <button type="submit"><?= et('send_reset_link') ?></button>
    </form>
</main>

</body>
</html>