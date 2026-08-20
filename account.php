<?php

declare(strict_types=1);
require __DIR__ . '/config.php';

getCurrentLocale();
startAppSession();

if (!isLoggedIn())
{
    header('Location: login.php');
    exit;
}

$currentUser    = getCurrentUser();
$currentEmail   = getCurrentUserEmail();

$csrfToken      = getCsrfToken();
$uploadedGames  = getUploadedEgfFilesByUser( (string) $currentUser);

$deleteStatus           = $_GET['delete'] ?? '';
$deleteAccountStatus    = $_GET['delete_account'] ?? '';

$message        = '';
$messageType    = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $action = $_POST['action'] ?? '';

    if ($action === 'change_email')
    {
        $newEmail = $_POST['new_email'] ?? '';
        $currentPassword = $_POST['current_password_for_email'] ?? '';

        $result = changeCurrentEmail( (string) $newEmail, (string) $currentPassword);

        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';

        $currentEmail = getCurrentUserEmail();
    }
    elseif ($action === 'change_password')
    {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

        if ($newPassword !== $newPasswordConfirm)
        {
            $message = t('new_passwords_do_not_match');
            $messageType = 'error';
        }
        else
        {
            $result = changeCurrentPassword( (string) $currentPassword, (string) $newPassword);

            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
        }
    }
    else
    {
        $message = t('invalid_account_action');
        $messageType = 'error';
    }
}

?>
<!DOCTYPE html>
<html <?= getHtmlLanguageAttributes() ?>>
<head>
    <meta charset="utf-8">
    <title><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> - <?= et('account_settings') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="./style.css">
</head>

<body>
<main class="card">
    <?php renderLanguageSelector(); ?>
    <p><a href="index.php"><?= et('back_home') ?></a></p>

    <h1><?= et('account_settings') ?></h1>

    <p class="muted">
        <?= et('logged_in_as', ['username' => (string) $currentUser]) ?>
        <a href="logout.php"><?= et('logout') ?></a>
    </p>

    <?php if ($currentEmail !== null): ?>
        <p class="muted"><?= et('email') ?>: <?= e($currentEmail) ?></p>
    <?php endif; ?>

    <?php if ($message !== ''): ?>
        <div class="notice <?= e($messageType) ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($deleteStatus === 'success'): ?>
    <div class="notice success"><?= et('game_deleted_success') ?></div>

    <?php elseif ($deleteStatus === 'failed'): ?>
    <div class="notice error"><?= et('game_delete_failed') ?></div>
<?php endif; ?>

<?php if ($deleteAccountStatus === 'failed'): ?>
    <div class="notice error"><?= et('account_delete_failed') ?></div>
<?php endif; ?>

    <form method="post" class="form">
        <h2><?= et('change_email') ?></h2>

        <input type="hidden" name="action" value="change_email">

        <label for="new_email"><?= et('new_email') ?></label>
        <input
            type="email"
            id="new_email"
            name="new_email"
            required
        >

        <label for="current_password_for_email"><?= et('current_password') ?></label>
        <input
            type="password"
            id="current_password_for_email"
            name="current_password_for_email"
            required
        >

        <button type="submit"><?= et('send_verification_email') ?></button>
    </form>

    <form method="post" class="form">
        <h2><?= et('change_password') ?></h2>

        <input type="hidden" name="action" value="change_password">

        <label for="current_password"><?= et('current_password') ?></label>
        <input
            type="password"
            id="current_password"
            name="current_password"
            required
        >

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

    <section class="account-section">
    <h2><?= et('uploaded_games') ?></h2>

    <?php if (count($uploadedGames) === 0): ?>
        <p class="muted"><?= et('no_uploaded_games') ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?= et('icon') ?></th>
                    <th><?= et('name') ?></th>
                    <th><?= et('identifier') ?></th>
                    <th><?= et('size') ?></th>
                    <th><?= et('download') ?></th>
                    <th><?= et('delete') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($uploadedGames as $file): ?>
                <tr>
                    <td>
                        <?php if ($file['has_cover']): ?>
                            <img
                                class="game-cover"
                                src="cover.php?file=<?= rawurlencode($file['name']) ?>"
                                alt=""
                                loading="lazy"
                            >
                        <?php else: ?>
                            <span class="game-cover game-cover-placeholder" aria-hidden="true">EGF</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($file['game_name']) ?></td>
                    <td class="identifier-cell"><?= e($file['game_identifier']) ?></td>
                    <td><?= e(humanFileSize( (int) $file['size']) ) ?></td>
                    <td>
                        <a href="download.php?file=<?= rawurlencode($file['name']) ?>"><?= et('download') ?></a>
                    </td>
                    <td>
                        <form method="post" action="delete-game.php" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="file" value="<?= e($file['name']) ?>">
                            <button
                                type="submit"
                                class="button-danger"
                                onclick="return confirm(<?= e(json_encode(t('delete_game_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>);"
                            >
                                <?= et('delete') ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="account-section danger-zone">
    <h2><?= et('delete_account') ?></h2>

    <p class="muted">
        <?= et('delete_account_warning') ?>
    </p>

    <form method="post" action="delete-account.php" class="form">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

        <label for="delete_account_password"><?= et('current_password') ?></label>
        <input
            type="password"
            id="delete_account_password"
            name="current_password"
            required
        >

        <button
            type="submit"
            class="button-danger"
            onclick="return confirm(<?= e(json_encode(t('delete_account_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>);"
        >
            <?= et('delete_my_account') ?>
        </button>
    </form>
</section>

</main>

</body>
</html>
