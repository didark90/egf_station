<?php

declare(strict_types=1);
require __DIR__ . '/config.php';

getCurrentLocale();
ensureUploadDirectory();

$uploadAccess   = getUploadAccessStatus();
$currentUser    = isLoggedIn() ? getCurrentUser() : null;
$accountDeleted = ($_GET['account_deleted'] ?? '') === '1';

?>

<!DOCTYPE html>
<html <?= getHtmlLanguageAttributes() ?>>
<head>
    <meta charset="utf-8">
    <title><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="./style.css">
</head>

<body>
<main class="card">
    <?php renderLanguageSelector(); ?>
    <h1>
        <?= e(APP_NAME) ?>
        <span class="version">v<?= e(APP_VERSION) ?></span>
    </h1>
    <p class="muted"><?= et('tagline') ?></p>
    
    <?php if ($accountDeleted): ?>
        <div class="notice success"><?= et('account_deleted') ?></div>
    <?php endif; ?>

    <?php if ($currentUser !== null): ?>
    <p class="muted">
        <?= et('logged_in_as', ['username' => (string) $currentUser]) ?>
        <a href="account.php"><?= et('account_settings') ?></a>
        <a href="logout.php"><?= et('logout') ?></a>
    </p>
    <?php else: ?>
    <p class="muted">
        <?php if (REQUIRE_ACCOUNT_FOR_UPLOAD): ?>
            <?= et('upload_requires_account') ?>
        <?php else: ?>
            <?= et('account_optional') ?>
        <?php endif; ?>

        <a href="login.php"><?= et('login') ?></a>

        <?php if (REGISTRATION_ENABLED): ?>
            <?= et('or') ?> <a href="register.php"><?= et('create_account') ?></a>
        <?php endif; ?>.
    </p>
    <?php endif; ?>

    <nav class="actions" aria-label="<?= et('main_actions') ?>">
        <?php if ($uploadAccess['allowed']): ?>
            <a class="button" href="upload.php"><?= et('upload_game') ?></a>
        <?php else: ?>
            <a class="button button-secondary" href="upload.php"><?= et('upload_game') ?></a>
        <?php endif; ?>
        <a class="button button-secondary" href="files.php"><?= et('display_all_games') ?></a>
    </nav>
</main>

</body>
</html>
