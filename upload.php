<?php

declare(strict_types=1);
require __DIR__ . "/config.php";

getCurrentLocale();
ensureUploadDirectory();

$message        = "";
$messageType    = "info";

$uploadAccess = getUploadAccessStatus();
$canUpload = (bool) $uploadAccess['allowed'];

$currentUser = isLoggedIn() ? getCurrentUser() : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    if (!$canUpload)
    {
        $message = $uploadAccess['message'];
        $messageType = "error";
    }

    elseif ( !isset($_FILES["egf_file"]) )
    {
        $message        = t('no_file_submitted');
        $messageType    = "error";
    }
    
    elseif ($_FILES["egf_file"]["error"] !== UPLOAD_ERR_OK)
    {
        $message        = t('upload_failed_code', ['code' => (string) (int) $_FILES["egf_file"]["error"]]);
        $messageType    = "error";
    }
    
    else
    {
        $originalName   = $_FILES["egf_file"]["name"] ?? '';
        $temporaryPath  = $_FILES["egf_file"]["tmp_name"] ?? '';
        $size           = (int) ($_FILES["egf_file"]["size"] ?? 0);
        $extension      = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== "egf")
        {
            $message        = t('only_egf_allowed');
            $messageType    = "error";
        }
        
        elseif ($size <= 0)
        {
            $message        = t('uploaded_file_empty');
            $messageType    = "error";
        }
        
        elseif ($size > MAX_UPLOAD_BYTES)
        {
            $message            = t('upload_too_large', ['size' => humanFileSize(MAX_UPLOAD_BYTES)]);
            $messageType        = "error";
        }
        
        elseif ( !is_uploaded_file($temporaryPath) )
        {
            $message            = t('upload_not_verified');
            $messageType        = "error";
        }

        else
        {
            $validation = validateEgfPackage($temporaryPath);

            if (!$validation['valid'])
            {
                $displayedErrors = array_slice($validation['errors'], 0, 4);

                $message = t('invalid_egf_file', ['errors' => implode(' ', $displayedErrors)]);

                if (count($validation['errors']) > count($displayedErrors))
                {
                    $message .= ' ' . t('additional_validation_errors');
                }

                $messageType = "error";
            }
    
            else
            {
                $uploadedSha1 = sha1_file($temporaryPath);

                if ($uploadedSha1 === false)
                {
                    $message        = t('fingerprint_failed');
                    $messageType    = "error";
                }

                else
                {
                    $uploadedSha1 = strtolower($uploadedSha1);
                    $duplicateFile = findUploadedFileBySha1($uploadedSha1);

                    if ($duplicateFile !== null)
                    {
                        $message        = t('duplicate_egf_file');
                        $messageType    = "error";
                    }
            
                    else
                    {
                        $safeName       = uniqueUploadName($originalName);
                        $destination    = UPLOAD_DIR . '/' . $safeName;

                        if (move_uploaded_file($temporaryPath, $destination))
                        {
                            chmod($destination, 0644);
                            saveUploadedFileSha1($safeName, $uploadedSha1);

                            saveUploadMetadata($safeName, [
                                'uploaded_by' => $currentUser ?? '',
                                'uploaded_at' => gmdate('c'),
                                'sha1' => $uploadedSha1,
                            ]);

                            $message     = t('upload_success', ['filename' => $safeName]);
                            $messageType = "success";
                        }
                
                        else
                        {
                            $message     = t('upload_save_failed');
                            $messageType = "error";
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html <?= getHtmlLanguageAttributes() ?>>
<head>
    <meta charset="utf-8">
    <title><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> - <?= et('upload_an_egf_game') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="./style.css">
</head>

<body>
<main class="card">
    <?php renderLanguageSelector(); ?>
    <p><a href="index.php"><?= et('back_home') ?></a></p>
    <h1><?= et('upload_an_egf_game') ?></h1>

    <?php if ($currentUser !== null): ?>
    <p class="muted">
        <?= et('logged_in_as', ['username' => (string) $currentUser]) ?>
        <a href="account.php"><?= et('account_settings') ?></a>
        <a href="logout.php"><?= et('logout') ?></a>
    </p>
<?php elseif (REQUIRE_ACCOUNT_FOR_UPLOAD): ?>
    <p class="muted">
        <?= et('you_need_account_upload') ?>
        <a href="login.php"><?= et('login') ?></a>
        <?php if (REGISTRATION_ENABLED): ?>
            <?= et('or') ?> <a href="register.php"><?= et('create_account') ?></a>
        <?php endif; ?>.
    </p>
    <?php endif; ?>

   <?php if ($message !== ''): ?>
    <div class="notice <?= e($messageType) ?>"><?= e($message) ?></div>
<?php elseif (!$canUpload): ?>
    <div class="notice error"><?= e($uploadAccess['message']) ?></div>
<?php endif; ?>

<?php if ($canUpload): ?>
    <form method="post" enctype="multipart/form-data" class="form">
        <div class="notice warning">
            <?= et('rights_warning') ?>
        </div>
        <label for="egf_file"><?= et('choose_egf_file') ?></label>
        <input type="file" id="egf_file" name="egf_file" accept=".egf" required>
        <button type="submit"><?= et('upload_game') ?></button>
    </form>
<?php endif; ?>

    <p class="muted"><?= et('maximum_upload_size', ['size' => humanFileSize(MAX_UPLOAD_BYTES)]) ?></p>
</main>

</body>
</html>
