<?php

declare(strict_types=1);
require __DIR__ . '/config.php';

getCurrentLocale();

$files          = getUploadedEgfFiles();
$currentUser    = isLoggedIn() ? getCurrentUser() : null;
$csrfToken      = getCsrfToken();

$deleteStatus = $_GET['delete'] ?? '';

?>

<!DOCTYPE html>
<html <?= getHtmlLanguageAttributes() ?>>
<head>
    <meta charset="utf-8">
    <title><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> - <?= et('available_games') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="./style.css">
</head>

<body>
<main class="card wide">
    <?php renderLanguageSelector(); ?>
    <p><a href="index.php"><?= et('back_home') ?></a></p>
    <h1><?= et('available_games') ?></h1>

    <?php if ($deleteStatus === 'success'): ?>
        <div class="notice success"><?= et('game_deleted_success') ?></div>
    <?php elseif ($deleteStatus === 'failed'): ?>
        <div class="notice error"><?= et('game_delete_failed') ?></div>
    <?php endif; ?>

    <?php if (count($files) === 0): ?>
        <p class="muted"><?= et('no_games') ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?= et('icon') ?></th>
                    <th><?= et('name') ?></th>
                    <th><?= et('creator') ?></th>
                    <th><?= et('description') ?></th>
                    <th><?= et('identifier') ?></th>
                    <th><?= et('modified') ?></th>
                    <th><?= et('size') ?></th>
                    <th><?= et('uploaded_by') ?></th>
                    <th><?= et('download') ?></th>
                    <th><?= et('play') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($files as $file): ?>
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
                    <td><?= e($file['game_creator']) ?></td>
                    <td class="description-cell"><?= e($file['game_description']) ?></td>
                    <td class="identifier-cell"><?= e($file['game_identifier']) ?></td>
                    <td><?= e($file['game_modified']) ?></td>
                    <td><?= e(humanFileSize( (int) $file['size']) ) ?></td>
                    <td>
                        <?php if ($file['uploaded_by_known']): ?>
                            <?= e($file['uploaded_by']) ?>
                        <?php else: ?>
                            <span class="user-anonymous"><?= et('anonymous_upload') ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="download.php?file=<?= rawurlencode($file['name']) ?>"><?= et('download') ?></a>
                    </td>
                    <td>
                        <a class="button button-secondary" href="play.php?file=<?= rawurlencode($file['name']) ?>" target="_blank" rel="noopener"><?= et('play') ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p class="actions-inline"><a class="button" href="upload.php"><?= et('upload_another_game') ?></a></p>
</main>

</body>
</html>
