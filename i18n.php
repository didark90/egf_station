<?php

declare(strict_types=1);

/*
 * EGF Station i18n helper.
 * 1. Put this file next to config.php.
 * 2. In config.php, require it near the end of the file, after e() is defined:
 *      require_once __DIR__ . '/i18n.php';
 * 3. Replace static strings with t('key') and add the language selector in pages.
 */

const DEFAULT_LOCALE        = 'en';
const FALLBACK_LOCALE       = 'fr';
const LOCALE_COOKIE_NAME    = 'egf_locale';

function getSupportedLocales(): array
{
    return [
        'en' => ['native_name' => 'English', 'dir' => 'ltr'],
        'fr' => ['native_name' => 'Français', 'dir' => 'ltr'],
        'es' => ['native_name' => 'Español', 'dir' => 'ltr'],
        'pt' => ['native_name' => 'Português', 'dir' => 'ltr'],
        'zh' => ['native_name' => '中文（普通话）', 'dir' => 'ltr'],
        'ar' => ['native_name' => 'العربية الفصحى', 'dir' => 'rtl'],
        'hi' => ['native_name' => 'हिन्दी', 'dir' => 'ltr'],
        'ur' => ['native_name' => 'اُردو', 'dir' => 'rtl'],
        'ru' => ['native_name' => 'Русский', 'dir' => 'ltr'],
    ];
}

function normalizeLocaleCode(string $locale): string
{
    $locale = strtolower(trim(str_replace('_', '-', $locale)));

    if ($locale === 'zh-cn' || $locale === 'zh-hans' || $locale === 'cmn' || $locale === 'cmn-hans')
    {
        return 'zh';
    }

    if ($locale === 'pt-br' || $locale === 'pt-pt')
    {
        return 'pt';
    }

    return substr($locale, 0, 2);
}

function getPreferredLocaleFromBrowser(): ?string
{
    $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

    if (!is_string($acceptLanguage) || trim($acceptLanguage) === '')
    {
        return null;
    }

    $preferredLocales = [];

    foreach (explode(',', $acceptLanguage) as $part)
    {
        $part = trim($part);

        if ($part === '')
        {
            continue;
        }

        $segments = explode(';', $part);
        $locale = normalizeLocaleCode($segments[0]);
        $quality = 1.0;

        if (isset($segments[1]) && preg_match('/q=([0-9.]+)/', $segments[1], $matches))
        {
            $quality = (float) $matches[1];
        }

        if (!isSupportedLocale($locale))
        {
            continue;
        }

        $preferredLocales[$locale] = max($preferredLocales[$locale] ?? 0, $quality);
    }

    if (count($preferredLocales) === 0)
    {
        return null;
    }

    arsort($preferredLocales);

    return array_key_first($preferredLocales);
}

function isSupportedLocale(string $locale): bool
{
    return array_key_exists(normalizeLocaleCode($locale), getSupportedLocales());
}

function getCurrentLocale(): string
{
    static $currentLocale = null;

    if ($currentLocale !== null)
    {
        return $currentLocale;
    }

    $candidate = $_GET['lang'] ?? null;

    if (is_string($candidate))
    {
        $candidate = normalizeLocaleCode($candidate);

        if (isSupportedLocale($candidate))
        {
            $currentLocale = $candidate;

            if (function_exists('startAppSession'))
            {
                startAppSession();
                $_SESSION['locale'] = $currentLocale;
            }

            setcookie(LOCALE_COOKIE_NAME, $currentLocale, [
                'expires' => time() + 365 * 24 * 60 * 60,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            return $currentLocale;
        }
    }

    if (function_exists('startAppSession'))
    {
        startAppSession();

        $sessionLocale = $_SESSION['locale'] ?? null;

        if (is_string($sessionLocale) && isSupportedLocale($sessionLocale))
        {
            $currentLocale = normalizeLocaleCode($sessionLocale);

            return $currentLocale;
        }
    }

    $cookieLocale = $_COOKIE[LOCALE_COOKIE_NAME] ?? null;

    if (is_string($cookieLocale) && isSupportedLocale($cookieLocale))
    {
        $currentLocale = normalizeLocaleCode($cookieLocale);

        return $currentLocale;
    }

    $browserLocale = getPreferredLocaleFromBrowser();

    if ($browserLocale !== null)
    {
        $currentLocale = $browserLocale;

        return $currentLocale;
    }

    $currentLocale = DEFAULT_LOCALE;

    return $currentLocale;
}

function getCurrentTextDirection(): string
{
    $locale = getCurrentLocale();
    $supported = getSupportedLocales();

    return $supported[$locale]['dir'] ?? 'ltr';
}

function getHtmlLanguageAttributes(): string
{
    return 'lang="' . e(getCurrentLocale()) . '" dir="' . e(getCurrentTextDirection()) . '"';
}

function t(string $key, array $params = []): string
{
    $translations   = getTranslations();
    $locale         = getCurrentLocale();

    $text = $translations[$locale][$key]
        ?? $translations[FALLBACK_LOCALE][$key]
        ?? $translations[DEFAULT_LOCALE][$key]
        ?? $key;

    foreach ($params as $name => $value)
    {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }

    return $text;
}

function et(string $key, array $params = []): string
{
    return e( t($key, $params) );
}

function renderLanguageSelector(): void
{
    $currentLocale      = getCurrentLocale();
    $locales            = getSupportedLocales();

    echo '<form class="language-switcher" method="get">';

    foreach ($_GET as $name => $value)
    {
        if ($name === 'lang' || is_array($value))
        {
            continue;
        }

        echo '<input type="hidden" name="' . e((string) $name) . '" value="' . e((string) $value) . '">';
    }

    echo '<label for="lang">' . et('language') . '</label>';
    echo '<select id="lang" name="lang" onchange="this.form.submit()">';

    foreach ($locales as $code => $info)
    {
        $selected = $code === $currentLocale ? ' selected' : '';
        echo '<option value="' . e($code) . '"' . $selected . '>' . e($info['native_name']) . '</option>';
    }

    echo '</select>';
    echo '<noscript><button type="submit">' . et('apply') . '</button></noscript>';
    echo '</form>';
}

function getTranslations(): array
{
    return [
        'en' => [
            'language' => 'Language',
            'apply' => 'Apply',
            'main_actions' => 'Main actions',

            'tagline' => 'A simple archive for sharing and downloading EGF games.',
            'back_home' => '← Back to home',
            'back_login' => '← Back to login',
            'logged_in_as' => 'Logged in as {username}.',
            'account_settings' => 'Account settings',
            'logout' => 'Log out',
            'login' => 'Log in',
            'create_account' => 'Create an account',
            'upload_requires_account' => 'Uploading requires an account.',
            'account_optional' => 'Account is optional.',
            'you_need_account_upload' => 'You need an account to upload EGF files.',
            'or' => 'or',

            'upload_game' => 'Upload a game',
            'upload_an_egf_game' => 'Upload an EGF game',
            'display_all_games' => 'Display all games',
            'available_games' => 'Available EGF games',
            'no_games' => 'No EGF games have been uploaded yet.',
            'upload_another_game' => 'Upload another game',
            'maximum_upload_size' => 'Maximum upload size: {size}.',
            'rights_warning' => 'Please ensure that you own the rights to the EGF game, or that it is distributed under a free license, before uploading it. Files that do not meet this requirement may be removed.',
            'choose_egf_file' => 'Choose an .egf file',

            'icon' => 'Icon',
            'name' => 'Name',
            'creator' => 'Creator',
            'description' => 'Description',
            'identifier' => 'Identifier',
            'modified' => 'Modified',
            'size' => 'Size',
            'uploaded_by' => 'Uploaded by',
            'download' => 'Download',
            'delete' => 'Delete',
            'anonymous_upload' => 'Anonymous upload',

            'email' => 'Email',
            'change_email' => 'Change email address',
            'new_email' => 'New email address',
            'current_password' => 'Current password',
            'send_verification_email' => 'Send verification email',
            'change_password' => 'Change password',
            'new_password' => 'New password',
            'confirm_new_password' => 'Confirm new password',
            'update_password' => 'Update password',

            'uploaded_games' => 'Uploaded games',
            'no_uploaded_games' => 'You have not uploaded any EGF games yet.',

            'delete_account' => 'Delete account',
            'delete_account_warning' => 'Deleting your account is permanent. Your uploaded EGF games will remain available, but they will be shown as anonymous uploads.',
            'delete_my_account' => 'Delete my account',
            'delete_game_confirm' => 'Delete this EGF game? This action cannot be undone.',
            'delete_account_confirm' => 'Delete your account? This action cannot be undone.',

            'reset_password' => 'Reset password',
            'account_email_address' => 'Account email address',
            'send_reset_link' => 'Send reset link',
            'choose_new_password' => 'Choose new password',

            'username' => 'Username',
            'password' => 'Password',
            'forgot_password' => 'Forgot password?',
            'no_account_yet' => 'No account yet?',
            'already_have_account' => 'Already have an account?',
            'confirm_password' => 'Confirm password',

            'registration_disabled' => 'Registration is currently disabled.',
            'verify_email' => 'Verify email',
            'verify_email_change' => 'Verify email change',

            'account_deleted' => 'Your account has been deleted.',
            'game_deleted_success' => 'Game deleted successfully.',
            'game_delete_failed' => 'Could not delete this game.',
            'account_delete_failed' => 'Could not delete your account. Please check your password.',

            'missing_verification_token' => 'Missing verification token.',
            'missing_password_reset_token' => 'Missing password reset token.',
            'expired_account_link_help' => 'If the link has expired, please create a new account or contact the administrator.',
            'expired_email_change_help' => 'If the link has expired, request a new email change from your account settings.',

            'invalid_account_action' => 'Invalid account action.',
            'new_passwords_do_not_match' => 'New passwords do not match.',
            'passwords_do_not_match' => 'Passwords do not match.',

            'login_success' => 'Logged in successfully.',
            'invalid_username_or_password' => 'Invalid username or password.',
            'login_temporarily_locked' => 'Too many failed login attempts. Please try again in {duration}.',

            'file_uploads_disabled' => 'File uploads are currently disabled.',
            'no_file_submitted' => 'No file was submitted.',
            'upload_failed_code' => 'Upload failed. Error code: {code}.',
            'only_egf_allowed' => 'Only .egf files are allowed.',
            'uploaded_file_empty' => 'The uploaded file is empty.',
            'upload_too_large' => 'The file is too large. Maximum size: {size}.',
            'upload_not_verified' => 'The upload could not be verified.',
            'invalid_egf_file' => 'Invalid or unsupported EGF file. {errors}',
            'additional_validation_errors' => 'Additional validation errors were found.',
            'fingerprint_failed' => 'Could not verify the uploaded file fingerprint.',
            'duplicate_egf_file' => 'This EGF file has already been uploaded.',
            'upload_success' => 'File uploaded successfully: {filename}',
            'upload_save_failed' => 'Could not save the uploaded file. Check that the uploads directory is writable.',

            'duration_seconds' => '{count} seconds',
            'duration_minutes' => '{count} minutes',

            'invalid_email_address' => 'Please enter a valid email address.',
            'email_provider_not_allowed' => 'This email provider is not allowed.',
            'email_provider_not_authorized' => 'This email provider is not authorized.',

            'verify_account_email_subject' => 'Verify your EGF Station account',
            'verify_account_email_body' => "Hello {username},\n\nPlease verify your EGF Station account by opening this link:\n\n{url}\n\nThis link expires in 24 hours.\n\nIf you did not create this account, you can ignore this email.\n",

            'invalid_verification_link' => 'Invalid verification link.',
            'user_account_not_found' => 'User account not found.',
            'verification_link_expired' => 'This verification link has expired.',
            'could_not_verify_account' => 'Could not verify the account.',
            'email_verified_login_now' => 'Your email address has been verified. You can now log in.',

            'not_allowed_delete_game' => 'You are not allowed to delete this game.',
            'game_file_not_found' => 'Game file not found.',
            'could_not_delete_game_file' => 'Could not delete the game file.',

            'invalid_username_rules' => 'Username must contain 3 to 32 characters: letters, numbers, underscores or hyphens only.',
            'password_min_length' => 'Password must contain at least 8 characters.',
            'username_taken' => 'This username is already taken.',
            'email_already_used' => 'This email address is already used by another account.',
            'wait_before_creating_account' => 'Please wait {duration} before creating another account.',
            'could_not_save_user_account' => 'Could not save the user account.',
            'account_created_email_not_sent' => 'The account was created, but the verification email could not be sent.',
            'account_created_check_email' => 'Account created successfully. Please check your email to verify your account.',
            'account_created_success' => 'Account created successfully.',

            'account_invalid_email' => 'This account does not have a valid email address.',
            'email_already_verified' => 'Email address is already verified.',
            'verification_email_recently_sent' => 'A verification email was already sent recently. Please wait {duration} before requesting another one.',
            'could_not_save_verification_token' => 'Could not save the new verification token.',
            'could_not_send_verification_email' => 'Could not send the verification email.',
            'new_verification_email_sent' => 'A new verification email has been sent.',

            'verify_email_before_login_new_sent' => 'Please verify your email address before logging in. A new verification email has been sent.',
            'verify_email_before_login_send_failed' => 'Please verify your email address before logging in. However, a new verification email could not be sent: {message}',

            'email_change_subject' => 'Confirm your new EGF Station email address',
            'email_change_body' => "Hello {username},\n\nPlease confirm your new email address by opening this link:\n\n{url}\n\nThis link expires in 24 hours.\n\nIf you did not request this change, you can ignore this email.\n",

            'must_login_change_email' => 'You must be logged in to change your email address.',
            'current_password_incorrect' => 'Current password is incorrect.',
            'email_unchanged' => 'Email address unchanged.',
            'could_not_save_email_change' => 'Could not save the email change request.',
            'email_change_saved_email_not_sent' => 'The email change request was saved, but the verification email could not be sent.',
            'email_change_link_sent' => 'A verification link has been sent to your new email address.',

            'must_login_delete_account' => 'You must be logged in to delete your account.',
            'could_not_delete_account' => 'Could not delete the account.',

            'must_login_change_password' => 'You must be logged in to change your password.',
            'new_password_min_length' => 'New password must contain at least 8 characters.',
            'could_not_update_password' => 'Could not update the password.',
            'password_updated_success' => 'Password updated successfully.',

            'email_verification_link_expired' => 'This email verification link has expired.',
            'could_not_update_email' => 'Could not update the email address.',
            'email_updated_success' => 'Your email address has been updated successfully.',

            'password_reset_email_subject' => 'Reset your EGF Station password',
            'password_reset_email_body' => "Hello {username},\n\nA password reset was requested for your EGF Station account.\n\nYou can choose a new password by opening this link:\n\n{url}\n\nThis link expires in 1 hour.\n\nIf you did not request this, you can ignore this email.\n",
            'password_reset_generic_message' => 'If an account uses this email address, a password reset link has been sent.',
            'could_not_create_password_reset' => 'Could not create the password reset request.',
            'password_reset_link_expired' => 'This password reset link has expired.',
            'password_updated_login_now' => 'Your password has been updated. You can now log in.',
            'invalid_password_reset_link' => 'Invalid password reset link.',

            'could_not_determine_ip' => 'Could not determine your IP address.',
            'uploads_blocked_ip' => 'File uploads are not allowed from your IP address.',
            'uploads_restricted_ip' => 'File uploads are restricted to authorized IP addresses.',
            'must_login_upload' => 'You must be logged in to upload EGF files.',

            'invalid_file_request' => 'Invalid file request.',
            'file_not_found' => 'File not found.',
            'ziparchive_unavailable' => 'ZipArchive is not available.',
            'cover_not_found' => 'Cover not found.',
            'could_not_open_egf_file' => 'Could not open EGF file.',
            'invalid_cover' => 'Invalid cover.',
            'method_not_allowed' => 'Method not allowed.',
            'invalid_security_token' => 'Invalid security token.',

            'unknown_creator' => 'Unknown creator',
            'no_description' => 'No description',
            'no_identifier' => 'No identifier',
            'no_modification_date' => 'No modification date',

            'ziparchive_extension_unavailable' => 'The PHP ZipArchive extension is not available.',
            'uploaded_file_cannot_be_read' => 'The uploaded file cannot be read.',
            'not_valid_zip_archive' => 'The file is not a valid ZIP archive.',

            'egf_package_incomplete' => 'The EGF package is incomplete.',
            'mimetype_must_be_first' => 'The mimetype file must be the first file in the archive.',
            'mimetype_missing' => 'The mimetype file is missing.',
            'mimetype_must_not_be_compressed' => 'The mimetype file must not be compressed.',
            'mimetype_invalid_size' => 'The mimetype file has an invalid size.',
            'mimetype_invalid_content' => 'The mimetype file must contain exactly: application/egf+zip.',

            'container_missing_or_too_large' => 'META-INF/container.xml is missing or too large.',
            'container_not_valid' => 'META-INF/container.xml is not a valid EGF container.',
            'container_version_required' => 'The container element must have version="1.0".',
            'container_one_rootfiles' => 'The container must contain exactly one rootfiles element.',
            'container_one_rootfile' => 'The container must contain exactly one rootfile element.',
            'rootfile_path_invalid' => 'The rootfile full-path is invalid.',
            'rootfile_id_required' => 'The rootfile id attribute is required.',

            'core_missing_or_too_large' => 'The EGF core file is missing or too large.',
            'core_must_have_egf_root' => 'The EGF core file must have an <egf> root element.',
            'core_version_required' => 'The EGF core file must declare a version attribute.',
            'egf_version_format' => 'The EGF version must use the major.minor format, for example 1.0 or 1.1.',
            'egf_version_not_supported' => 'This EGF version is not supported by this archive yet.',

            'metadata_missing' => 'The metadata element is missing.',
            'metadata_title_required' => 'The metadata must contain a non-empty dc:title.',
            'metadata_language_required' => 'The metadata must contain a non-empty dc:language.',
            'metadata_modified_required' => 'The metadata must contain meta property="dcterms:modified".',
            'metadata_modified_utc' => 'The dcterms:modified value must be a UTC dateTime, for example 2025-11-12T10:00:00Z.',

            'manifest_missing' => 'The manifest element is missing.',
            'manifest_children_item_only' => 'All manifest children must be item elements.',
            'manifest_item_id_required' => 'Each manifest item must have an id attribute.',
            'manifest_item_role_required' => 'Each manifest item must have a role attribute.',
            'manifest_duplicate_id' => 'Manifest item ids must be unique. Duplicate id: {id}.',
            'manifest_custom_x_roles_not_allowed' => 'Custom roles starting with x- are not allowed for a conforming EGF 1.1 package.',
            'manifest_item_empty_href' => 'Manifest item "{id}" has an empty href.',
            'manifest_item_invalid_href' => 'Manifest item "{id}" has an invalid href.',
            'manifest_item_missing_resource' => 'Manifest item "{id}" references a missing resource: {path}.',
            'manifest_item_media_type_required' => 'Manifest item "{id}" with href must have a media-type attribute.',
            'cover_must_be_png_or_jpeg' => 'The egf_cover item must use image/png or image/jpeg.',
            'max_wrong_answers_positive_integer' => 'The max_wrong_answers item must have a positive integer value.',
            'manifest_role_exactly_once' => 'The manifest must contain exactly one item with role="{role}".',

            'sequence_missing' => 'The sequence element is missing.',
            'sequence_children_scene_only' => 'All sequence children must be scene elements.',
            'scene_ref_required' => 'Each scene must have a ref attribute.',
            'sequence_unknown_manifest_item' => 'The sequence references an unknown manifest item: {ref}.',
            'sequence_duplicate_ref' => 'Each scene ref must be unique in the sequence for EGF 1.1. Duplicate ref: {ref}.',
            'sequence_minimum_scenes' => 'The sequence must contain at least the title, congratulations, game over, and credits scenes.',
            'title_scene_first' => 'The Game Title scene must be the first scene in the sequence.',
            'credits_scene_last' => 'The Credits scene must be the last scene in the sequence.',
            'game_over_scene_penultimate' => 'The Game Over scene must be the penultimate scene in the sequence.',
            'congratulations_scene_antepenultimate' => 'The Congratulations scene must be the antepenultimate scene in the sequence.',

            'settings_missing' => 'The settings element is missing.',
            'settings_children_setting_only' => 'All settings children must be setting elements.',
            'setting_ref_required' => 'Each setting must have a ref attribute.',
            'settings_unknown_manifest_item' => 'The settings reference an unknown manifest item: {ref}.',
            'settings_max_wrong_answers_once' => 'The settings must contain exactly one setting for max_wrong_answers.',

            'download' => 'Download',
            'play' => 'Play',
        ],
        'fr' => [
            'language' => 'Langue',
            'apply' => 'Appliquer',
            'main_actions' => 'Actions principales',

            'tagline' => 'Une archive simple pour partager et télécharger des jeux EGF.',
            'back_home' => '← Retour à l\'accueil',
            'back_login' => '← Retour à la connexion',
            'logged_in_as' => 'Connecté en tant que {username}.',
            'account_settings' => 'Paramètres du compte',
            'logout' => 'Se déconnecter',
            'login' => 'Se connecter',
            'create_account' => 'Créer un compte',
            'upload_requires_account' => 'L\'envoi nécessite un compte.',
            'account_optional' => 'Le compte est facultatif.',
            'you_need_account_upload' => 'Vous devez avoir un compte pour envoyer des fichiers EGF.',
            'or' => 'ou',

            'upload_game' => 'Envoyer un jeu',
            'upload_an_egf_game' => 'Envoyer un jeu EGF',
            'display_all_games' => 'Afficher tous les jeux',
            'available_games' => 'Jeux EGF disponibles',
            'no_games' => 'Aucun jeu EGF n\'a encore été envoyé.',
            'upload_another_game' => 'Envoyer un autre jeu',
            'maximum_upload_size' => 'Taille maximale d\'envoi : {size}.',
            'rights_warning' => 'Assurez-vous de posséder les droits du jeu EGF, ou qu\'il est distribué sous une licence libre, avant de l\'envoyer. Les fichiers qui ne respectent pas cette exigence peuvent être supprimés.',
            'choose_egf_file' => 'Choisir un fichier .egf',

            'icon' => 'Icône',
            'name' => 'Nom',
            'creator' => 'Créateur',
            'description' => 'Description',
            'identifier' => 'Identifiant',
            'modified' => 'Modifié',
            'size' => 'Taille',
            'uploaded_by' => 'Envoyé par',
            'download' => 'Télécharger',
            'delete' => 'Supprimer',
            'anonymous_upload' => 'Envoi anonyme',

            'email' => 'E-mail',
            'change_email' => 'Modifier l\'adresse e-mail',
            'new_email' => 'Nouvelle adresse e-mail',
            'current_password' => 'Mot de passe actuel',
            'send_verification_email' => 'Envoyer l\'e-mail de vérification',
            'change_password' => 'Modifier le mot de passe',
            'new_password' => 'Nouveau mot de passe',
            'confirm_new_password' => 'Confirmer le nouveau mot de passe',
            'update_password' => 'Mettre à jour le mot de passe',

            'uploaded_games' => 'Jeux envoyés',
            'no_uploaded_games' => 'Vous n\'avez encore envoyé aucun jeu EGF.',

            'delete_account' => 'Supprimer le compte',
            'delete_account_warning' => 'La suppression de votre compte est définitive. Vos jeux EGF envoyés resteront disponibles, mais ils seront affichés comme des envois anonymes.',
            'delete_my_account' => 'Supprimer mon compte',
            'delete_game_confirm' => 'Supprimer ce jeu EGF ? Cette action est irréversible.',
            'delete_account_confirm' => 'Supprimer votre compte ? Cette action est irréversible.',

            'reset_password' => 'Réinitialiser le mot de passe',
            'account_email_address' => 'Adresse e-mail du compte',
            'send_reset_link' => 'Envoyer le lien de réinitialisation',
            'choose_new_password' => 'Choisir un nouveau mot de passe',

            'username' => 'Nom d\'utilisateur',
            'password' => 'Mot de passe',
            'forgot_password' => 'Mot de passe oublié ?',
            'no_account_yet' => 'Pas encore de compte ?',
            'already_have_account' => 'Vous avez déjà un compte ?',
            'confirm_password' => 'Confirmer le mot de passe',

            'registration_disabled' => 'L\'inscription est actuellement désactivée.',
            'verify_email' => 'Vérifier l\'e-mail',
            'verify_email_change' => 'Vérifier le changement d\'e-mail',

            'account_deleted' => 'Votre compte a été supprimé.',
            'game_deleted_success' => 'Jeu supprimé avec succès.',
            'game_delete_failed' => 'Impossible de supprimer ce jeu.',
            'account_delete_failed' => 'Impossible de supprimer votre compte. Vérifiez votre mot de passe.',

            'missing_verification_token' => 'Jeton de vérification manquant.',
            'missing_password_reset_token' => 'Jeton de réinitialisation du mot de passe manquant.',
            'expired_account_link_help' => 'Si le lien a expiré, créez un nouveau compte ou contactez l\'administrateur.',
            'expired_email_change_help' => 'Si le lien a expiré, demandez un nouveau changement d\'e-mail dans les paramètres de votre compte.',

            'invalid_account_action' => 'Action de compte invalide.',
            'new_passwords_do_not_match' => 'Les nouveaux mots de passe ne correspondent pas.',
            'passwords_do_not_match' => 'Les mots de passe ne correspondent pas.',

            'login_success' => 'Connexion réussie.',
            'invalid_username_or_password' => 'Nom d\'utilisateur ou mot de passe invalide.',
            'login_temporarily_locked' => 'Trop de tentatives de connexion échouées. Réessayez dans {duration}.',

            'file_uploads_disabled' => 'Les envois de fichiers sont actuellement désactivés.',
            'no_file_submitted' => 'Aucun fichier n\'a été soumis.',
            'upload_failed_code' => 'L\'envoi a échoué. Code d\'erreur : {code}.',
            'only_egf_allowed' => 'Seuls les fichiers .egf sont autorisés.',
            'uploaded_file_empty' => 'Le fichier envoyé est vide.',
            'upload_too_large' => 'Le fichier est trop volumineux. Taille maximale : {size}.',
            'upload_not_verified' => 'L\'envoi n\'a pas pu être vérifié.',
            'invalid_egf_file' => 'Fichier EGF invalide ou non pris en charge. {errors}',
            'additional_validation_errors' => 'D\'autres erreurs de validation ont été trouvées.',
            'fingerprint_failed' => 'Impossible de vérifier l\'empreinte du fichier envoyé.',
            'duplicate_egf_file' => 'Ce fichier EGF a déjà été envoyé.',
            'upload_success' => 'Fichier envoyé avec succès : {filename}',
            'upload_save_failed' => 'Impossible d\'enregistrer le fichier envoyé. Vérifiez que le dossier uploads est accessible en écriture.',

            'duration_seconds' => '{count} secondes',
            'duration_minutes' => '{count} minutes',

            'invalid_email_address' => 'Veuillez saisir une adresse e-mail valide.',
            'email_provider_not_allowed' => 'Ce fournisseur d\'e-mail n\'est pas autorisé.',
            'email_provider_not_authorized' => 'Ce fournisseur d\'e-mail n\'est pas accepté.',

            'verify_account_email_subject' => 'Vérifiez votre compte EGF Station',
            'verify_account_email_body' => "Bonjour {username},\n\nVeuillez vérifier votre compte EGF Station en ouvrant ce lien :\n\n{url}\n\nCe lien expire dans 24 heures.\n\nSi vous n\'avez pas créé ce compte, vous pouvez ignorer cet e-mail.\n",

            'invalid_verification_link' => 'Lien de vérification invalide.',
            'user_account_not_found' => 'Compte utilisateur introuvable.',
            'verification_link_expired' => 'Ce lien de vérification a expiré.',
            'could_not_verify_account' => 'Impossible de vérifier le compte.',
            'email_verified_login_now' => 'Votre adresse e-mail a été vérifiée. Vous pouvez maintenant vous connecter.',

            'not_allowed_delete_game' => 'Vous n\'êtes pas autorisé à supprimer ce jeu.',
            'game_file_not_found' => 'Fichier du jeu introuvable.',
            'could_not_delete_game_file' => 'Impossible de supprimer le fichier du jeu.',

            'invalid_username_rules' => 'Le nom d\'utilisateur doit contenir 3 à 32 caractères : lettres, chiffres, tirets bas ou traits d\'union uniquement.',
            'password_min_length' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'username_taken' => 'Ce nom d\'utilisateur est déjà pris.',
            'email_already_used' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
            'wait_before_creating_account' => 'Veuillez attendre {duration} avant de créer un autre compte.',
            'could_not_save_user_account' => 'Impossible d\'enregistrer le compte utilisateur.',
            'account_created_email_not_sent' => 'Le compte a été créé, mais l\'e-mail de vérification n\'a pas pu être envoyé.',
            'account_created_check_email' => 'Compte créé avec succès. Veuillez consulter vos e-mails pour vérifier votre compte.',
            'account_created_success' => 'Compte créé avec succès.',

            'account_invalid_email' => 'Ce compte ne possède pas d\'adresse e-mail valide.',
            'email_already_verified' => 'L\'adresse e-mail est déjà vérifiée.',
            'verification_email_recently_sent' => 'Un e-mail de vérification a déjà été envoyé récemment. Veuillez attendre {duration} avant d\'en demander un autre.',
            'could_not_save_verification_token' => 'Impossible d\'enregistrer le nouveau jeton de vérification.',
            'could_not_send_verification_email' => 'Impossible d\'envoyer l\'e-mail de vérification.',
            'new_verification_email_sent' => 'Un nouvel e-mail de vérification a été envoyé.',

            'verify_email_before_login_new_sent' => 'Veuillez vérifier votre adresse e-mail avant de vous connecter. Un nouvel e-mail de vérification a été envoyé.',
            'verify_email_before_login_send_failed' => 'Veuillez vérifier votre adresse e-mail avant de vous connecter. Cependant, un nouvel e-mail de vérification n\'a pas pu être envoyé : {message}',

            'email_change_subject' => 'Confirmez votre nouvelle adresse e-mail EGF Station',
            'email_change_body' => "Bonjour {username},\n\nVeuillez confirmer votre nouvelle adresse e-mail en ouvrant ce lien :\n\n{url}\n\nCe lien expire dans 24 heures.\n\nSi vous n\'avez pas demandé ce changement, vous pouvez ignorer cet e-mail.\n",

            'must_login_change_email' => 'Vous devez être connecté pour modifier votre adresse e-mail.',
            'current_password_incorrect' => 'Le mot de passe actuel est incorrect.',
            'email_unchanged' => 'Adresse e-mail inchangée.',
            'could_not_save_email_change' => 'Impossible d\'enregistrer la demande de changement d\'e-mail.',
            'email_change_saved_email_not_sent' => 'La demande de changement d\'e-mail a été enregistrée, mais l\'e-mail de vérification n\'a pas pu être envoyé.',
            'email_change_link_sent' => 'Un lien de vérification a été envoyé à votre nouvelle adresse e-mail.',

            'must_login_delete_account' => 'Vous devez être connecté pour supprimer votre compte.',
            'could_not_delete_account' => 'Impossible de supprimer le compte.',

            'must_login_change_password' => 'Vous devez être connecté pour modifier votre mot de passe.',
            'new_password_min_length' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'could_not_update_password' => 'Impossible de mettre à jour le mot de passe.',
            'password_updated_success' => 'Mot de passe mis à jour avec succès.',

            'email_verification_link_expired' => 'Ce lien de vérification d\'e-mail a expiré.',
            'could_not_update_email' => 'Impossible de mettre à jour l\'adresse e-mail.',
            'email_updated_success' => 'Votre adresse e-mail a été mise à jour avec succès.',

            'password_reset_email_subject' => 'Réinitialisez votre mot de passe EGF Station',
            'password_reset_email_body' => "Bonjour {username},\n\nUne demande de réinitialisation de mot de passe a été effectuée pour votre compte EGF Station.\n\nVous pouvez choisir un nouveau mot de passe en ouvrant ce lien :\n\n{url}\n\nCe lien expire dans 1 heure.\n\nSi vous n\'avez pas demandé cela, vous pouvez ignorer cet e-mail.\n",
            'password_reset_generic_message' => 'Si un compte utilise cette adresse e-mail, un lien de réinitialisation a été envoyé.',
            'could_not_create_password_reset' => 'Impossible de créer la demande de réinitialisation du mot de passe.',
            'password_reset_link_expired' => 'Ce lien de réinitialisation du mot de passe a expiré.',
            'password_updated_login_now' => 'Votre mot de passe a été mis à jour. Vous pouvez maintenant vous connecter.',
            'invalid_password_reset_link' => 'Lien de réinitialisation du mot de passe invalide.',

            'could_not_determine_ip' => 'Impossible de déterminer votre adresse IP.',
            'uploads_blocked_ip' => 'Les envois de fichiers ne sont pas autorisés depuis votre adresse IP.',
            'uploads_restricted_ip' => 'Les envois de fichiers sont limités aux adresses IP autorisées.',
            'must_login_upload' => 'Vous devez être connecté pour envoyer des fichiers EGF.',

            'invalid_file_request' => 'Demande de fichier invalide.',
            'file_not_found' => 'Fichier introuvable.',
            'ziparchive_unavailable' => 'ZipArchive n\'est pas disponible.',
            'cover_not_found' => 'Couverture introuvable.',
            'could_not_open_egf_file' => 'Impossible d\'ouvrir le fichier EGF.',
            'invalid_cover' => 'Couverture invalide.',
            'method_not_allowed' => 'Méthode non autorisée.',
            'invalid_security_token' => 'Jeton de sécurité invalide.',

            'unknown_creator' => 'Créateur inconnu',
            'no_description' => 'Aucune description',
            'no_identifier' => 'Aucun identifiant',
            'no_modification_date' => 'Aucune date de modification',

            'ziparchive_extension_unavailable' => 'L\'extension PHP ZipArchive n\'est pas disponible.',
            'uploaded_file_cannot_be_read' => 'Le fichier envoyé ne peut pas être lu.',
            'not_valid_zip_archive' => 'Le fichier n\'est pas une archive ZIP valide.',

            'egf_package_incomplete' => 'Le paquet EGF est incomplet.',
            'mimetype_must_be_first' => 'Le fichier mimetype doit être le premier fichier de l\'archive.',
            'mimetype_missing' => 'Le fichier mimetype est manquant.',
            'mimetype_must_not_be_compressed' => 'Le fichier mimetype ne doit pas être compressé.',
            'mimetype_invalid_size' => 'Le fichier mimetype a une taille invalide.',
            'mimetype_invalid_content' => 'Le fichier mimetype doit contenir exactement : application/egf+zip.',

            'container_missing_or_too_large' => 'META-INF/container.xml est manquant ou trop volumineux.',
            'container_not_valid' => 'META-INF/container.xml n\'est pas un conteneur EGF valide.',
            'container_version_required' => 'L\'élément container doit avoir version="1.0".',
            'container_one_rootfiles' => 'Le conteneur doit contenir exactement un élément rootfiles.',
            'container_one_rootfile' => 'Le conteneur doit contenir exactement un élément rootfile.',
            'rootfile_path_invalid' => 'L\'attribut full-path du rootfile est invalide.',
            'rootfile_id_required' => 'L\'attribut id du rootfile est obligatoire.',

            'core_missing_or_too_large' => 'Le fichier cœur EGF est manquant ou trop volumineux.',
            'core_must_have_egf_root' => 'Le fichier cœur EGF doit avoir un élément racine <egf>.',
            'core_version_required' => 'Le fichier cœur EGF doit déclarer un attribut version.',
            'egf_version_format' => 'La version EGF doit utiliser le format majeur.mineur, par exemple 1.0 ou 1.1.',
            'egf_version_not_supported' => 'Cette version EGF n\'est pas encore prise en charge par cette archive.',

            'metadata_missing' => 'L\'élément metadata est manquant.',
            'metadata_title_required' => 'Les métadonnées doivent contenir un dc:title non vide.',
            'metadata_language_required' => 'Les métadonnées doivent contenir un dc:language non vide.',
            'metadata_modified_required' => 'Les métadonnées doivent contenir meta property="dcterms:modified".',
            'metadata_modified_utc' => 'La valeur dcterms:modified doit être une dateTime UTC, par exemple 2025-11-12T10:00:00Z.',

            'manifest_missing' => 'L\'élément manifest est manquant.',
            'manifest_children_item_only' => 'Tous les enfants du manifest doivent être des éléments item.',
            'manifest_item_id_required' => 'Chaque élément du manifest doit avoir un attribut id.',
            'manifest_item_role_required' => 'Chaque élément du manifest doit avoir un attribut role.',
            'manifest_duplicate_id' => 'Les identifiants des éléments du manifest doivent être uniques. Identifiant dupliqué : {id}.',
            'manifest_custom_x_roles_not_allowed' => 'Les rôles personnalisés commençant par x- ne sont pas autorisés pour un paquet EGF 1.1 conforme.',
            'manifest_item_empty_href' => 'L\'élément du manifest "{id}" a un href vide.',
            'manifest_item_invalid_href' => 'L\'élément du manifest "{id}" a un href invalide.',
            'manifest_item_missing_resource' => 'L\'élément du manifest "{id}" référence une ressource manquante : {path}.',
            'manifest_item_media_type_required' => 'L\'élément du manifest "{id}" avec href doit avoir un attribut media-type.',
            'cover_must_be_png_or_jpeg' => 'L\'élément egf_cover doit utiliser image/png ou image/jpeg.',
            'max_wrong_answers_positive_integer' => 'L\'élément max_wrong_answers doit avoir une valeur entière positive.',
            'manifest_role_exactly_once' => 'Le manifest doit contenir exactement un élément avec role="{role}".',

            'sequence_missing' => 'L\'élément sequence est manquant.',
            'sequence_children_scene_only' => 'Tous les enfants de sequence doivent être des éléments scene.',
            'scene_ref_required' => 'Chaque scène doit avoir un attribut ref.',
            'sequence_unknown_manifest_item' => 'La séquence référence un élément du manifest inconnu : {ref}.',
            'sequence_duplicate_ref' => 'Chaque ref de scène doit être unique dans la séquence pour EGF 1.1. Ref dupliquée : {ref}.',
            'sequence_minimum_scenes' => 'La séquence doit contenir au moins les scènes de titre, de félicitations, de game over et de crédits.',
            'title_scene_first' => 'La scène Game Title doit être la première scène de la séquence.',
            'credits_scene_last' => 'La scène Credits doit être la dernière scène de la séquence.',
            'game_over_scene_penultimate' => 'La scène Game Over doit être l\'avant-dernière scène de la séquence.',
            'congratulations_scene_antepenultimate' => 'La scène Congratulations doit être l\'antépénultième scène de la séquence.',

            'settings_missing' => 'L\'élément settings est manquant.',
            'settings_children_setting_only' => 'Tous les enfants de settings doivent être des éléments setting.',
            'setting_ref_required' => 'Chaque paramètre doit avoir un attribut ref.',
            'settings_unknown_manifest_item' => 'Les paramètres référencent un élément du manifest inconnu : {ref}.',
            'settings_max_wrong_answers_once' => 'Les paramètres doivent contenir exactement un paramètre pour max_wrong_answers.',

            'download' => 'Télécharger',
            'play' => 'Jouer',
        ],
        'es' => [
            'language' => 'Idioma',
            'apply' => 'Aplicar',
            'main_actions' => 'Acciones principales',

            'tagline' => 'Un archivo sencillo para compartir y descargar juegos EGF.',
            'back_home' => '← Volver al inicio',
            'back_login' => '← Volver al inicio de sesión',
            'logged_in_as' => 'Sesión iniciada como {username}.',
            'account_settings' => 'Configuración de la cuenta',
            'logout' => 'Cerrar sesión',
            'login' => 'Iniciar sesión',
            'create_account' => 'Crear una cuenta',
            'upload_requires_account' => 'Para subir archivos se necesita una cuenta.',
            'account_optional' => 'La cuenta es opcional.',
            'you_need_account_upload' => 'Necesitas una cuenta para subir archivos EGF.',
            'or' => 'o',

            'upload_game' => 'Subir un juego',
            'upload_an_egf_game' => 'Subir un juego EGF',
            'display_all_games' => 'Mostrar todos los juegos',
            'available_games' => 'Juegos EGF disponibles',
            'no_games' => 'Todavía no se ha subido ningún juego EGF.',
            'upload_another_game' => 'Subir otro juego',
            'maximum_upload_size' => 'Tamaño máximo de subida: {size}.',
            'rights_warning' => 'Asegúrate de tener los derechos del juego EGF o de que se distribuya con una licencia libre antes de subirlo. Los archivos que no cumplan este requisito pueden ser eliminados.',
            'choose_egf_file' => 'Elegir un archivo .egf',

            'icon' => 'Icono',
            'name' => 'Nombre',
            'creator' => 'Creador',
            'description' => 'Descripción',
            'identifier' => 'Identificador',
            'modified' => 'Modificado',
            'size' => 'Tamaño',
            'uploaded_by' => 'Subido por',
            'download' => 'Descargar',
            'delete' => 'Eliminar',
            'anonymous_upload' => 'Subida anónima',

            'email' => 'Correo electrónico',
            'change_email' => 'Cambiar dirección de correo electrónico',
            'new_email' => 'Nueva dirección de correo electrónico',
            'current_password' => 'Contraseña actual',
            'send_verification_email' => 'Enviar correo de verificación',
            'change_password' => 'Cambiar contraseña',
            'new_password' => 'Nueva contraseña',
            'confirm_new_password' => 'Confirmar nueva contraseña',
            'update_password' => 'Actualizar contraseña',

            'uploaded_games' => 'Juegos subidos',
            'no_uploaded_games' => 'Aún no has subido ningún juego EGF.',

            'delete_account' => 'Eliminar cuenta',
            'delete_account_warning' => 'Eliminar tu cuenta es permanente. Tus juegos EGF subidos seguirán disponibles, pero aparecerán como subidas anónimas.',
            'delete_my_account' => 'Eliminar mi cuenta',
            'delete_game_confirm' => '¿Eliminar este juego EGF? Esta acción no se puede deshacer.',
            'delete_account_confirm' => '¿Eliminar tu cuenta? Esta acción no se puede deshacer.',

            'reset_password' => 'Restablecer contraseña',
            'account_email_address' => 'Correo electrónico de la cuenta',
            'send_reset_link' => 'Enviar enlace de restablecimiento',
            'choose_new_password' => 'Elegir nueva contraseña',

            'username' => 'Nombre de usuario',
            'password' => 'Contraseña',
            'forgot_password' => '¿Olvidaste tu contraseña?',
            'no_account_yet' => '¿Aún no tienes cuenta?',
            'already_have_account' => '¿Ya tienes una cuenta?',
            'confirm_password' => 'Confirmar contraseña',

            'registration_disabled' => 'El registro está desactivado actualmente.',
            'verify_email' => 'Verificar correo electrónico',
            'verify_email_change' => 'Verificar cambio de correo electrónico',

            'account_deleted' => 'Tu cuenta ha sido eliminada.',
            'game_deleted_success' => 'Juego eliminado correctamente.',
            'game_delete_failed' => 'No se pudo eliminar este juego.',
            'account_delete_failed' => 'No se pudo eliminar tu cuenta. Comprueba tu contraseña.',

            'missing_verification_token' => 'Falta el token de verificación.',
            'missing_password_reset_token' => 'Falta el token de restablecimiento de contraseña.',
            'expired_account_link_help' => 'Si el enlace ha caducado, crea una cuenta nueva o contacta con el administrador.',
            'expired_email_change_help' => 'Si el enlace ha caducado, solicita un nuevo cambio de correo electrónico desde la configuración de tu cuenta.',

            'invalid_account_action' => 'Acción de cuenta no válida.',
            'new_passwords_do_not_match' => 'Las nuevas contraseñas no coinciden.',
            'passwords_do_not_match' => 'Las contraseñas no coinciden.',

            'login_success' => 'Inicio de sesión correcto.',
            'invalid_username_or_password' => 'Nombre de usuario o contraseña no válidos.',
            'login_temporarily_locked' => 'Demasiados intentos de inicio de sesión fallidos. Inténtelo de nuevo en {duration}.',

            'file_uploads_disabled' => 'La subida de archivos está desactivada actualmente.',
            'no_file_submitted' => 'No se ha enviado ningún archivo.',
            'upload_failed_code' => 'La subida falló. Código de error: {code}.',
            'only_egf_allowed' => 'Solo se permiten archivos .egf.',
            'uploaded_file_empty' => 'El archivo subido está vacío.',
            'upload_too_large' => 'El archivo es demasiado grande. Tamaño máximo: {size}.',
            'upload_not_verified' => 'No se pudo verificar la subida.',
            'invalid_egf_file' => 'Archivo EGF no válido o no compatible. {errors}',
            'additional_validation_errors' => 'Se encontraron errores de validación adicionales.',
            'fingerprint_failed' => 'No se pudo verificar la huella del archivo subido.',
            'duplicate_egf_file' => 'Este archivo EGF ya ha sido subido.',
            'upload_success' => 'Archivo subido correctamente: {filename}',
            'upload_save_failed' => 'No se pudo guardar el archivo subido. Comprueba que el directorio uploads tenga permisos de escritura.',

            'duration_seconds' => '{count} segundos',
            'duration_minutes' => '{count} minutos',

            'invalid_email_address' => 'Introduce una dirección de correo electrónico válida.',
            'email_provider_not_allowed' => 'Este proveedor de correo electrónico no está permitido.',
            'email_provider_not_authorized' => 'Este proveedor de correo electrónico no está autorizado.',

            'verify_account_email_subject' => 'Verifica tu cuenta de EGF Station',
            'verify_account_email_body' => "Hola {username},\n\nVerifica tu cuenta de EGF Station abriendo este enlace:\n\n{url}\n\nEste enlace caduca en 24 horas.\n\nSi no has creado esta cuenta, puedes ignorar este correo.\n",

            'invalid_verification_link' => 'Enlace de verificación no válido.',
            'user_account_not_found' => 'Cuenta de usuario no encontrada.',
            'verification_link_expired' => 'Este enlace de verificación ha caducado.',
            'could_not_verify_account' => 'No se pudo verificar la cuenta.',
            'email_verified_login_now' => 'Tu dirección de correo electrónico ha sido verificada. Ya puedes iniciar sesión.',

            'not_allowed_delete_game' => 'No tienes permiso para eliminar este juego.',
            'game_file_not_found' => 'Archivo del juego no encontrado.',
            'could_not_delete_game_file' => 'No se pudo eliminar el archivo del juego.',

            'invalid_username_rules' => 'El nombre de usuario debe contener entre 3 y 32 caracteres: solo letras, números, guiones bajos o guiones.',
            'password_min_length' => 'La contraseña debe contener al menos 8 caracteres.',
            'username_taken' => 'Este nombre de usuario ya está en uso.',
            'email_already_used' => 'Esta dirección de correo electrónico ya está siendo utilizada por otra cuenta.',
            'wait_before_creating_account' => 'Espera {duration} antes de crear otra cuenta.',
            'could_not_save_user_account' => 'No se pudo guardar la cuenta de usuario.',
            'account_created_email_not_sent' => 'La cuenta fue creada, pero no se pudo enviar el correo de verificación.',
            'account_created_check_email' => 'Cuenta creada correctamente. Revisa tu correo para verificarla.',
            'account_created_success' => 'Cuenta creada correctamente.',

            'account_invalid_email' => 'Esta cuenta no tiene una dirección de correo electrónico válida.',
            'email_already_verified' => 'La dirección de correo electrónico ya está verificada.',
            'verification_email_recently_sent' => 'Ya se envió un correo de verificación recientemente. Espera {duration} antes de solicitar otro.',
            'could_not_save_verification_token' => 'No se pudo guardar el nuevo token de verificación.',
            'could_not_send_verification_email' => 'No se pudo enviar el correo de verificación.',
            'new_verification_email_sent' => 'Se ha enviado un nuevo correo de verificación.',

            'verify_email_before_login_new_sent' => 'Verifica tu dirección de correo electrónico antes de iniciar sesión. Se ha enviado un nuevo correo de verificación.',
            'verify_email_before_login_send_failed' => 'Verifica tu dirección de correo electrónico antes de iniciar sesión. Sin embargo, no se pudo enviar un nuevo correo de verificación: {message}',

            'email_change_subject' => 'Confirma tu nueva dirección de correo electrónico de EGF Station',
            'email_change_body' => "Hola {username},\n\nConfirma tu nueva dirección de correo electrónico abriendo este enlace:\n\n{url}\n\nEste enlace caduca en 24 horas.\n\nSi no solicitaste este cambio, puedes ignorar este correo.\n",

            'must_login_change_email' => 'Debes iniciar sesión para cambiar tu dirección de correo electrónico.',
            'current_password_incorrect' => 'La contraseña actual es incorrecta.',
            'email_unchanged' => 'Dirección de correo electrónico sin cambios.',
            'could_not_save_email_change' => 'No se pudo guardar la solicitud de cambio de correo electrónico.',
            'email_change_saved_email_not_sent' => 'La solicitud de cambio de correo electrónico fue guardada, pero no se pudo enviar el correo de verificación.',
            'email_change_link_sent' => 'Se ha enviado un enlace de verificación a tu nueva dirección de correo electrónico.',

            'must_login_delete_account' => 'Debes iniciar sesión para eliminar tu cuenta.',
            'could_not_delete_account' => 'No se pudo eliminar la cuenta.',

            'must_login_change_password' => 'Debes iniciar sesión para cambiar tu contraseña.',
            'new_password_min_length' => 'La nueva contraseña debe contener al menos 8 caracteres.',
            'could_not_update_password' => 'No se pudo actualizar la contraseña.',
            'password_updated_success' => 'Contraseña actualizada correctamente.',

            'email_verification_link_expired' => 'Este enlace de verificación de correo electrónico ha caducado.',
            'could_not_update_email' => 'No se pudo actualizar la dirección de correo electrónico.',
            'email_updated_success' => 'Tu dirección de correo electrónico se ha actualizado correctamente.',

            'password_reset_email_subject' => 'Restablece tu contraseña de EGF Station',
            'password_reset_email_body' => "Hola {username},\n\nSe solicitó un restablecimiento de contraseña para tu cuenta de EGF Station.\n\nPuedes elegir una nueva contraseña abriendo este enlace:\n\n{url}\n\nEste enlace caduca en 1 hora.\n\nSi no solicitaste esto, puedes ignorar este correo.\n",
            'password_reset_generic_message' => 'Si existe una cuenta con esta dirección de correo electrónico, se ha enviado un enlace de restablecimiento.',
            'could_not_create_password_reset' => 'No se pudo crear la solicitud de restablecimiento de contraseña.',
            'password_reset_link_expired' => 'Este enlace de restablecimiento de contraseña ha caducado.',
            'password_updated_login_now' => 'Tu contraseña ha sido actualizada. Ya puedes iniciar sesión.',
            'invalid_password_reset_link' => 'Enlace de restablecimiento de contraseña no válido.',

            'could_not_determine_ip' => 'No se pudo determinar tu dirección IP.',
            'uploads_blocked_ip' => 'No se permite subir archivos desde tu dirección IP.',
            'uploads_restricted_ip' => 'La subida de archivos está restringida a direcciones IP autorizadas.',
            'must_login_upload' => 'Debes iniciar sesión para subir archivos EGF.',

            'invalid_file_request' => 'Solicitud de archivo no válida.',
            'file_not_found' => 'Archivo no encontrado.',
            'ziparchive_unavailable' => 'ZipArchive no está disponible.',
            'cover_not_found' => 'Portada no encontrada.',
            'could_not_open_egf_file' => 'No se pudo abrir el archivo EGF.',
            'invalid_cover' => 'Portada no válida.',
            'method_not_allowed' => 'Método no permitido.',
            'invalid_security_token' => 'Token de seguridad no válido.',

            'unknown_creator' => 'Creador desconocido',
            'no_description' => 'Sin descripción',
            'no_identifier' => 'Sin identificador',
            'no_modification_date' => 'Sin fecha de modificación',

            'ziparchive_extension_unavailable' => 'La extensión PHP ZipArchive no está disponible.',
            'uploaded_file_cannot_be_read' => 'No se puede leer el archivo subido.',
            'not_valid_zip_archive' => 'El archivo no es un archivo ZIP válido.',

            'egf_package_incomplete' => 'El paquete EGF está incompleto.',
            'mimetype_must_be_first' => 'El archivo mimetype debe ser el primer archivo del archivo comprimido.',
            'mimetype_missing' => 'Falta el archivo mimetype.',
            'mimetype_must_not_be_compressed' => 'El archivo mimetype no debe estar comprimido.',
            'mimetype_invalid_size' => 'El archivo mimetype tiene un tamaño no válido.',
            'mimetype_invalid_content' => 'El archivo mimetype debe contener exactamente: application/egf+zip.',

            'container_missing_or_too_large' => 'META-INF/container.xml falta o es demasiado grande.',
            'container_not_valid' => 'META-INF/container.xml no es un contenedor EGF válido.',
            'container_version_required' => 'El elemento container debe tener version="1.0".',
            'container_one_rootfiles' => 'El contenedor debe contener exactamente un elemento rootfiles.',
            'container_one_rootfile' => 'El contenedor debe contener exactamente un elemento rootfile.',
            'rootfile_path_invalid' => 'El atributo full-path del rootfile no es válido.',
            'rootfile_id_required' => 'El atributo id del rootfile es obligatorio.',

            'core_missing_or_too_large' => 'El archivo principal EGF falta o es demasiado grande.',
            'core_must_have_egf_root' => 'El archivo principal EGF debe tener un elemento raíz <egf>.',
            'core_version_required' => 'El archivo principal EGF debe declarar un atributo version.',
            'egf_version_format' => 'La versión EGF debe usar el formato mayor.menor, por ejemplo 1.0 o 1.1.',
            'egf_version_not_supported' => 'Esta versión de EGF aún no es compatible con este archivo.',

            'metadata_missing' => 'Falta el elemento metadata.',
            'metadata_title_required' => 'Los metadatos deben contener un dc:title no vacío.',
            'metadata_language_required' => 'Los metadatos deben contener un dc:language no vacío.',
            'metadata_modified_required' => 'Los metadatos deben contener meta property="dcterms:modified".',
            'metadata_modified_utc' => 'El valor dcterms:modified debe ser una fecha y hora UTC, por ejemplo 2025-11-12T10:00:00Z.',

            'manifest_missing' => 'Falta el elemento manifest.',
            'manifest_children_item_only' => 'Todos los elementos hijos del manifest deben ser elementos item.',
            'manifest_item_id_required' => 'Cada elemento del manifest debe tener un atributo id.',
            'manifest_item_role_required' => 'Cada elemento del manifest debe tener un atributo role.',
            'manifest_duplicate_id' => 'Los identificadores de los elementos del manifest deben ser únicos. Identificador duplicado: {id}.',
            'manifest_custom_x_roles_not_allowed' => 'Los roles personalizados que empiezan por x- no están permitidos para un paquete EGF 1.1 conforme.',
            'manifest_item_empty_href' => 'El elemento del manifest "{id}" tiene un href vacío.',
            'manifest_item_invalid_href' => 'El elemento del manifest "{id}" tiene un href no válido.',
            'manifest_item_missing_resource' => 'El elemento del manifest "{id}" referencia un recurso faltante: {path}.',
            'manifest_item_media_type_required' => 'El elemento del manifest "{id}" con href debe tener un atributo media-type.',
            'cover_must_be_png_or_jpeg' => 'El elemento egf_cover debe usar image/png o image/jpeg.',
            'max_wrong_answers_positive_integer' => 'El elemento max_wrong_answers debe tener un valor entero positivo.',
            'manifest_role_exactly_once' => 'El manifest debe contener exactamente un elemento con role="{role}".',

            'sequence_missing' => 'Falta el elemento sequence.',
            'sequence_children_scene_only' => 'Todos los elementos hijos de sequence deben ser elementos scene.',
            'scene_ref_required' => 'Cada escena debe tener un atributo ref.',
            'sequence_unknown_manifest_item' => 'La secuencia referencia un elemento del manifest desconocido: {ref}.',
            'sequence_duplicate_ref' => 'Cada ref de escena debe ser único en la secuencia para EGF 1.1. Ref duplicado: {ref}.',
            'sequence_minimum_scenes' => 'La secuencia debe contener al menos las escenas de título, felicitaciones, fin del juego y créditos.',
            'title_scene_first' => 'La escena Game Title debe ser la primera escena de la secuencia.',
            'credits_scene_last' => 'La escena Credits debe ser la última escena de la secuencia.',
            'game_over_scene_penultimate' => 'La escena Game Over debe ser la penúltima escena de la secuencia.',
            'congratulations_scene_antepenultimate' => 'La escena Congratulations debe ser la antepenúltima escena de la secuencia.',

            'settings_missing' => 'Falta el elemento settings.',
            'settings_children_setting_only' => 'Todos los elementos hijos de settings deben ser elementos setting.',
            'setting_ref_required' => 'Cada ajuste debe tener un atributo ref.',
            'settings_unknown_manifest_item' => 'Los ajustes referencian un elemento del manifest desconocido: {ref}.',
            'settings_max_wrong_answers_once' => 'Los ajustes deben contener exactamente un ajuste para max_wrong_answers.',

            'download' => 'Descargar',
            'play' => 'Jugar',
        ],
        'pt' => [
            'language' => 'Idioma',
            'apply' => 'Aplicar',
            'main_actions' => 'Ações principais',

            'tagline' => 'Um arquivo simples para partilhar e transferir jogos EGF.',
            'back_home' => '← Voltar ao início',
            'back_login' => '← Voltar ao início de sessão',
            'logged_in_as' => 'Sessão iniciada como {username}.',
            'account_settings' => 'Definições da conta',
            'logout' => 'Terminar sessão',
            'login' => 'Iniciar sessão',
            'create_account' => 'Criar uma conta',
            'upload_requires_account' => 'É necessária uma conta para enviar ficheiros.',
            'account_optional' => 'A conta é opcional.',
            'you_need_account_upload' => 'Precisa de uma conta para enviar ficheiros EGF.',
            'or' => 'ou',

            'upload_game' => 'Enviar um jogo',
            'upload_an_egf_game' => 'Enviar um jogo EGF',
            'display_all_games' => 'Mostrar todos os jogos',
            'available_games' => 'Jogos EGF disponíveis',
            'no_games' => 'Ainda não foi enviado nenhum jogo EGF.',
            'upload_another_game' => 'Enviar outro jogo',
            'maximum_upload_size' => 'Tamanho máximo de envio: {size}.',
            'rights_warning' => 'Antes de enviar, certifique-se de que possui os direitos do jogo EGF ou que ele é distribuído sob uma licença livre. Os ficheiros que não cumpram este requisito podem ser removidos.',
            'choose_egf_file' => 'Escolher um ficheiro .egf',

            'icon' => 'Ícone',
            'name' => 'Nome',
            'creator' => 'Criador',
            'description' => 'Descrição',
            'identifier' => 'Identificador',
            'modified' => 'Modificado',
            'size' => 'Tamanho',
            'uploaded_by' => 'Enviado por',
            'download' => 'Transferir',
            'delete' => 'Eliminar',
            'anonymous_upload' => 'Envio anónimo',

            'email' => 'E-mail',
            'change_email' => 'Alterar endereço de e-mail',
            'new_email' => 'Novo endereço de e-mail',
            'current_password' => 'Palavra-passe atual',
            'send_verification_email' => 'Enviar e-mail de verificação',
            'change_password' => 'Alterar palavra-passe',
            'new_password' => 'Nova palavra-passe',
            'confirm_new_password' => 'Confirmar nova palavra-passe',
            'update_password' => 'Atualizar palavra-passe',

            'uploaded_games' => 'Jogos enviados',
            'no_uploaded_games' => 'Ainda não enviou nenhum jogo EGF.',

            'delete_account' => 'Eliminar conta',
            'delete_account_warning' => 'Eliminar a sua conta é permanente. Os jogos EGF enviados continuarão disponíveis, mas serão apresentados como envios anónimos.',
            'delete_my_account' => 'Eliminar a minha conta',
            'delete_game_confirm' => 'Eliminar este jogo EGF? Esta ação não pode ser anulada.',
            'delete_account_confirm' => 'Eliminar a sua conta? Esta ação não pode ser anulada.',

            'reset_password' => 'Repor palavra-passe',
            'account_email_address' => 'Endereço de e-mail da conta',
            'send_reset_link' => 'Enviar ligação de reposição',
            'choose_new_password' => 'Escolher nova palavra-passe',

            'username' => 'Nome de utilizador',
            'password' => 'Palavra-passe',
            'forgot_password' => 'Esqueceu-se da palavra-passe?',
            'no_account_yet' => 'Ainda não tem conta?',
            'already_have_account' => 'Já tem uma conta?',
            'confirm_password' => 'Confirmar palavra-passe',

            'registration_disabled' => 'O registo está atualmente desativado.',
            'verify_email' => 'Verificar e-mail',
            'verify_email_change' => 'Verificar alteração de e-mail',

            'account_deleted' => 'A sua conta foi eliminada.',
            'game_deleted_success' => 'Jogo eliminado com sucesso.',
            'game_delete_failed' => 'Não foi possível eliminar este jogo.',
            'account_delete_failed' => 'Não foi possível eliminar a sua conta. Verifique a sua palavra-passe.',

            'missing_verification_token' => 'Token de verificação em falta.',
            'missing_password_reset_token' => 'Token de reposição de palavra-passe em falta.',
            'expired_account_link_help' => 'Se a ligação expirou, crie uma nova conta ou contacte o administrador.',
            'expired_email_change_help' => 'Se a ligação expirou, solicite uma nova alteração de e-mail nas definições da sua conta.',

            'invalid_account_action' => 'Ação de conta inválida.',
            'new_passwords_do_not_match' => 'As novas palavras-passe não coincidem.',
            'passwords_do_not_match' => 'As palavras-passe não coincidem.',

            'login_success' => 'Sessão iniciada com sucesso.',
            'invalid_username_or_password' => 'Nome de utilizador ou palavra-passe inválidos.',
            'login_temporarily_locked' => 'Demasiadas tentativas de início de sessão falhadas. Tente novamente em {duration}.',

            'file_uploads_disabled' => 'O envio de ficheiros está atualmente desativado.',
            'no_file_submitted' => 'Nenhum ficheiro foi submetido.',
            'upload_failed_code' => 'O envio falhou. Código de erro: {code}.',
            'only_egf_allowed' => 'Apenas ficheiros .egf são permitidos.',
            'uploaded_file_empty' => 'O ficheiro enviado está vazio.',
            'upload_too_large' => 'O ficheiro é demasiado grande. Tamanho máximo: {size}.',
            'upload_not_verified' => 'Não foi possível verificar o envio.',
            'invalid_egf_file' => 'Ficheiro EGF inválido ou não suportado. {errors}',
            'additional_validation_errors' => 'Foram encontrados erros de validação adicionais.',
            'fingerprint_failed' => 'Não foi possível verificar a impressão digital do ficheiro enviado.',
            'duplicate_egf_file' => 'Este ficheiro EGF já foi enviado.',
            'upload_success' => 'Ficheiro enviado com sucesso: {filename}',
            'upload_save_failed' => 'Não foi possível guardar o ficheiro enviado. Verifique se o diretório uploads tem permissão de escrita.',

            'duration_seconds' => '{count} segundos',
            'duration_minutes' => '{count} minutos',

            'invalid_email_address' => 'Introduza um endereço de e-mail válido.',
            'email_provider_not_allowed' => 'Este fornecedor de e-mail não é permitido.',
            'email_provider_not_authorized' => 'Este fornecedor de e-mail não está autorizado.',

            'verify_account_email_subject' => 'Verifique a sua conta EGF Station',
            'verify_account_email_body' => "Olá {username},\n\nVerifique a sua conta EGF Station abrindo esta ligação:\n\n{url}\n\nEsta ligação expira em 24 horas.\n\nSe não criou esta conta, pode ignorar este e-mail.\n",

            'invalid_verification_link' => 'Ligação de verificação inválida.',
            'user_account_not_found' => 'Conta de utilizador não encontrada.',
            'verification_link_expired' => 'Esta ligação de verificação expirou.',
            'could_not_verify_account' => 'Não foi possível verificar a conta.',
            'email_verified_login_now' => 'O seu endereço de e-mail foi verificado. Já pode iniciar sessão.',

            'not_allowed_delete_game' => 'Não tem autorização para eliminar este jogo.',
            'game_file_not_found' => 'Ficheiro do jogo não encontrado.',
            'could_not_delete_game_file' => 'Não foi possível eliminar o ficheiro do jogo.',

            'invalid_username_rules' => 'O nome de utilizador deve conter entre 3 e 32 caracteres: apenas letras, números, underscores ou hífenes.',
            'password_min_length' => 'A palavra-passe deve conter pelo menos 8 caracteres.',
            'username_taken' => 'Este nome de utilizador já está em uso.',
            'email_already_used' => 'Este endereço de e-mail já está a ser utilizado por outra conta.',
            'wait_before_creating_account' => 'Aguarde {duration} antes de criar outra conta.',
            'could_not_save_user_account' => 'Não foi possível guardar a conta de utilizador.',
            'account_created_email_not_sent' => 'A conta foi criada, mas não foi possível enviar o e-mail de verificação.',
            'account_created_check_email' => 'Conta criada com sucesso. Verifique o seu e-mail para confirmar a conta.',
            'account_created_success' => 'Conta criada com sucesso.',

            'account_invalid_email' => 'Esta conta não tem um endereço de e-mail válido.',
            'email_already_verified' => 'O endereço de e-mail já está verificado.',
            'verification_email_recently_sent' => 'Já foi enviado recentemente um e-mail de verificação. Aguarde {duration} antes de solicitar outro.',
            'could_not_save_verification_token' => 'Não foi possível guardar o novo token de verificação.',
            'could_not_send_verification_email' => 'Não foi possível enviar o e-mail de verificação.',
            'new_verification_email_sent' => 'Foi enviado um novo e-mail de verificação.',

            'verify_email_before_login_new_sent' => 'Verifique o seu endereço de e-mail antes de iniciar sessão. Foi enviado um novo e-mail de verificação.',
            'verify_email_before_login_send_failed' => 'Verifique o seu endereço de e-mail antes de iniciar sessão. No entanto, não foi possível enviar um novo e-mail de verificação: {message}',

            'email_change_subject' => 'Confirme o seu novo endereço de e-mail do EGF Station',
            'email_change_body' => "Olá {username},\n\nConfirme o seu novo endereço de e-mail abrindo esta ligação:\n\n{url}\n\nEsta ligação expira em 24 horas.\n\nSe não solicitou esta alteração, pode ignorar este e-mail.\n",

            'must_login_change_email' => 'Tem de iniciar sessão para alterar o seu endereço de e-mail.',
            'current_password_incorrect' => 'A palavra-passe atual está incorreta.',
            'email_unchanged' => 'Endereço de e-mail sem alterações.',
            'could_not_save_email_change' => 'Não foi possível guardar o pedido de alteração de e-mail.',
            'email_change_saved_email_not_sent' => 'O pedido de alteração de e-mail foi guardado, mas não foi possível enviar o e-mail de verificação.',
            'email_change_link_sent' => 'Foi enviada uma ligação de verificação para o seu novo endereço de e-mail.',

            'must_login_delete_account' => 'Tem de iniciar sessão para eliminar a sua conta.',
            'could_not_delete_account' => 'Não foi possível eliminar a conta.',

            'must_login_change_password' => 'Tem de iniciar sessão para alterar a sua palavra-passe.',
            'new_password_min_length' => 'A nova palavra-passe deve conter pelo menos 8 caracteres.',
            'could_not_update_password' => 'Não foi possível atualizar a palavra-passe.',
            'password_updated_success' => 'Palavra-passe atualizada com sucesso.',

            'email_verification_link_expired' => 'Esta ligação de verificação de e-mail expirou.',
            'could_not_update_email' => 'Não foi possível atualizar o endereço de e-mail.',
            'email_updated_success' => 'O seu endereço de e-mail foi atualizado com sucesso.',

            'password_reset_email_subject' => 'Reponha a sua palavra-passe do EGF Station',
            'password_reset_email_body' => "Olá {username},\n\nFoi solicitado o restabelecimento da palavra-passe da sua conta EGF Station.\n\nPode escolher uma nova palavra-passe abrindo esta ligação:\n\n{url}\n\nEsta ligação expira em 1 hora.\n\nSe não solicitou isto, pode ignorar este e-mail.\n",
            'password_reset_generic_message' => 'Se existir uma conta com este endereço de e-mail, foi enviada uma ligação de reposição.',
            'could_not_create_password_reset' => 'Não foi possível criar o pedido de reposição da palavra-passe.',
            'password_reset_link_expired' => 'Esta ligação de reposição de palavra-passe expirou.',
            'password_updated_login_now' => 'A sua palavra-passe foi atualizada. Já pode iniciar sessão.',
            'invalid_password_reset_link' => 'Ligação de reposição de palavra-passe inválida.',

            'could_not_determine_ip' => 'Não foi possível determinar o seu endereço IP.',
            'uploads_blocked_ip' => 'O envio de ficheiros não é permitido a partir do seu endereço IP.',
            'uploads_restricted_ip' => 'O envio de ficheiros está restrito a endereços IP autorizados.',
            'must_login_upload' => 'Tem de iniciar sessão para enviar ficheiros EGF.',

            'invalid_file_request' => 'Pedido de ficheiro inválido.',
            'file_not_found' => 'Ficheiro não encontrado.',
            'ziparchive_unavailable' => 'ZipArchive não está disponível.',
            'cover_not_found' => 'Capa não encontrada.',
            'could_not_open_egf_file' => 'Não foi possível abrir o ficheiro EGF.',
            'invalid_cover' => 'Capa inválida.',
            'method_not_allowed' => 'Método não permitido.',
            'invalid_security_token' => 'Token de segurança inválido.',

            'unknown_creator' => 'Criador desconhecido',
            'no_description' => 'Sem descrição',
            'no_identifier' => 'Sem identificador',
            'no_modification_date' => 'Sem data de modificação',

            'ziparchive_extension_unavailable' => 'A extensão PHP ZipArchive não está disponível.',
            'uploaded_file_cannot_be_read' => 'O ficheiro enviado não pode ser lido.',
            'not_valid_zip_archive' => 'O ficheiro não é uma archive ZIP válida.',

            'egf_package_incomplete' => 'O pacote EGF está incompleto.',
            'mimetype_must_be_first' => 'O ficheiro mimetype deve ser o primeiro ficheiro da archive.',
            'mimetype_missing' => 'O ficheiro mimetype está em falta.',
            'mimetype_must_not_be_compressed' => 'O ficheiro mimetype não deve estar comprimido.',
            'mimetype_invalid_size' => 'O ficheiro mimetype tem um tamanho inválido.',
            'mimetype_invalid_content' => 'O ficheiro mimetype deve conter exatamente: application/egf+zip.',

            'container_missing_or_too_large' => 'META-INF/container.xml está em falta ou é demasiado grande.',
            'container_not_valid' => 'META-INF/container.xml não é um contentor EGF válido.',
            'container_version_required' => 'O elemento container deve ter version="1.0".',
            'container_one_rootfiles' => 'O contentor deve conter exatamente um elemento rootfiles.',
            'container_one_rootfile' => 'O contentor deve conter exatamente um elemento rootfile.',
            'rootfile_path_invalid' => 'O atributo full-path do rootfile é inválido.',
            'rootfile_id_required' => 'O atributo id do rootfile é obrigatório.',

            'core_missing_or_too_large' => 'O ficheiro principal EGF está em falta ou é demasiado grande.',
            'core_must_have_egf_root' => 'O ficheiro principal EGF deve ter um elemento raiz <egf>.',
            'core_version_required' => 'O ficheiro principal EGF deve declarar um atributo version.',
            'egf_version_format' => 'A versão EGF deve usar o formato maior.menor, por exemplo 1.0 ou 1.1.',
            'egf_version_not_supported' => 'Esta versão EGF ainda não é suportada por este arquivo.',

            'metadata_missing' => 'O elemento metadata está em falta.',
            'metadata_title_required' => 'Os metadados devem conter um dc:title não vazio.',
            'metadata_language_required' => 'Os metadados devem conter um dc:language não vazio.',
            'metadata_modified_required' => 'Os metadados devem conter meta property="dcterms:modified".',
            'metadata_modified_utc' => 'O valor dcterms:modified deve ser uma data/hora UTC, por exemplo 2025-11-12T10:00:00Z.',

            'manifest_missing' => 'O elemento manifest está em falta.',
            'manifest_children_item_only' => 'Todos os elementos filhos de manifest devem ser elementos item.',
            'manifest_item_id_required' => 'Cada elemento do manifest deve ter um atributo id.',
            'manifest_item_role_required' => 'Cada elemento do manifest deve ter um atributo role.',
            'manifest_duplicate_id' => 'Os identificadores dos elementos do manifest devem ser únicos. Identificador duplicado: {id}.',
            'manifest_custom_x_roles_not_allowed' => 'Funções personalizadas que começam por x- não são permitidas para um pacote EGF 1.1 conforme.',
            'manifest_item_empty_href' => 'O elemento do manifest "{id}" tem um href vazio.',
            'manifest_item_invalid_href' => 'O elemento do manifest "{id}" tem um href inválido.',
            'manifest_item_missing_resource' => 'O elemento do manifest "{id}" referencia um recurso em falta: {path}.',
            'manifest_item_media_type_required' => 'O elemento do manifest "{id}" com href deve ter um atributo media-type.',
            'cover_must_be_png_or_jpeg' => 'O elemento egf_cover deve usar image/png ou image/jpeg.',
            'max_wrong_answers_positive_integer' => 'O elemento max_wrong_answers deve ter um valor inteiro positivo.',
            'manifest_role_exactly_once' => 'O manifest deve conter exatamente um elemento com role="{role}".',

            'sequence_missing' => 'O elemento sequence está em falta.',
            'sequence_children_scene_only' => 'Todos os elementos filhos de sequence devem ser elementos scene.',
            'scene_ref_required' => 'Cada cena deve ter um atributo ref.',
            'sequence_unknown_manifest_item' => 'A sequência referencia um elemento desconhecido do manifest: {ref}.',
            'sequence_duplicate_ref' => 'Cada ref de cena deve ser único na sequência para EGF 1.1. Ref duplicado: {ref}.',
            'sequence_minimum_scenes' => 'A sequência deve conter pelo menos as cenas de título, parabéns, fim de jogo e créditos.',
            'title_scene_first' => 'A cena Game Title deve ser a primeira cena da sequência.',
            'credits_scene_last' => 'A cena Credits deve ser a última cena da sequência.',
            'game_over_scene_penultimate' => 'A cena Game Over deve ser a penúltima cena da sequência.',
            'congratulations_scene_antepenultimate' => 'A cena Congratulations deve ser a antepenúltima cena da sequência.',

            'settings_missing' => 'O elemento settings está em falta.',
            'settings_children_setting_only' => 'Todos os elementos filhos de settings devem ser elementos setting.',
            'setting_ref_required' => 'Cada definição deve ter um atributo ref.',
            'settings_unknown_manifest_item' => 'As definições referenciam um elemento desconhecido do manifest: {ref}.',
            'settings_max_wrong_answers_once' => 'As definições devem conter exatamente uma definição para max_wrong_answers.',

            'download' => 'Transferir',
            'play' => 'Jogar',
        ],
        'zh' => [
            'language' => '语言',
            'apply' => '应用',
            'main_actions' => '主要操作',

            'tagline' => '一个用于分享和下载 EGF 游戏的简单存档。',
            'back_home' => '← 返回首页',
            'back_login' => '← 返回登录',
            'logged_in_as' => '已以 {username} 身份登录。',
            'account_settings' => '账户设置',
            'logout' => '退出登录',
            'login' => '登录',
            'create_account' => '创建账户',
            'upload_requires_account' => '上传需要账户。',
            'account_optional' => '账户是可选的。',
            'you_need_account_upload' => '你需要账户才能上传 EGF 文件。',
            'or' => '或',

            'upload_game' => '上传游戏',
            'upload_an_egf_game' => '上传 EGF 游戏',
            'display_all_games' => '显示所有游戏',
            'available_games' => '可用的 EGF 游戏',
            'no_games' => '尚未上传任何 EGF 游戏。',
            'upload_another_game' => '上传另一个游戏',
            'maximum_upload_size' => '最大上传大小：{size}。',
            'rights_warning' => '上传前请确保你拥有该 EGF 游戏的权利，或者该游戏以自由许可证发布。不符合此要求的文件可能会被删除。',
            'choose_egf_file' => '选择 .egf 文件',

            'icon' => '图标',
            'name' => '名称',
            'creator' => '创作者',
            'description' => '描述',
            'identifier' => '标识符',
            'modified' => '修改时间',
            'size' => '大小',
            'uploaded_by' => '上传者',
            'download' => '下载',
            'delete' => '删除',
            'anonymous_upload' => '匿名上传',

            'email' => '电子邮件',
            'change_email' => '更改电子邮件地址',
            'new_email' => '新的电子邮件地址',
            'current_password' => '当前密码',
            'send_verification_email' => '发送验证邮件',
            'change_password' => '更改密码',
            'new_password' => '新密码',
            'confirm_new_password' => '确认新密码',
            'update_password' => '更新密码',

            'uploaded_games' => '已上传的游戏',
            'no_uploaded_games' => '你还没有上传任何 EGF 游戏。',

            'delete_account' => '删除账户',
            'delete_account_warning' => '删除账户是永久操作。你上传的 EGF 游戏仍将可用，但会显示为匿名上传。',
            'delete_my_account' => '删除我的账户',
            'delete_game_confirm' => '删除这个 EGF 游戏？此操作无法撤销。',
            'delete_account_confirm' => '删除你的账户？此操作无法撤销。',

            'reset_password' => '重置密码',
            'account_email_address' => '账户电子邮件地址',
            'send_reset_link' => '发送重置链接',
            'choose_new_password' => '选择新密码',

            'username' => '用户名',
            'password' => '密码',
            'forgot_password' => '忘记密码？',
            'no_account_yet' => '还没有账户？',
            'already_have_account' => '已经有账户？',
            'confirm_password' => '确认密码',

            'registration_disabled' => '注册当前已禁用。',
            'verify_email' => '验证电子邮件',
            'verify_email_change' => '验证电子邮件更改',

            'account_deleted' => '你的账户已被删除。',
            'game_deleted_success' => '游戏已成功删除。',
            'game_delete_failed' => '无法删除此游戏。',
            'account_delete_failed' => '无法删除你的账户。请检查你的密码。',

            'missing_verification_token' => '缺少验证令牌。',
            'missing_password_reset_token' => '缺少密码重置令牌。',
            'expired_account_link_help' => '如果链接已过期，请创建新账户或联系管理员。',
            'expired_email_change_help' => '如果链接已过期，请在账户设置中重新请求更改电子邮件。',

            'invalid_account_action' => '无效的账户操作。',
            'new_passwords_do_not_match' => '两次输入的新密码不一致。',
            'passwords_do_not_match' => '两次输入的密码不一致。',

            'login_success' => '登录成功。',
            'invalid_username_or_password' => '用户名或密码无效。',
            'login_temporarily_locked' => '登录失败次数过多。请在 {duration} 后重试。',

            'file_uploads_disabled' => '文件上传当前已禁用。',
            'no_file_submitted' => '未提交任何文件。',
            'upload_failed_code' => '上传失败。错误代码：{code}。',
            'only_egf_allowed' => '只允许上传 .egf 文件。',
            'uploaded_file_empty' => '上传的文件为空。',
            'upload_too_large' => '文件太大。最大大小：{size}。',
            'upload_not_verified' => '无法验证此次上传。',
            'invalid_egf_file' => 'EGF 文件无效或不受支持。{errors}',
            'additional_validation_errors' => '还发现了其他验证错误。',
            'fingerprint_failed' => '无法验证上传文件的指纹。',
            'duplicate_egf_file' => '这个 EGF 文件已经上传过。',
            'upload_success' => '文件上传成功：{filename}',
            'upload_save_failed' => '无法保存上传的文件。请检查 uploads 目录是否可写。',

            'duration_seconds' => '{count} 秒',
            'duration_minutes' => '{count} 分钟',

            'invalid_email_address' => '请输入有效的电子邮件地址。',
            'email_provider_not_allowed' => '不允许使用此电子邮件服务提供商。',
            'email_provider_not_authorized' => '此电子邮件服务提供商未获授权。',

            'verify_account_email_subject' => '验证你的 EGF Station 账户',
            'verify_account_email_body' => "你好 {username}，\n\n请打开以下链接验证你的 EGF Station 账户：\n\n{url}\n\n此链接将在 24 小时后过期。\n\n如果你没有创建此账户，可以忽略这封邮件。\n",

            'invalid_verification_link' => '验证链接无效。',
            'user_account_not_found' => '未找到账户。',
            'verification_link_expired' => '此验证链接已过期。',
            'could_not_verify_account' => '无法验证账户。',
            'email_verified_login_now' => '你的电子邮件地址已验证。现在可以登录。',

            'not_allowed_delete_game' => '你没有权限删除这个游戏。',
            'game_file_not_found' => '未找到游戏文件。',
            'could_not_delete_game_file' => '无法删除游戏文件。',

            'invalid_username_rules' => '用户名必须包含 3 到 32 个字符，只能使用字母、数字、下划线或连字符。',
            'password_min_length' => '密码必须至少包含 8 个字符。',
            'username_taken' => '此用户名已被占用。',
            'email_already_used' => '此电子邮件地址已被另一个账户使用。',
            'wait_before_creating_account' => '请等待 {duration} 后再创建另一个账户。',
            'could_not_save_user_account' => '无法保存用户账户。',
            'account_created_email_not_sent' => '账户已创建，但无法发送验证邮件。',
            'account_created_check_email' => '账户创建成功。请检查你的电子邮件以验证账户。',
            'account_created_success' => '账户创建成功。',

            'account_invalid_email' => '此账户没有有效的电子邮件地址。',
            'email_already_verified' => '电子邮件地址已验证。',
            'verification_email_recently_sent' => '最近已经发送过验证邮件。请等待 {duration} 后再请求另一封。',
            'could_not_save_verification_token' => '无法保存新的验证令牌。',
            'could_not_send_verification_email' => '无法发送验证邮件。',
            'new_verification_email_sent' => '新的验证邮件已发送。',

            'verify_email_before_login_new_sent' => '登录前请先验证你的电子邮件地址。新的验证邮件已发送。',
            'verify_email_before_login_send_failed' => '登录前请先验证你的电子邮件地址。但是，无法发送新的验证邮件：{message}',

            'email_change_subject' => '确认你的新 EGF Station 电子邮件地址',
            'email_change_body' => "你好 {username}，\n\n请打开以下链接确认你的新电子邮件地址：\n\n{url}\n\n此链接将在 24 小时后过期。\n\n如果你没有请求此更改，可以忽略这封邮件。\n",

            'must_login_change_email' => '你必须登录才能更改电子邮件地址。',
            'current_password_incorrect' => '当前密码不正确。',
            'email_unchanged' => '电子邮件地址未更改。',
            'could_not_save_email_change' => '无法保存电子邮件更改请求。',
            'email_change_saved_email_not_sent' => '电子邮件更改请求已保存，但无法发送验证邮件。',
            'email_change_link_sent' => '验证链接已发送到你的新电子邮件地址。',

            'must_login_delete_account' => '你必须登录才能删除账户。',
            'could_not_delete_account' => '无法删除账户。',

            'must_login_change_password' => '你必须登录才能更改密码。',
            'new_password_min_length' => '新密码必须至少包含 8 个字符。',
            'could_not_update_password' => '无法更新密码。',
            'password_updated_success' => '密码更新成功。',

            'email_verification_link_expired' => '此电子邮件验证链接已过期。',
            'could_not_update_email' => '无法更新电子邮件地址。',
            'email_updated_success' => '你的电子邮件地址已成功更新。',

            'password_reset_email_subject' => '重置你的 EGF Station 密码',
            'password_reset_email_body' => "你好 {username}，\n\n有人请求重置你的 EGF Station 账户密码。\n\n你可以打开以下链接选择新密码：\n\n{url}\n\n此链接将在 1 小时后过期。\n\n如果你没有请求此操作，可以忽略这封邮件。\n",
            'password_reset_generic_message' => '如果有账户使用此电子邮件地址，重置链接已经发送。',
            'could_not_create_password_reset' => '无法创建密码重置请求。',
            'password_reset_link_expired' => '此密码重置链接已过期。',
            'password_updated_login_now' => '你的密码已更新。现在可以登录。',
            'invalid_password_reset_link' => '密码重置链接无效。',

            'could_not_determine_ip' => '无法确定你的 IP 地址。',
            'uploads_blocked_ip' => '不允许从你的 IP 地址上传文件。',
            'uploads_restricted_ip' => '文件上传仅限授权 IP 地址。',
            'must_login_upload' => '你必须登录才能上传 EGF 文件。',

            'invalid_file_request' => '无效的文件请求。',
            'file_not_found' => '未找到文件。',
            'ziparchive_unavailable' => 'ZipArchive 不可用。',
            'cover_not_found' => '未找到封面。',
            'could_not_open_egf_file' => '无法打开 EGF 文件。',
            'invalid_cover' => '封面无效。',
            'method_not_allowed' => '不允许使用此方法。',
            'invalid_security_token' => '安全令牌无效。',

            'unknown_creator' => '未知创作者',
            'no_description' => '无描述',
            'no_identifier' => '无标识符',
            'no_modification_date' => '无修改日期',

            'ziparchive_extension_unavailable' => 'PHP ZipArchive 扩展不可用。',
            'uploaded_file_cannot_be_read' => '无法读取上传的文件。',
            'not_valid_zip_archive' => '该文件不是有效的 ZIP 存档。',

            'egf_package_incomplete' => 'EGF 包不完整。',
            'mimetype_must_be_first' => 'mimetype 文件必须是存档中的第一个文件。',
            'mimetype_missing' => '缺少 mimetype 文件。',
            'mimetype_must_not_be_compressed' => 'mimetype 文件不能被压缩。',
            'mimetype_invalid_size' => 'mimetype 文件大小无效。',
            'mimetype_invalid_content' => 'mimetype 文件必须精确包含：application/egf+zip。',

            'container_missing_or_too_large' => 'META-INF/container.xml 缺失或过大。',
            'container_not_valid' => 'META-INF/container.xml 不是有效的 EGF 容器。',
            'container_version_required' => 'container 元素必须具有 version="1.0"。',
            'container_one_rootfiles' => '容器必须恰好包含一个 rootfiles 元素。',
            'container_one_rootfile' => '容器必须恰好包含一个 rootfile 元素。',
            'rootfile_path_invalid' => 'rootfile 的 full-path 属性无效。',
            'rootfile_id_required' => 'rootfile 的 id 属性是必需的。',

            'core_missing_or_too_large' => 'EGF 核心文件缺失或过大。',
            'core_must_have_egf_root' => 'EGF 核心文件必须具有 <egf> 根元素。',
            'core_version_required' => 'EGF 核心文件必须声明 version 属性。',
            'egf_version_format' => 'EGF 版本必须使用 major.minor 格式，例如 1.0 或 1.1。',
            'egf_version_not_supported' => '此存档尚不支持该 EGF 版本。',

            'metadata_missing' => '缺少 metadata 元素。',
            'metadata_title_required' => '元数据必须包含非空的 dc:title。',
            'metadata_language_required' => '元数据必须包含非空的 dc:language。',
            'metadata_modified_required' => '元数据必须包含 meta property="dcterms:modified"。',
            'metadata_modified_utc' => 'dcterms:modified 的值必须是 UTC dateTime，例如 2025-11-12T10:00:00Z。',

            'manifest_missing' => '缺少 manifest 元素。',
            'manifest_children_item_only' => 'manifest 的所有子元素都必须是 item 元素。',
            'manifest_item_id_required' => '每个 manifest item 都必须具有 id 属性。',
            'manifest_item_role_required' => '每个 manifest item 都必须具有 role 属性。',
            'manifest_duplicate_id' => 'manifest item 的 id 必须唯一。重复的 id：{id}。',
            'manifest_custom_x_roles_not_allowed' => '符合 EGF 1.1 的包不允许使用以 x- 开头的自定义 role。',
            'manifest_item_empty_href' => 'manifest item "{id}" 的 href 为空。',
            'manifest_item_invalid_href' => 'manifest item "{id}" 的 href 无效。',
            'manifest_item_missing_resource' => 'manifest item "{id}" 引用了缺失的资源：{path}。',
            'manifest_item_media_type_required' => '带有 href 的 manifest item "{id}" 必须具有 media-type 属性。',
            'cover_must_be_png_or_jpeg' => 'egf_cover item 必须使用 image/png 或 image/jpeg。',
            'max_wrong_answers_positive_integer' => 'max_wrong_answers item 必须具有正整数值。',
            'manifest_role_exactly_once' => 'manifest 必须恰好包含一个 role="{role}" 的 item。',

            'sequence_missing' => '缺少 sequence 元素。',
            'sequence_children_scene_only' => 'sequence 的所有子元素都必须是 scene 元素。',
            'scene_ref_required' => '每个 scene 都必须具有 ref 属性。',
            'sequence_unknown_manifest_item' => 'sequence 引用了未知的 manifest item：{ref}。',
            'sequence_duplicate_ref' => '对于 EGF 1.1，sequence 中每个 scene ref 都必须唯一。重复的 ref：{ref}。',
            'sequence_minimum_scenes' => 'sequence 必须至少包含标题、祝贺、游戏结束和制作人员名单场景。',
            'title_scene_first' => 'Game Title 场景必须是 sequence 中的第一个场景。',
            'credits_scene_last' => 'Credits 场景必须是 sequence 中的最后一个场景。',
            'game_over_scene_penultimate' => 'Game Over 场景必须是 sequence 中的倒数第二个场景。',
            'congratulations_scene_antepenultimate' => 'Congratulations 场景必须是 sequence 中的倒数第三个场景。',

            'settings_missing' => '缺少 settings 元素。',
            'settings_children_setting_only' => 'settings 的所有子元素都必须是 setting 元素。',
            'setting_ref_required' => '每个 setting 都必须具有 ref 属性。',
            'settings_unknown_manifest_item' => 'settings 引用了未知的 manifest item：{ref}。',
            'settings_max_wrong_answers_once' => 'settings 必须恰好包含一个 max_wrong_answers 设置。',

            'download' => '下载',
            'play' => '播放',
        ],
        'ar' => [
            'language' => 'اللغة',
            'apply' => 'تطبيق',
            'main_actions' => 'الإجراءات الرئيسية',

            'tagline' => 'أرشيف بسيط لمشاركة ألعاب EGF وتنزيلها.',
            'back_home' => '← العودة إلى الصفحة الرئيسية',
            'back_login' => '← العودة إلى تسجيل الدخول',
            'logged_in_as' => 'تم تسجيل الدخول باسم {username}.',
            'account_settings' => 'إعدادات الحساب',
            'logout' => 'تسجيل الخروج',
            'login' => 'تسجيل الدخول',
            'create_account' => 'إنشاء حساب',
            'upload_requires_account' => 'يتطلب رفع الملفات حسابًا.',
            'account_optional' => 'الحساب اختياري.',
            'you_need_account_upload' => 'تحتاج إلى حساب لرفع ملفات EGF.',
            'or' => 'أو',

            'upload_game' => 'رفع لعبة',
            'upload_an_egf_game' => 'رفع لعبة EGF',
            'display_all_games' => 'عرض كل الألعاب',
            'available_games' => 'ألعاب EGF المتاحة',
            'no_games' => 'لم يتم رفع أي ألعاب EGF بعد.',
            'upload_another_game' => 'رفع لعبة أخرى',
            'maximum_upload_size' => 'الحد الأقصى لحجم الرفع: {size}.',
            'rights_warning' => 'يرجى التأكد من أنك تملك حقوق لعبة EGF، أو أنها موزعة بموجب ترخيص حر، قبل رفعها. قد تُزال الملفات التي لا تستوفي هذا الشرط.',
            'choose_egf_file' => 'اختر ملف .egf',

            'icon' => 'الأيقونة',
            'name' => 'الاسم',
            'creator' => 'المنشئ',
            'description' => 'الوصف',
            'identifier' => 'المعرّف',
            'modified' => 'تاريخ التعديل',
            'size' => 'الحجم',
            'uploaded_by' => 'رُفع بواسطة',
            'download' => 'تنزيل',
            'delete' => 'حذف',
            'anonymous_upload' => 'رفع مجهول',

            'email' => 'البريد الإلكتروني',
            'change_email' => 'تغيير عنوان البريد الإلكتروني',
            'new_email' => 'عنوان البريد الإلكتروني الجديد',
            'current_password' => 'كلمة المرور الحالية',
            'send_verification_email' => 'إرسال رسالة التحقق',
            'change_password' => 'تغيير كلمة المرور',
            'new_password' => 'كلمة المرور الجديدة',
            'confirm_new_password' => 'تأكيد كلمة المرور الجديدة',
            'update_password' => 'تحديث كلمة المرور',

            'uploaded_games' => 'الألعاب المرفوعة',
            'no_uploaded_games' => 'لم ترفع أي ألعاب EGF بعد.',

            'delete_account' => 'حذف الحساب',
            'delete_account_warning' => 'حذف حسابك إجراء دائم. ستبقى ألعاب EGF التي رفعتها متاحة، لكنها ستظهر كرفعات مجهولة.',
            'delete_my_account' => 'حذف حسابي',
            'delete_game_confirm' => 'هل تريد حذف لعبة EGF هذه؟ لا يمكن التراجع عن هذا الإجراء.',
            'delete_account_confirm' => 'هل تريد حذف حسابك؟ لا يمكن التراجع عن هذا الإجراء.',

            'reset_password' => 'إعادة تعيين كلمة المرور',
            'account_email_address' => 'عنوان البريد الإلكتروني للحساب',
            'send_reset_link' => 'إرسال رابط إعادة التعيين',
            'choose_new_password' => 'اختر كلمة مرور جديدة',

            'username' => 'اسم المستخدم',
            'password' => 'كلمة المرور',
            'forgot_password' => 'هل نسيت كلمة المرور؟',
            'no_account_yet' => 'ليس لديك حساب بعد؟',
            'already_have_account' => 'هل لديك حساب بالفعل؟',
            'confirm_password' => 'تأكيد كلمة المرور',

            'registration_disabled' => 'التسجيل معطل حاليًا.',
            'verify_email' => 'تحقق من البريد الإلكتروني',
            'verify_email_change' => 'تحقق من تغيير البريد الإلكتروني',

            'account_deleted' => 'تم حذف حسابك.',
            'game_deleted_success' => 'تم حذف اللعبة بنجاح.',
            'game_delete_failed' => 'تعذر حذف هذه اللعبة.',
            'account_delete_failed' => 'تعذر حذف حسابك. يرجى التحقق من كلمة المرور.',

            'missing_verification_token' => 'رمز التحقق مفقود.',
            'missing_password_reset_token' => 'رمز إعادة تعيين كلمة المرور مفقود.',
            'expired_account_link_help' => 'إذا انتهت صلاحية الرابط، يرجى إنشاء حساب جديد أو الاتصال بالمسؤول.',
            'expired_email_change_help' => 'إذا انتهت صلاحية الرابط، فاطلب تغييرًا جديدًا للبريد الإلكتروني من إعدادات حسابك.',

            'invalid_account_action' => 'إجراء الحساب غير صالح.',
            'new_passwords_do_not_match' => 'كلمتا المرور الجديدتان غير متطابقتين.',
            'passwords_do_not_match' => 'كلمتا المرور غير متطابقتين.',

            'login_success' => 'تم تسجيل الدخول بنجاح.',
            'invalid_username_or_password' => 'اسم المستخدم أو كلمة المرور غير صحيح.',
            'login_temporarily_locked' => 'عدد كبير جدًا من محاولات تسجيل الدخول الفاشلة. يرجى المحاولة مرة أخرى خلال {duration}.',

            'file_uploads_disabled' => 'رفع الملفات معطل حاليًا.',
            'no_file_submitted' => 'لم يتم إرسال أي ملف.',
            'upload_failed_code' => 'فشل الرفع. رمز الخطأ: {code}.',
            'only_egf_allowed' => 'يُسمح فقط بملفات .egf.',
            'uploaded_file_empty' => 'الملف المرفوع فارغ.',
            'upload_too_large' => 'الملف كبير جدًا. الحد الأقصى للحجم: {size}.',
            'upload_not_verified' => 'تعذر التحقق من عملية الرفع.',
            'invalid_egf_file' => 'ملف EGF غير صالح أو غير مدعوم. {errors}',
            'additional_validation_errors' => 'تم العثور على أخطاء تحقق إضافية.',
            'fingerprint_failed' => 'تعذر التحقق من بصمة الملف المرفوع.',
            'duplicate_egf_file' => 'تم رفع ملف EGF هذا من قبل.',
            'upload_success' => 'تم رفع الملف بنجاح: {filename}',
            'upload_save_failed' => 'تعذر حفظ الملف المرفوع. تحقق من أن مجلد uploads قابل للكتابة.',

            'duration_seconds' => '{count} ثانية',
            'duration_minutes' => '{count} دقيقة',

            'invalid_email_address' => 'يرجى إدخال عنوان بريد إلكتروني صالح.',
            'email_provider_not_allowed' => 'مزود البريد الإلكتروني هذا غير مسموح به.',
            'email_provider_not_authorized' => 'مزود البريد الإلكتروني هذا غير مصرّح به.',

            'verify_account_email_subject' => 'تحقق من حسابك في EGF Station',
            'verify_account_email_body' => "مرحبًا {username}،\n\nيرجى التحقق من حسابك في EGF Station بفتح هذا الرابط:\n\n{url}\n\nتنتهي صلاحية هذا الرابط خلال 24 ساعة.\n\nإذا لم تنشئ هذا الحساب، يمكنك تجاهل هذه الرسالة.\n",

            'invalid_verification_link' => 'رابط التحقق غير صالح.',
            'user_account_not_found' => 'حساب المستخدم غير موجود.',
            'verification_link_expired' => 'انتهت صلاحية رابط التحقق هذا.',
            'could_not_verify_account' => 'تعذر التحقق من الحساب.',
            'email_verified_login_now' => 'تم التحقق من عنوان بريدك الإلكتروني. يمكنك الآن تسجيل الدخول.',

            'not_allowed_delete_game' => 'غير مسموح لك بحذف هذه اللعبة.',
            'game_file_not_found' => 'ملف اللعبة غير موجود.',
            'could_not_delete_game_file' => 'تعذر حذف ملف اللعبة.',

            'invalid_username_rules' => 'يجب أن يحتوي اسم المستخدم على 3 إلى 32 حرفًا: حروف أو أرقام أو شرطات سفلية أو شرطات فقط.',
            'password_min_length' => 'يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل.',
            'username_taken' => 'اسم المستخدم هذا مستخدم بالفعل.',
            'email_already_used' => 'عنوان البريد الإلكتروني هذا مستخدم بالفعل من قبل حساب آخر.',
            'wait_before_creating_account' => 'يرجى الانتظار {duration} قبل إنشاء حساب آخر.',
            'could_not_save_user_account' => 'تعذر حفظ حساب المستخدم.',
            'account_created_email_not_sent' => 'تم إنشاء الحساب، لكن تعذر إرسال رسالة التحقق.',
            'account_created_check_email' => 'تم إنشاء الحساب بنجاح. يرجى التحقق من بريدك الإلكتروني لتأكيد حسابك.',
            'account_created_success' => 'تم إنشاء الحساب بنجاح.',

            'account_invalid_email' => 'لا يحتوي هذا الحساب على عنوان بريد إلكتروني صالح.',
            'email_already_verified' => 'عنوان البريد الإلكتروني مُتحقق منه بالفعل.',
            'verification_email_recently_sent' => 'تم إرسال رسالة تحقق مؤخرًا. يرجى الانتظار {duration} قبل طلب رسالة أخرى.',
            'could_not_save_verification_token' => 'تعذر حفظ رمز التحقق الجديد.',
            'could_not_send_verification_email' => 'تعذر إرسال رسالة التحقق.',
            'new_verification_email_sent' => 'تم إرسال رسالة تحقق جديدة.',

            'verify_email_before_login_new_sent' => 'يرجى التحقق من عنوان بريدك الإلكتروني قبل تسجيل الدخول. تم إرسال رسالة تحقق جديدة.',
            'verify_email_before_login_send_failed' => 'يرجى التحقق من عنوان بريدك الإلكتروني قبل تسجيل الدخول. ومع ذلك، تعذر إرسال رسالة تحقق جديدة: {message}',

            'email_change_subject' => 'أكد عنوان بريدك الإلكتروني الجديد في EGF Station',
            'email_change_body' => "مرحبًا {username}،\n\nيرجى تأكيد عنوان بريدك الإلكتروني الجديد بفتح هذا الرابط:\n\n{url}\n\nتنتهي صلاحية هذا الرابط خلال 24 ساعة.\n\nإذا لم تطلب هذا التغيير، يمكنك تجاهل هذه الرسالة.\n",

            'must_login_change_email' => 'يجب تسجيل الدخول لتغيير عنوان بريدك الإلكتروني.',
            'current_password_incorrect' => 'كلمة المرور الحالية غير صحيحة.',
            'email_unchanged' => 'لم يتغير عنوان البريد الإلكتروني.',
            'could_not_save_email_change' => 'تعذر حفظ طلب تغيير البريد الإلكتروني.',
            'email_change_saved_email_not_sent' => 'تم حفظ طلب تغيير البريد الإلكتروني، لكن تعذر إرسال رسالة التحقق.',
            'email_change_link_sent' => 'تم إرسال رابط تحقق إلى عنوان بريدك الإلكتروني الجديد.',

            'must_login_delete_account' => 'يجب تسجيل الدخول لحذف حسابك.',
            'could_not_delete_account' => 'تعذر حذف الحساب.',

            'must_login_change_password' => 'يجب تسجيل الدخول لتغيير كلمة المرور.',
            'new_password_min_length' => 'يجب أن تحتوي كلمة المرور الجديدة على 8 أحرف على الأقل.',
            'could_not_update_password' => 'تعذر تحديث كلمة المرور.',
            'password_updated_success' => 'تم تحديث كلمة المرور بنجاح.',

            'email_verification_link_expired' => 'انتهت صلاحية رابط التحقق من البريد الإلكتروني هذا.',
            'could_not_update_email' => 'تعذر تحديث عنوان البريد الإلكتروني.',
            'email_updated_success' => 'تم تحديث عنوان بريدك الإلكتروني بنجاح.',

            'password_reset_email_subject' => 'أعد تعيين كلمة مرورك في EGF Station',
            'password_reset_email_body' => "مرحبًا {username}،\n\nتم طلب إعادة تعيين كلمة المرور لحسابك في EGF Station.\n\nيمكنك اختيار كلمة مرور جديدة بفتح هذا الرابط:\n\n{url}\n\nتنتهي صلاحية هذا الرابط خلال ساعة واحدة.\n\nإذا لم تطلب ذلك، يمكنك تجاهل هذه الرسالة.\n",
            'password_reset_generic_message' => 'إذا كان هناك حساب يستخدم عنوان البريد الإلكتروني هذا، فقد تم إرسال رابط إعادة تعيين كلمة المرور.',
            'could_not_create_password_reset' => 'تعذر إنشاء طلب إعادة تعيين كلمة المرور.',
            'password_reset_link_expired' => 'انتهت صلاحية رابط إعادة تعيين كلمة المرور هذا.',
            'password_updated_login_now' => 'تم تحديث كلمة المرور. يمكنك الآن تسجيل الدخول.',
            'invalid_password_reset_link' => 'رابط إعادة تعيين كلمة المرور غير صالح.',

            'could_not_determine_ip' => 'تعذر تحديد عنوان IP الخاص بك.',
            'uploads_blocked_ip' => 'لا يُسمح برفع الملفات من عنوان IP الخاص بك.',
            'uploads_restricted_ip' => 'رفع الملفات محدود بعناوين IP المصرح بها.',
            'must_login_upload' => 'يجب تسجيل الدخول لرفع ملفات EGF.',

            'invalid_file_request' => 'طلب الملف غير صالح.',
            'file_not_found' => 'الملف غير موجود.',
            'ziparchive_unavailable' => 'ZipArchive غير متاح.',
            'cover_not_found' => 'الغلاف غير موجود.',
            'could_not_open_egf_file' => 'تعذر فتح ملف EGF.',
            'invalid_cover' => 'الغلاف غير صالح.',
            'method_not_allowed' => 'الطريقة غير مسموح بها.',
            'invalid_security_token' => 'رمز الأمان غير صالح.',

            'unknown_creator' => 'منشئ غير معروف',
            'no_description' => 'لا يوجد وصف',
            'no_identifier' => 'لا يوجد معرّف',
            'no_modification_date' => 'لا يوجد تاريخ تعديل',

            'ziparchive_extension_unavailable' => 'امتداد PHP ZipArchive غير متاح.',
            'uploaded_file_cannot_be_read' => 'لا يمكن قراءة الملف المرفوع.',
            'not_valid_zip_archive' => 'الملف ليس أرشيف ZIP صالحًا.',

            'egf_package_incomplete' => 'حزمة EGF غير مكتملة.',
            'mimetype_must_be_first' => 'يجب أن يكون ملف mimetype هو أول ملف في الأرشيف.',
            'mimetype_missing' => 'ملف mimetype مفقود.',
            'mimetype_must_not_be_compressed' => 'يجب ألا يكون ملف mimetype مضغوطًا.',
            'mimetype_invalid_size' => 'حجم ملف mimetype غير صالح.',
            'mimetype_invalid_content' => 'يجب أن يحتوي ملف mimetype بالضبط على: application/egf+zip.',

            'container_missing_or_too_large' => 'ملف META-INF/container.xml مفقود أو كبير جدًا.',
            'container_not_valid' => 'ملف META-INF/container.xml ليس حاوية EGF صالحة.',
            'container_version_required' => 'يجب أن يحتوي عنصر container على version="1.0".',
            'container_one_rootfiles' => 'يجب أن تحتوي الحاوية على عنصر rootfiles واحد بالضبط.',
            'container_one_rootfile' => 'يجب أن تحتوي الحاوية على عنصر rootfile واحد بالضبط.',
            'rootfile_path_invalid' => 'السمة full-path في rootfile غير صالحة.',
            'rootfile_id_required' => 'السمة id في rootfile مطلوبة.',

            'core_missing_or_too_large' => 'ملف EGF الأساسي مفقود أو كبير جدًا.',
            'core_must_have_egf_root' => 'يجب أن يحتوي ملف EGF الأساسي على عنصر جذر <egf>.',
            'core_version_required' => 'يجب أن يعلن ملف EGF الأساسي عن سمة version.',
            'egf_version_format' => 'يجب أن تستخدم نسخة EGF تنسيق major.minor، مثل 1.0 أو 1.1.',
            'egf_version_not_supported' => 'هذه النسخة من EGF غير مدعومة في هذا الأرشيف بعد.',

            'metadata_missing' => 'عنصر metadata مفقود.',
            'metadata_title_required' => 'يجب أن تحتوي البيانات الوصفية على dc:title غير فارغ.',
            'metadata_language_required' => 'يجب أن تحتوي البيانات الوصفية على dc:language غير فارغ.',
            'metadata_modified_required' => 'يجب أن تحتوي البيانات الوصفية على meta property="dcterms:modified".',
            'metadata_modified_utc' => 'يجب أن تكون قيمة dcterms:modified من نوع dateTime بتوقيت UTC، مثل 2025-11-12T10:00:00Z.',

            'manifest_missing' => 'عنصر manifest مفقود.',
            'manifest_children_item_only' => 'يجب أن تكون كل العناصر الفرعية في manifest من نوع item.',
            'manifest_item_id_required' => 'يجب أن يحتوي كل عنصر في manifest على سمة id.',
            'manifest_item_role_required' => 'يجب أن يحتوي كل عنصر في manifest على سمة role.',
            'manifest_duplicate_id' => 'يجب أن تكون معرفات عناصر manifest فريدة. المعرّف المكرر: {id}.',
            'manifest_custom_x_roles_not_allowed' => 'الأدوار المخصصة التي تبدأ بـ x- غير مسموح بها لحزمة EGF 1.1 مطابقة.',
            'manifest_item_empty_href' => 'عنصر manifest "{id}" يحتوي على href فارغ.',
            'manifest_item_invalid_href' => 'عنصر manifest "{id}" يحتوي على href غير صالح.',
            'manifest_item_missing_resource' => 'عنصر manifest "{id}" يشير إلى مورد مفقود: {path}.',
            'manifest_item_media_type_required' => 'عنصر manifest "{id}" الذي يحتوي على href يجب أن يحتوي على سمة media-type.',
            'cover_must_be_png_or_jpeg' => 'يجب أن يستخدم عنصر egf_cover النوع image/png أو image/jpeg.',
            'max_wrong_answers_positive_integer' => 'يجب أن يحتوي عنصر max_wrong_answers على قيمة عدد صحيح موجب.',
            'manifest_role_exactly_once' => 'يجب أن يحتوي manifest على عنصر واحد بالضبط مع role="{role}".',

            'sequence_missing' => 'عنصر sequence مفقود.',
            'sequence_children_scene_only' => 'يجب أن تكون كل العناصر الفرعية في sequence من نوع scene.',
            'scene_ref_required' => 'يجب أن تحتوي كل scene على سمة ref.',
            'sequence_unknown_manifest_item' => 'يشير sequence إلى عنصر manifest غير معروف: {ref}.',
            'sequence_duplicate_ref' => 'يجب أن تكون كل ref في scene فريدة داخل sequence في EGF 1.1. ref مكرر: {ref}.',
            'sequence_minimum_scenes' => 'يجب أن يحتوي sequence على الأقل على مشاهد العنوان، والتهنئة، ونهاية اللعبة، والاعتمادات.',
            'title_scene_first' => 'يجب أن يكون مشهد Game Title هو المشهد الأول في sequence.',
            'credits_scene_last' => 'يجب أن يكون مشهد Credits هو المشهد الأخير في sequence.',
            'game_over_scene_penultimate' => 'يجب أن يكون مشهد Game Over هو المشهد قبل الأخير في sequence.',
            'congratulations_scene_antepenultimate' => 'يجب أن يكون مشهد Congratulations هو المشهد الثالث من النهاية في sequence.',

            'settings_missing' => 'عنصر settings مفقود.',
            'settings_children_setting_only' => 'يجب أن تكون كل العناصر الفرعية في settings من نوع setting.',
            'setting_ref_required' => 'يجب أن يحتوي كل setting على سمة ref.',
            'settings_unknown_manifest_item' => 'تشير settings إلى عنصر manifest غير معروف: {ref}.',
            'settings_max_wrong_answers_once' => 'يجب أن تحتوي settings على setting واحد بالضبط لـ max_wrong_answers.',

            'download' => 'تنزيل',
            'play' => 'تشغيل',
        ],
        'hi' => [
            'language' => 'भाषा',
            'apply' => 'लागू करें',
            'main_actions' => 'मुख्य कार्रवाइयाँ',

            'tagline' => 'EGF गेम साझा करने और डाउनलोड करने के लिए एक सरल संग्रह।',
            'back_home' => '← होम पर वापस जाएँ',
            'back_login' => '← लॉगिन पर वापस जाएँ',
            'logged_in_as' => '{username} के रूप में लॉग इन।',
            'account_settings' => 'खाता सेटिंग्स',
            'logout' => 'लॉग आउट',
            'login' => 'लॉग इन',
            'create_account' => 'खाता बनाएँ',
            'upload_requires_account' => 'अपलोड करने के लिए खाता आवश्यक है।',
            'account_optional' => 'खाता वैकल्पिक है।',
            'you_need_account_upload' => 'EGF फ़ाइलें अपलोड करने के लिए आपको खाते की आवश्यकता है।',
            'or' => 'या',

            'upload_game' => 'गेम अपलोड करें',
            'upload_an_egf_game' => 'EGF गेम अपलोड करें',
            'display_all_games' => 'सभी गेम दिखाएँ',
            'available_games' => 'उपलब्ध EGF गेम',
            'no_games' => 'अभी तक कोई EGF गेम अपलोड नहीं किया गया है।',
            'upload_another_game' => 'दूसरा गेम अपलोड करें',
            'maximum_upload_size' => 'अधिकतम अपलोड आकार: {size}।',
            'rights_warning' => 'अपलोड करने से पहले सुनिश्चित करें कि आपके पास EGF गेम के अधिकार हैं, या यह किसी मुक्त लाइसेंस के अंतर्गत वितरित है। इस आवश्यकता को पूरा न करने वाली फ़ाइलें हटाई जा सकती हैं।',
            'choose_egf_file' => '.egf फ़ाइल चुनें',

            'icon' => 'आइकन',
            'name' => 'नाम',
            'creator' => 'निर्माता',
            'description' => 'विवरण',
            'identifier' => 'पहचानकर्ता',
            'modified' => 'संशोधित',
            'size' => 'आकार',
            'uploaded_by' => 'अपलोड करने वाला',
            'download' => 'डाउनलोड',
            'delete' => 'हटाएँ',
            'anonymous_upload' => 'अनाम अपलोड',

            'email' => 'ईमेल',
            'change_email' => 'ईमेल पता बदलें',
            'new_email' => 'नया ईमेल पता',
            'current_password' => 'वर्तमान पासवर्ड',
            'send_verification_email' => 'सत्यापन ईमेल भेजें',
            'change_password' => 'पासवर्ड बदलें',
            'new_password' => 'नया पासवर्ड',
            'confirm_new_password' => 'नए पासवर्ड की पुष्टि करें',
            'update_password' => 'पासवर्ड अपडेट करें',

            'uploaded_games' => 'अपलोड किए गए गेम',
            'no_uploaded_games' => 'आपने अभी तक कोई EGF गेम अपलोड नहीं किया है।',

            'delete_account' => 'खाता हटाएँ',
            'delete_account_warning' => 'खाता हटाना स्थायी है। आपके अपलोड किए गए EGF गेम उपलब्ध रहेंगे, लेकिन वे अनाम अपलोड के रूप में दिखेंगे।',
            'delete_my_account' => 'मेरा खाता हटाएँ',
            'delete_game_confirm' => 'यह EGF गेम हटाएँ? यह क्रिया पूर्ववत नहीं की जा सकती।',
            'delete_account_confirm' => 'अपना खाता हटाएँ? यह क्रिया पूर्ववत नहीं की जा सकती।',

            'reset_password' => 'पासवर्ड रीसेट करें',
            'account_email_address' => 'खाते का ईमेल पता',
            'send_reset_link' => 'रीसेट लिंक भेजें',
            'choose_new_password' => 'नया पासवर्ड चुनें',

            'username' => 'उपयोगकर्ता नाम',
            'password' => 'पासवर्ड',
            'forgot_password' => 'पासवर्ड भूल गए?',
            'no_account_yet' => 'अभी तक खाता नहीं है?',
            'already_have_account' => 'पहले से खाता है?',
            'confirm_password' => 'पासवर्ड की पुष्टि करें',

            'registration_disabled' => 'पंजीकरण अभी अक्षम है।',
            'verify_email' => 'ईमेल सत्यापित करें',
            'verify_email_change' => 'ईमेल परिवर्तन सत्यापित करें',

            'account_deleted' => 'आपका खाता हटा दिया गया है।',
            'game_deleted_success' => 'गेम सफलतापूर्वक हटा दिया गया।',
            'game_delete_failed' => 'यह गेम हटाया नहीं जा सका।',
            'account_delete_failed' => 'आपका खाता हटाया नहीं जा सका। कृपया अपना पासवर्ड जाँचें।',

            'missing_verification_token' => 'सत्यापन टोकन अनुपस्थित है।',
            'missing_password_reset_token' => 'पासवर्ड रीसेट टोकन अनुपस्थित है।',
            'expired_account_link_help' => 'यदि लिंक की समय-सीमा समाप्त हो गई है, तो कृपया नया खाता बनाएँ या व्यवस्थापक से संपर्क करें।',
            'expired_email_change_help' => 'यदि लिंक की समय-सीमा समाप्त हो गई है, तो अपने खाते की सेटिंग्स से नया ईमेल परिवर्तन अनुरोध करें।',

            'invalid_account_action' => 'अमान्य खाता कार्रवाई।',
            'new_passwords_do_not_match' => 'नए पासवर्ड मेल नहीं खाते।',
            'passwords_do_not_match' => 'पासवर्ड मेल नहीं खाते।',

            'login_success' => 'लॉगिन सफल रहा।',
            'invalid_username_or_password' => 'उपयोगकर्ता नाम या पासवर्ड अमान्य है।',
            'login_temporarily_locked' => 'बहुत अधिक असफल लॉगिन प्रयास। कृपया {duration} में फिर से प्रयास करें।',

            'file_uploads_disabled' => 'फ़ाइल अपलोड अभी अक्षम हैं।',
            'no_file_submitted' => 'कोई फ़ाइल सबमिट नहीं की गई।',
            'upload_failed_code' => 'अपलोड विफल रहा। त्रुटि कोड: {code}।',
            'only_egf_allowed' => 'केवल .egf फ़ाइलों की अनुमति है।',
            'uploaded_file_empty' => 'अपलोड की गई फ़ाइल खाली है।',
            'upload_too_large' => 'फ़ाइल बहुत बड़ी है। अधिकतम आकार: {size}।',
            'upload_not_verified' => 'अपलोड सत्यापित नहीं किया जा सका।',
            'invalid_egf_file' => 'EGF फ़ाइल अमान्य या असमर्थित है। {errors}',
            'additional_validation_errors' => 'अतिरिक्त सत्यापन त्रुटियाँ मिलीं।',
            'fingerprint_failed' => 'अपलोड की गई फ़ाइल की फ़िंगरप्रिंट जाँची नहीं जा सकी।',
            'duplicate_egf_file' => 'यह EGF फ़ाइल पहले ही अपलोड की जा चुकी है।',
            'upload_success' => 'फ़ाइल सफलतापूर्वक अपलोड हुई: {filename}',
            'upload_save_failed' => 'अपलोड की गई फ़ाइल सहेजी नहीं जा सकी। जाँचें कि uploads निर्देशिका लिखने योग्य है।',

            'duration_seconds' => '{count} सेकंड',
            'duration_minutes' => '{count} मिनट',

            'invalid_email_address' => 'कृपया एक मान्य ईमेल पता दर्ज करें।',
            'email_provider_not_allowed' => 'यह ईमेल प्रदाता अनुमत नहीं है।',
            'email_provider_not_authorized' => 'यह ईमेल प्रदाता अधिकृत नहीं है।',

            'verify_account_email_subject' => 'अपना EGF Station खाता सत्यापित करें',
            'verify_account_email_body' => "नमस्ते {username},\n\nकृपया यह लिंक खोलकर अपना EGF Station खाता सत्यापित करें:\n\n{url}\n\nयह लिंक 24 घंटे में समाप्त हो जाएगा।\n\nयदि आपने यह खाता नहीं बनाया है, तो आप इस ईमेल को अनदेखा कर सकते हैं।\n",

            'invalid_verification_link' => 'सत्यापन लिंक अमान्य है।',
            'user_account_not_found' => 'उपयोगकर्ता खाता नहीं मिला।',
            'verification_link_expired' => 'इस सत्यापन लिंक की समय-सीमा समाप्त हो गई है।',
            'could_not_verify_account' => 'खाता सत्यापित नहीं किया जा सका।',
            'email_verified_login_now' => 'आपका ईमेल पता सत्यापित हो गया है। अब आप लॉग इन कर सकते हैं।',

            'not_allowed_delete_game' => 'आपको यह गेम हटाने की अनुमति नहीं है।',
            'game_file_not_found' => 'गेम फ़ाइल नहीं मिली।',
            'could_not_delete_game_file' => 'गेम फ़ाइल हटाई नहीं जा सकी।',

            'invalid_username_rules' => 'उपयोगकर्ता नाम में 3 से 32 वर्ण होने चाहिए: केवल अक्षर, अंक, अंडरस्कोर या हाइफ़न।',
            'password_min_length' => 'पासवर्ड में कम से कम 8 वर्ण होने चाहिए।',
            'username_taken' => 'यह उपयोगकर्ता नाम पहले से लिया जा चुका है।',
            'email_already_used' => 'यह ईमेल पता पहले से किसी दूसरे खाते द्वारा उपयोग किया जा रहा है।',
            'wait_before_creating_account' => 'दूसरा खाता बनाने से पहले कृपया {duration} प्रतीक्षा करें।',
            'could_not_save_user_account' => 'उपयोगकर्ता खाता सहेजा नहीं जा सका।',
            'account_created_email_not_sent' => 'खाता बना दिया गया, लेकिन सत्यापन ईमेल भेजा नहीं जा सका।',
            'account_created_check_email' => 'खाता सफलतापूर्वक बनाया गया। कृपया अपना खाता सत्यापित करने के लिए ईमेल देखें।',
            'account_created_success' => 'खाता सफलतापूर्वक बनाया गया।',

            'account_invalid_email' => 'इस खाते में मान्य ईमेल पता नहीं है।',
            'email_already_verified' => 'ईमेल पता पहले से सत्यापित है।',
            'verification_email_recently_sent' => 'हाल ही में सत्यापन ईमेल भेजा गया था। दूसरा अनुरोध करने से पहले कृपया {duration} प्रतीक्षा करें।',
            'could_not_save_verification_token' => 'नया सत्यापन टोकन सहेजा नहीं जा सका।',
            'could_not_send_verification_email' => 'सत्यापन ईमेल भेजा नहीं जा सका।',
            'new_verification_email_sent' => 'नया सत्यापन ईमेल भेज दिया गया है।',

            'verify_email_before_login_new_sent' => 'लॉग इन करने से पहले कृपया अपना ईमेल पता सत्यापित करें। नया सत्यापन ईमेल भेज दिया गया है।',
            'verify_email_before_login_send_failed' => 'लॉग इन करने से पहले कृपया अपना ईमेल पता सत्यापित करें। हालाँकि, नया सत्यापन ईमेल भेजा नहीं जा सका: {message}',

            'email_change_subject' => 'अपने नए EGF Station ईमेल पते की पुष्टि करें',
            'email_change_body' => "नमस्ते {username},\n\nकृपया यह लिंक खोलकर अपने नए ईमेल पते की पुष्टि करें:\n\n{url}\n\nयह लिंक 24 घंटे में समाप्त हो जाएगा।\n\nयदि आपने यह परिवर्तन अनुरोध नहीं किया है, तो आप इस ईमेल को अनदेखा कर सकते हैं।\n",

            'must_login_change_email' => 'ईमेल पता बदलने के लिए आपको लॉग इन होना होगा।',
            'current_password_incorrect' => 'वर्तमान पासवर्ड गलत है।',
            'email_unchanged' => 'ईमेल पता अपरिवर्तित है।',
            'could_not_save_email_change' => 'ईमेल परिवर्तन अनुरोध सहेजा नहीं जा सका।',
            'email_change_saved_email_not_sent' => 'ईमेल परिवर्तन अनुरोध सहेज लिया गया, लेकिन सत्यापन ईमेल भेजा नहीं जा सका।',
            'email_change_link_sent' => 'आपके नए ईमेल पते पर सत्यापन लिंक भेज दिया गया है।',

            'must_login_delete_account' => 'खाता हटाने के लिए आपको लॉग इन होना होगा।',
            'could_not_delete_account' => 'खाता हटाया नहीं जा सका।',

            'must_login_change_password' => 'पासवर्ड बदलने के लिए आपको लॉग इन होना होगा।',
            'new_password_min_length' => 'नए पासवर्ड में कम से कम 8 वर्ण होने चाहिए।',
            'could_not_update_password' => 'पासवर्ड अपडेट नहीं किया जा सका।',
            'password_updated_success' => 'पासवर्ड सफलतापूर्वक अपडेट किया गया।',

            'email_verification_link_expired' => 'इस ईमेल सत्यापन लिंक की समय-सीमा समाप्त हो गई है।',
            'could_not_update_email' => 'ईमेल पता अपडेट नहीं किया जा सका।',
            'email_updated_success' => 'आपका ईमेल पता सफलतापूर्वक अपडेट हो गया है।',

            'password_reset_email_subject' => 'अपना EGF Station पासवर्ड रीसेट करें',
            'password_reset_email_body' => "नमस्ते {username},\n\nआपके EGF Station खाते के लिए पासवर्ड रीसेट का अनुरोध किया गया था।\n\nआप यह लिंक खोलकर नया पासवर्ड चुन सकते हैं:\n\n{url}\n\nयह लिंक 1 घंटे में समाप्त हो जाएगा।\n\nयदि आपने यह अनुरोध नहीं किया है, तो आप इस ईमेल को अनदेखा कर सकते हैं।\n",
            'password_reset_generic_message' => 'यदि इस ईमेल पते से कोई खाता जुड़ा है, तो पासवर्ड रीसेट लिंक भेज दिया गया है।',
            'could_not_create_password_reset' => 'पासवर्ड रीसेट अनुरोध बनाया नहीं जा सका।',
            'password_reset_link_expired' => 'इस पासवर्ड रीसेट लिंक की समय-सीमा समाप्त हो गई है।',
            'password_updated_login_now' => 'आपका पासवर्ड अपडेट हो गया है। अब आप लॉग इन कर सकते हैं।',
            'invalid_password_reset_link' => 'पासवर्ड रीसेट लिंक अमान्य है।',

            'could_not_determine_ip' => 'आपका IP पता निर्धारित नहीं किया जा सका।',
            'uploads_blocked_ip' => 'आपके IP पते से फ़ाइल अपलोड की अनुमति नहीं है।',
            'uploads_restricted_ip' => 'फ़ाइल अपलोड केवल अधिकृत IP पतों तक सीमित है।',
            'must_login_upload' => 'EGF फ़ाइलें अपलोड करने के लिए आपको लॉग इन होना होगा।',

            'invalid_file_request' => 'अमान्य फ़ाइल अनुरोध।',
            'file_not_found' => 'फ़ाइल नहीं मिली।',
            'ziparchive_unavailable' => 'ZipArchive उपलब्ध नहीं है।',
            'cover_not_found' => 'कवर नहीं मिला।',
            'could_not_open_egf_file' => 'EGF फ़ाइल खोली नहीं जा सकी।',
            'invalid_cover' => 'कवर अमान्य है।',
            'method_not_allowed' => 'यह विधि अनुमत नहीं है।',
            'invalid_security_token' => 'सुरक्षा टोकन अमान्य है।',

            'unknown_creator' => 'अज्ञात निर्माता',
            'no_description' => 'कोई विवरण नहीं',
            'no_identifier' => 'कोई पहचानकर्ता नहीं',
            'no_modification_date' => 'कोई संशोधन तिथि नहीं',

            'ziparchive_extension_unavailable' => 'PHP ZipArchive एक्सटेंशन उपलब्ध नहीं है।',
            'uploaded_file_cannot_be_read' => 'अपलोड की गई फ़ाइल पढ़ी नहीं जा सकती।',
            'not_valid_zip_archive' => 'फ़ाइल मान्य ZIP आर्काइव नहीं है।',

            'egf_package_incomplete' => 'EGF पैकेज अधूरा है।',
            'mimetype_must_be_first' => 'mimetype फ़ाइल आर्काइव की पहली फ़ाइल होनी चाहिए।',
            'mimetype_missing' => 'mimetype फ़ाइल अनुपस्थित है।',
            'mimetype_must_not_be_compressed' => 'mimetype फ़ाइल संपीड़ित नहीं होनी चाहिए।',
            'mimetype_invalid_size' => 'mimetype फ़ाइल का आकार अमान्य है।',
            'mimetype_invalid_content' => 'mimetype फ़ाइल में ठीक यही होना चाहिए: application/egf+zip।',

            'container_missing_or_too_large' => 'META-INF/container.xml अनुपस्थित है या बहुत बड़ा है।',
            'container_not_valid' => 'META-INF/container.xml मान्य EGF कंटेनर नहीं है।',
            'container_version_required' => 'container तत्व में version="1.0" होना चाहिए।',
            'container_one_rootfiles' => 'कंटेनर में ठीक एक rootfiles तत्व होना चाहिए।',
            'container_one_rootfile' => 'कंटेनर में ठीक एक rootfile तत्व होना चाहिए।',
            'rootfile_path_invalid' => 'rootfile का full-path गुण अमान्य है।',
            'rootfile_id_required' => 'rootfile का id गुण आवश्यक है।',

            'core_missing_or_too_large' => 'EGF कोर फ़ाइल अनुपस्थित है या बहुत बड़ी है।',
            'core_must_have_egf_root' => 'EGF कोर फ़ाइल में <egf> रूट तत्व होना चाहिए।',
            'core_version_required' => 'EGF कोर फ़ाइल में version गुण घोषित होना चाहिए।',
            'egf_version_format' => 'EGF संस्करण major.minor प्रारूप में होना चाहिए, जैसे 1.0 या 1.1।',
            'egf_version_not_supported' => 'यह EGF संस्करण अभी इस आर्काइव द्वारा समर्थित नहीं है।',

            'metadata_missing' => 'metadata तत्व अनुपस्थित है।',
            'metadata_title_required' => 'मेटाडेटा में खाली न होने वाला dc:title होना चाहिए।',
            'metadata_language_required' => 'मेटाडेटा में खाली न होने वाला dc:language होना चाहिए।',
            'metadata_modified_required' => 'मेटाडेटा में meta property="dcterms:modified" होना चाहिए।',
            'metadata_modified_utc' => 'dcterms:modified का मान UTC dateTime होना चाहिए, जैसे 2025-11-12T10:00:00Z।',

            'manifest_missing' => 'manifest तत्व अनुपस्थित है।',
            'manifest_children_item_only' => 'manifest के सभी child तत्व item होने चाहिए।',
            'manifest_item_id_required' => 'प्रत्येक manifest item में id गुण होना चाहिए।',
            'manifest_item_role_required' => 'प्रत्येक manifest item में role गुण होना चाहिए।',
            'manifest_duplicate_id' => 'manifest item ids अद्वितीय होने चाहिए। डुप्लिकेट id: {id}।',
            'manifest_custom_x_roles_not_allowed' => 'x- से शुरू होने वाले कस्टम roles अनुरूप EGF 1.1 पैकेज के लिए अनुमत नहीं हैं।',
            'manifest_item_empty_href' => 'manifest item "{id}" का href खाली है।',
            'manifest_item_invalid_href' => 'manifest item "{id}" का href अमान्य है।',
            'manifest_item_missing_resource' => 'manifest item "{id}" एक अनुपस्थित संसाधन का संदर्भ देता है: {path}।',
            'manifest_item_media_type_required' => 'href वाले manifest item "{id}" में media-type गुण होना चाहिए।',
            'cover_must_be_png_or_jpeg' => 'egf_cover item को image/png या image/jpeg का उपयोग करना चाहिए।',
            'max_wrong_answers_positive_integer' => 'max_wrong_answers item में धनात्मक पूर्णांक मान होना चाहिए।',
            'manifest_role_exactly_once' => 'manifest में role="{role}" वाला ठीक एक item होना चाहिए।',

            'sequence_missing' => 'sequence तत्व अनुपस्थित है।',
            'sequence_children_scene_only' => 'sequence के सभी child तत्व scene होने चाहिए।',
            'scene_ref_required' => 'प्रत्येक scene में ref गुण होना चाहिए।',
            'sequence_unknown_manifest_item' => 'sequence एक अज्ञात manifest item का संदर्भ देता है: {ref}।',
            'sequence_duplicate_ref' => 'EGF 1.1 के लिए sequence में प्रत्येक scene ref अद्वितीय होना चाहिए। डुप्लिकेट ref: {ref}।',
            'sequence_minimum_scenes' => 'sequence में कम से कम title, congratulations, game over और credits scenes होने चाहिए।',
            'title_scene_first' => 'Game Title scene sequence में पहला scene होना चाहिए।',
            'credits_scene_last' => 'Credits scene sequence में अंतिम scene होना चाहिए।',
            'game_over_scene_penultimate' => 'Game Over scene sequence में अंतिम से दूसरा scene होना चाहिए।',
            'congratulations_scene_antepenultimate' => 'Congratulations scene sequence में अंतिम से तीसरा scene होना चाहिए।',

            'settings_missing' => 'settings तत्व अनुपस्थित है।',
            'settings_children_setting_only' => 'settings के सभी child तत्व setting होने चाहिए।',
            'setting_ref_required' => 'प्रत्येक setting में ref गुण होना चाहिए।',
            'settings_unknown_manifest_item' => 'settings एक अज्ञात manifest item का संदर्भ देता है: {ref}।',
            'settings_max_wrong_answers_once' => 'settings में max_wrong_answers के लिए ठीक एक setting होना चाहिए।',

            'download' => 'डाउनलोड',
            'play' => 'खेलें',
        ],
        'ur' => [
            'language' => 'زبان',
            'apply' => 'لاگو کریں',
            'main_actions' => 'اہم کارروائیاں',

            'tagline' => 'EGF گیمز شیئر اور ڈاؤن لوڈ کرنے کے لیے ایک سادہ آرکائیو۔',
            'back_home' => '← ہوم پر واپس جائیں',
            'back_login' => '← لاگ اِن پر واپس جائیں',
            'logged_in_as' => '{username} کے طور پر لاگ اِن ہیں۔',
            'account_settings' => 'اکاؤنٹ کی ترتیبات',
            'logout' => 'لاگ آؤٹ',
            'login' => 'لاگ اِن',
            'create_account' => 'اکاؤنٹ بنائیں',
            'upload_requires_account' => 'اپ لوڈ کرنے کے لیے اکاؤنٹ ضروری ہے۔',
            'account_optional' => 'اکاؤنٹ اختیاری ہے۔',
            'you_need_account_upload' => 'EGF فائلیں اپ لوڈ کرنے کے لیے آپ کو اکاؤنٹ چاہیے۔',
            'or' => 'یا',

            'upload_game' => 'گیم اپ لوڈ کریں',
            'upload_an_egf_game' => 'EGF گیم اپ لوڈ کریں',
            'display_all_games' => 'تمام گیمز دکھائیں',
            'available_games' => 'دستیاب EGF گیمز',
            'no_games' => 'ابھی تک کوئی EGF گیم اپ لوڈ نہیں ہوئی۔',
            'upload_another_game' => 'دوسری گیم اپ لوڈ کریں',
            'maximum_upload_size' => 'زیادہ سے زیادہ اپ لوڈ سائز: {size}۔',
            'rights_warning' => 'اپ لوڈ کرنے سے پہلے یقینی بنائیں کہ آپ EGF گیم کے حقوق رکھتے ہیں، یا یہ آزاد لائسنس کے تحت تقسیم کی گئی ہے۔ اس شرط کو پورا نہ کرنے والی فائلیں ہٹائی جا سکتی ہیں۔',
            'choose_egf_file' => '.egf فائل منتخب کریں',

            'icon' => 'آئیکن',
            'name' => 'نام',
            'creator' => 'تخلیق کار',
            'description' => 'تفصیل',
            'identifier' => 'شناخت کنندہ',
            'modified' => 'ترمیم شدہ',
            'size' => 'سائز',
            'uploaded_by' => 'اپ لوڈ کرنے والا',
            'download' => 'ڈاؤن لوڈ',
            'delete' => 'حذف کریں',
            'anonymous_upload' => 'گمنام اپ لوڈ',

            'email' => 'ای میل',
            'change_email' => 'ای میل پتہ تبدیل کریں',
            'new_email' => 'نیا ای میل پتہ',
            'current_password' => 'موجودہ پاس ورڈ',
            'send_verification_email' => 'تصدیقی ای میل بھیجیں',
            'change_password' => 'پاس ورڈ تبدیل کریں',
            'new_password' => 'نیا پاس ورڈ',
            'confirm_new_password' => 'نئے پاس ورڈ کی تصدیق کریں',
            'update_password' => 'پاس ورڈ اپ ڈیٹ کریں',

            'uploaded_games' => 'اپ لوڈ کردہ گیمز',
            'no_uploaded_games' => 'آپ نے ابھی تک کوئی EGF گیم اپ لوڈ نہیں کی۔',

            'delete_account' => 'اکاؤنٹ حذف کریں',
            'delete_account_warning' => 'اکاؤنٹ حذف کرنا مستقل ہے۔ آپ کی اپ لوڈ کردہ EGF گیمز دستیاب رہیں گی، مگر گمنام اپ لوڈ کے طور پر دکھائی دیں گی۔',
            'delete_my_account' => 'میرا اکاؤنٹ حذف کریں',
            'delete_game_confirm' => 'یہ EGF گیم حذف کریں؟ یہ عمل واپس نہیں کیا جا سکتا۔',
            'delete_account_confirm' => 'اپنا اکاؤنٹ حذف کریں؟ یہ عمل واپس نہیں کیا جا سکتا۔',

            'reset_password' => 'پاس ورڈ ری سیٹ کریں',
            'account_email_address' => 'اکاؤنٹ کا ای میل پتہ',
            'send_reset_link' => 'ری سیٹ لنک بھیجیں',
            'choose_new_password' => 'نیا پاس ورڈ منتخب کریں',

            'username' => 'صارف نام',
            'password' => 'پاس ورڈ',
            'forgot_password' => 'پاس ورڈ بھول گئے؟',
            'no_account_yet' => 'ابھی تک اکاؤنٹ نہیں؟',
            'already_have_account' => 'کیا پہلے سے اکاؤنٹ ہے؟',
            'confirm_password' => 'پاس ورڈ کی تصدیق کریں',

            'registration_disabled' => 'رجسٹریشن فی الحال غیر فعال ہے۔',
            'verify_email' => 'ای میل کی تصدیق کریں',
            'verify_email_change' => 'ای میل تبدیلی کی تصدیق کریں',

            'account_deleted' => 'آپ کا اکاؤنٹ حذف کر دیا گیا ہے۔',
            'game_deleted_success' => 'گیم کامیابی سے حذف کر دی گئی۔',
            'game_delete_failed' => 'یہ گیم حذف نہیں کی جا سکی۔',
            'account_delete_failed' => 'آپ کا اکاؤنٹ حذف نہیں کیا جا سکا۔ براہ کرم اپنا پاس ورڈ چیک کریں۔',

            'missing_verification_token' => 'تصدیقی ٹوکن موجود نہیں ہے۔',
            'missing_password_reset_token' => 'پاس ورڈ ری سیٹ ٹوکن موجود نہیں ہے۔',
            'expired_account_link_help' => 'اگر لنک کی مدت ختم ہو گئی ہے تو نیا اکاؤنٹ بنائیں یا منتظم سے رابطہ کریں۔',
            'expired_email_change_help' => 'اگر لنک کی مدت ختم ہو گئی ہے تو اپنے اکاؤنٹ کی ترتیبات سے ای میل تبدیلی کی نئی درخواست کریں۔',

            'invalid_account_action' => 'اکاؤنٹ کی کارروائی درست نہیں ہے۔',
            'new_passwords_do_not_match' => 'نئے پاس ورڈز مطابقت نہیں رکھتے۔',
            'passwords_do_not_match' => 'پاس ورڈز مطابقت نہیں رکھتے۔',

            'login_success' => 'لاگ اِن کامیاب رہا۔',
            'invalid_username_or_password' => 'صارف نام یا پاس ورڈ درست نہیں ہے۔',
            'login_temporarily_locked' => 'لاگ ان کی ناکام کوششوں کی تعداد بہت زیادہ ہے۔ براہ کرم {duration} میں دوبارہ کوشش کریں۔',

            'file_uploads_disabled' => 'فائل اپ لوڈز فی الحال غیر فعال ہیں۔',
            'no_file_submitted' => 'کوئی فائل جمع نہیں کرائی گئی۔',
            'upload_failed_code' => 'اپ لوڈ ناکام رہا۔ خرابی کا کوڈ: {code}۔',
            'only_egf_allowed' => 'صرف .egf فائلوں کی اجازت ہے۔',
            'uploaded_file_empty' => 'اپ لوڈ کی گئی فائل خالی ہے۔',
            'upload_too_large' => 'فائل بہت بڑی ہے۔ زیادہ سے زیادہ سائز: {size}۔',
            'upload_not_verified' => 'اپ لوڈ کی تصدیق نہیں کی جا سکی۔',
            'invalid_egf_file' => 'EGF فائل درست نہیں یا معاونت یافتہ نہیں ہے۔ {errors}',
            'additional_validation_errors' => 'اضافی توثیقی خرابیاں پائی گئیں۔',
            'fingerprint_failed' => 'اپ لوڈ کی گئی فائل کی فنگر پرنٹ تصدیق نہیں کی جا سکی۔',
            'duplicate_egf_file' => 'یہ EGF فائل پہلے ہی اپ لوڈ ہو چکی ہے۔',
            'upload_success' => 'فائل کامیابی سے اپ لوڈ ہو گئی: {filename}',
            'upload_save_failed' => 'اپ لوڈ کی گئی فائل محفوظ نہیں کی جا سکی۔ چیک کریں کہ uploads ڈائریکٹری لکھنے کے قابل ہے۔',

            'duration_seconds' => '{count} سیکنڈ',
            'duration_minutes' => '{count} منٹ',

            'invalid_email_address' => 'براہ کرم درست ای میل پتہ درج کریں۔',
            'email_provider_not_allowed' => 'یہ ای میل فراہم کنندہ مجاز نہیں ہے۔',
            'email_provider_not_authorized' => 'یہ ای میل فراہم کنندہ منظور شدہ نہیں ہے۔',

            'verify_account_email_subject' => 'اپنے EGF Station اکاؤنٹ کی تصدیق کریں',
            'verify_account_email_body' => "السلام علیکم {username}،\n\nبراہ کرم یہ لنک کھول کر اپنے EGF Station اکاؤنٹ کی تصدیق کریں:\n\n{url}\n\nیہ لنک 24 گھنٹوں میں ختم ہو جائے گا۔\n\nاگر آپ نے یہ اکاؤنٹ نہیں بنایا تو آپ اس ای میل کو نظر انداز کر سکتے ہیں۔\n",

            'invalid_verification_link' => 'تصدیقی لنک درست نہیں ہے۔',
            'user_account_not_found' => 'صارف اکاؤنٹ نہیں ملا۔',
            'verification_link_expired' => 'اس تصدیقی لنک کی مدت ختم ہو گئی ہے۔',
            'could_not_verify_account' => 'اکاؤنٹ کی تصدیق نہیں کی جا سکی۔',
            'email_verified_login_now' => 'آپ کا ای میل پتہ تصدیق شدہ ہے۔ اب آپ لاگ اِن کر سکتے ہیں۔',

            'not_allowed_delete_game' => 'آپ کو یہ گیم حذف کرنے کی اجازت نہیں ہے۔',
            'game_file_not_found' => 'گیم فائل نہیں ملی۔',
            'could_not_delete_game_file' => 'گیم فائل حذف نہیں کی جا سکی۔',

            'invalid_username_rules' => 'صارف نام میں 3 سے 32 حروف ہونے چاہئیں: صرف حروف، اعداد، انڈر اسکورز یا ہائفنز۔',
            'password_min_length' => 'پاس ورڈ کم از کم 8 حروف پر مشتمل ہونا چاہیے۔',
            'username_taken' => 'یہ صارف نام پہلے ہی استعمال ہو رہا ہے۔',
            'email_already_used' => 'یہ ای میل پتہ پہلے ہی کسی دوسرے اکاؤنٹ کے زیر استعمال ہے۔',
            'wait_before_creating_account' => 'دوسرا اکاؤنٹ بنانے سے پہلے براہ کرم {duration} انتظار کریں۔',
            'could_not_save_user_account' => 'صارف اکاؤنٹ محفوظ نہیں کیا جا سکا۔',
            'account_created_email_not_sent' => 'اکاؤنٹ بنا دیا گیا، لیکن تصدیقی ای میل بھیجی نہیں جا سکی۔',
            'account_created_check_email' => 'اکاؤنٹ کامیابی سے بن گیا۔ براہ کرم اپنے اکاؤنٹ کی تصدیق کے لیے اپنا ای میل چیک کریں۔',
            'account_created_success' => 'اکاؤنٹ کامیابی سے بن گیا۔',

            'account_invalid_email' => 'اس اکاؤنٹ میں درست ای میل پتہ نہیں ہے۔',
            'email_already_verified' => 'ای میل پتہ پہلے ہی تصدیق شدہ ہے۔',
            'verification_email_recently_sent' => 'حال ہی میں تصدیقی ای میل بھیجی جا چکی ہے۔ دوسری درخواست سے پہلے براہ کرم {duration} انتظار کریں۔',
            'could_not_save_verification_token' => 'نیا تصدیقی ٹوکن محفوظ نہیں کیا جا سکا۔',
            'could_not_send_verification_email' => 'تصدیقی ای میل نہیں بھیجی جا سکی۔',
            'new_verification_email_sent' => 'نئی تصدیقی ای میل بھیج دی گئی ہے۔',

            'verify_email_before_login_new_sent' => 'لاگ اِن کرنے سے پہلے براہ کرم اپنے ای میل پتے کی تصدیق کریں۔ نئی تصدیقی ای میل بھیج دی گئی ہے۔',
            'verify_email_before_login_send_failed' => 'لاگ اِن کرنے سے پہلے براہ کرم اپنے ای میل پتے کی تصدیق کریں۔ تاہم، نئی تصدیقی ای میل نہیں بھیجی جا سکی: {message}',

            'email_change_subject' => 'اپنے نئے EGF Station ای میل پتے کی تصدیق کریں',
            'email_change_body' => "السلام علیکم {username}،\n\nبراہ کرم یہ لنک کھول کر اپنے نئے ای میل پتے کی تصدیق کریں:\n\n{url}\n\nیہ لنک 24 گھنٹوں میں ختم ہو جائے گا۔\n\nاگر آپ نے یہ تبدیلی درخواست نہیں کی تو آپ اس ای میل کو نظر انداز کر سکتے ہیں۔\n",

            'must_login_change_email' => 'ای میل پتہ تبدیل کرنے کے لیے آپ کو لاگ اِن ہونا ضروری ہے۔',
            'current_password_incorrect' => 'موجودہ پاس ورڈ درست نہیں ہے۔',
            'email_unchanged' => 'ای میل پتہ تبدیل نہیں ہوا۔',
            'could_not_save_email_change' => 'ای میل تبدیلی کی درخواست محفوظ نہیں کی جا سکی۔',
            'email_change_saved_email_not_sent' => 'ای میل تبدیلی کی درخواست محفوظ ہو گئی، لیکن تصدیقی ای میل نہیں بھیجی جا سکی۔',
            'email_change_link_sent' => 'آپ کے نئے ای میل پتے پر تصدیقی لنک بھیج دیا گیا ہے۔',

            'must_login_delete_account' => 'اکاؤنٹ حذف کرنے کے لیے آپ کو لاگ اِن ہونا ضروری ہے۔',
            'could_not_delete_account' => 'اکاؤنٹ حذف نہیں کیا جا سکا۔',

            'must_login_change_password' => 'پاس ورڈ تبدیل کرنے کے لیے آپ کو لاگ اِن ہونا ضروری ہے۔',
            'new_password_min_length' => 'نیا پاس ورڈ کم از کم 8 حروف پر مشتمل ہونا چاہیے۔',
            'could_not_update_password' => 'پاس ورڈ اپ ڈیٹ نہیں کیا جا سکا۔',
            'password_updated_success' => 'پاس ورڈ کامیابی سے اپ ڈیٹ ہو گیا۔',

            'email_verification_link_expired' => 'اس ای میل تصدیقی لنک کی مدت ختم ہو گئی ہے۔',
            'could_not_update_email' => 'ای میل پتہ اپ ڈیٹ نہیں کیا جا سکا۔',
            'email_updated_success' => 'آپ کا ای میل پتہ کامیابی سے اپ ڈیٹ ہو گیا ہے۔',

            'password_reset_email_subject' => 'اپنا EGF Station پاس ورڈ ری سیٹ کریں',
            'password_reset_email_body' => "السلام علیکم {username}،\n\nآپ کے EGF Station اکاؤنٹ کے لیے پاس ورڈ ری سیٹ کی درخواست کی گئی تھی۔\n\nآپ یہ لنک کھول کر نیا پاس ورڈ منتخب کر سکتے ہیں:\n\n{url}\n\nیہ لنک 1 گھنٹے میں ختم ہو جائے گا۔\n\nاگر آپ نے یہ درخواست نہیں کی تو آپ اس ای میل کو نظر انداز کر سکتے ہیں۔\n",
            'password_reset_generic_message' => 'اگر اس ای میل پتے سے کوئی اکاؤنٹ منسلک ہے تو پاس ورڈ ری سیٹ لنک بھیج دیا گیا ہے۔',
            'could_not_create_password_reset' => 'پاس ورڈ ری سیٹ درخواست نہیں بنائی جا سکی۔',
            'password_reset_link_expired' => 'اس پاس ورڈ ری سیٹ لنک کی مدت ختم ہو گئی ہے۔',
            'password_updated_login_now' => 'آپ کا پاس ورڈ اپ ڈیٹ ہو گیا ہے۔ اب آپ لاگ اِن کر سکتے ہیں۔',
            'invalid_password_reset_link' => 'پاس ورڈ ری سیٹ لنک درست نہیں ہے۔',

            'could_not_determine_ip' => 'آپ کا IP پتہ معلوم نہیں کیا جا سکا۔',
            'uploads_blocked_ip' => 'آپ کے IP پتے سے فائل اپ لوڈ کرنے کی اجازت نہیں ہے۔',
            'uploads_restricted_ip' => 'فائل اپ لوڈز صرف مجاز IP پتوں تک محدود ہیں۔',
            'must_login_upload' => 'EGF فائلیں اپ لوڈ کرنے کے لیے آپ کو لاگ اِن ہونا ضروری ہے۔',

            'invalid_file_request' => 'فائل کی درخواست درست نہیں ہے۔',
            'file_not_found' => 'فائل نہیں ملی۔',
            'ziparchive_unavailable' => 'ZipArchive دستیاب نہیں ہے۔',
            'cover_not_found' => 'کور نہیں ملا۔',
            'could_not_open_egf_file' => 'EGF فائل نہیں کھولی جا سکی۔',
            'invalid_cover' => 'کور درست نہیں ہے۔',
            'method_not_allowed' => 'یہ طریقہ اجازت یافتہ نہیں ہے۔',
            'invalid_security_token' => 'سیکیورٹی ٹوکن درست نہیں ہے۔',

            'unknown_creator' => 'نامعلوم تخلیق کار',
            'no_description' => 'کوئی تفصیل نہیں',
            'no_identifier' => 'کوئی شناخت کنندہ نہیں',
            'no_modification_date' => 'ترمیم کی کوئی تاریخ نہیں',

            'ziparchive_extension_unavailable' => 'PHP ZipArchive ایکسٹینشن دستیاب نہیں ہے۔',
            'uploaded_file_cannot_be_read' => 'اپ لوڈ کی گئی فائل پڑھی نہیں جا سکتی۔',
            'not_valid_zip_archive' => 'فائل درست ZIP آرکائیو نہیں ہے۔',

            'egf_package_incomplete' => 'EGF پیکج نامکمل ہے۔',
            'mimetype_must_be_first' => 'mimetype فائل آرکائیو کی پہلی فائل ہونی چاہیے۔',
            'mimetype_missing' => 'mimetype فائل موجود نہیں ہے۔',
            'mimetype_must_not_be_compressed' => 'mimetype فائل کمپریسڈ نہیں ہونی چاہیے۔',
            'mimetype_invalid_size' => 'mimetype فائل کا سائز درست نہیں ہے۔',
            'mimetype_invalid_content' => 'mimetype فائل میں بالکل یہ ہونا چاہیے: application/egf+zip۔',

            'container_missing_or_too_large' => 'META-INF/container.xml موجود نہیں یا بہت بڑی ہے۔',
            'container_not_valid' => 'META-INF/container.xml درست EGF کنٹینر نہیں ہے۔',
            'container_version_required' => 'container عنصر میں version="1.0" ہونا چاہیے۔',
            'container_one_rootfiles' => 'کنٹینر میں بالکل ایک rootfiles عنصر ہونا چاہیے۔',
            'container_one_rootfile' => 'کنٹینر میں بالکل ایک rootfile عنصر ہونا چاہیے۔',
            'rootfile_path_invalid' => 'rootfile کا full-path وصف درست نہیں ہے۔',
            'rootfile_id_required' => 'rootfile کا id وصف ضروری ہے۔',

            'core_missing_or_too_large' => 'EGF کور فائل موجود نہیں یا بہت بڑی ہے۔',
            'core_must_have_egf_root' => 'EGF کور فائل میں <egf> جڑ عنصر ہونا چاہیے۔',
            'core_version_required' => 'EGF کور فائل میں version وصف کا اعلان ہونا چاہیے۔',
            'egf_version_format' => 'EGF ورژن major.minor فارمیٹ میں ہونا چاہیے، مثلاً 1.0 یا 1.1۔',
            'egf_version_not_supported' => 'یہ EGF ورژن ابھی اس آرکائیو میں معاونت یافتہ نہیں ہے۔',

            'metadata_missing' => 'metadata عنصر موجود نہیں ہے۔',
            'metadata_title_required' => 'میٹا ڈیٹا میں خالی نہ ہونے والا dc:title ہونا چاہیے۔',
            'metadata_language_required' => 'میٹا ڈیٹا میں خالی نہ ہونے والا dc:language ہونا چاہیے۔',
            'metadata_modified_required' => 'میٹا ڈیٹا میں meta property="dcterms:modified" ہونا چاہیے۔',
            'metadata_modified_utc' => 'dcterms:modified کی قدر UTC dateTime ہونی چاہیے، مثلاً 2025-11-12T10:00:00Z۔',

            'manifest_missing' => 'manifest عنصر موجود نہیں ہے۔',
            'manifest_children_item_only' => 'manifest کے تمام child عناصر item ہونے چاہئیں۔',
            'manifest_item_id_required' => 'ہر manifest item میں id وصف ہونا چاہیے۔',
            'manifest_item_role_required' => 'ہر manifest item میں role وصف ہونا چاہیے۔',
            'manifest_duplicate_id' => 'manifest item ids منفرد ہونے چاہئیں۔ ڈپلیکیٹ id: {id}۔',
            'manifest_custom_x_roles_not_allowed' => 'x- سے شروع ہونے والے custom roles مطابق EGF 1.1 پیکج کے لیے اجازت یافتہ نہیں ہیں۔',
            'manifest_item_empty_href' => 'manifest item "{id}" کا href خالی ہے۔',
            'manifest_item_invalid_href' => 'manifest item "{id}" کا href درست نہیں ہے۔',
            'manifest_item_missing_resource' => 'manifest item "{id}" ایک غیر موجود resource کا حوالہ دیتا ہے: {path}۔',
            'manifest_item_media_type_required' => 'href والے manifest item "{id}" میں media-type وصف ہونا چاہیے۔',
            'cover_must_be_png_or_jpeg' => 'egf_cover item کو image/png یا image/jpeg استعمال کرنا چاہیے۔',
            'max_wrong_answers_positive_integer' => 'max_wrong_answers item میں مثبت عددی قدر ہونی چاہیے۔',
            'manifest_role_exactly_once' => 'manifest میں role="{role}" والا بالکل ایک item ہونا چاہیے۔',

            'sequence_missing' => 'sequence عنصر موجود نہیں ہے۔',
            'sequence_children_scene_only' => 'sequence کے تمام child عناصر scene ہونے چاہئیں۔',
            'scene_ref_required' => 'ہر scene میں ref وصف ہونا چاہیے۔',
            'sequence_unknown_manifest_item' => 'sequence ایک نامعلوم manifest item کا حوالہ دیتا ہے: {ref}۔',
            'sequence_duplicate_ref' => 'EGF 1.1 کے لیے sequence میں ہر scene ref منفرد ہونا چاہیے۔ ڈپلیکیٹ ref: {ref}۔',
            'sequence_minimum_scenes' => 'sequence میں کم از کم title، congratulations، game over اور credits scenes ہونے چاہئیں۔',
            'title_scene_first' => 'Game Title scene کو sequence کا پہلا scene ہونا چاہیے۔',
            'credits_scene_last' => 'Credits scene کو sequence کا آخری scene ہونا چاہیے۔',
            'game_over_scene_penultimate' => 'Game Over scene کو sequence کا آخری سے دوسرا scene ہونا چاہیے۔',
            'congratulations_scene_antepenultimate' => 'Congratulations scene کو sequence کا آخری سے تیسرا scene ہونا چاہیے۔',

            'settings_missing' => 'settings عنصر موجود نہیں ہے۔',
            'settings_children_setting_only' => 'settings کے تمام child عناصر setting ہونے چاہئیں۔',
            'setting_ref_required' => 'ہر setting میں ref وصف ہونا چاہیے۔',
            'settings_unknown_manifest_item' => 'settings ایک نامعلوم manifest item کا حوالہ دیتی ہیں: {ref}۔',
            'settings_max_wrong_answers_once' => 'settings میں max_wrong_answers کے لیے بالکل ایک setting ہونی چاہیے۔',

            'download' => 'ڈاؤن لوڈ',
            'play' => 'کھیلیں',
        ],
        'ru' => [
            'language' => 'Язык',
            'apply' => 'Применить',
            'main_actions' => 'Основные действия',

            'tagline' => 'Простой архив для обмена и загрузки игр EGF.',
            'back_home' => '← Назад на главную',
            'back_login' => '← Назад ко входу',
            'logged_in_as' => 'Вы вошли как {username}.',
            'account_settings' => 'Настройки аккаунта',
            'logout' => 'Выйти',
            'login' => 'Войти',
            'create_account' => 'Создать аккаунт',
            'upload_requires_account' => 'Для загрузки требуется аккаунт.',
            'account_optional' => 'Аккаунт необязателен.',
            'you_need_account_upload' => 'Для загрузки файлов EGF нужен аккаунт.',
            'or' => 'или',

            'upload_game' => 'Загрузить игру',
            'upload_an_egf_game' => 'Загрузить игру EGF',
            'display_all_games' => 'Показать все игры',
            'available_games' => 'Доступные игры EGF',
            'no_games' => 'Пока не загружено ни одной игры EGF.',
            'upload_another_game' => 'Загрузить другую игру',
            'maximum_upload_size' => 'Максимальный размер загрузки: {size}.',
            'rights_warning' => 'Перед загрузкой убедитесь, что у вас есть права на игру EGF или что она распространяется по свободной лицензии. Файлы, не соответствующие этому требованию, могут быть удалены.',
            'choose_egf_file' => 'Выберите файл .egf',

            'icon' => 'Значок',
            'name' => 'Название',
            'creator' => 'Автор',
            'description' => 'Описание',
            'identifier' => 'Идентификатор',
            'modified' => 'Изменено',
            'size' => 'Размер',
            'uploaded_by' => 'Загрузил',
            'download' => 'Скачать',
            'delete' => 'Удалить',
            'anonymous_upload' => 'Анонимная загрузка',

            'email' => 'Эл. почта',
            'change_email' => 'Изменить адрес эл. почты',
            'new_email' => 'Новый адрес эл. почты',
            'current_password' => 'Текущий пароль',
            'send_verification_email' => 'Отправить письмо подтверждения',
            'change_password' => 'Изменить пароль',
            'new_password' => 'Новый пароль',
            'confirm_new_password' => 'Подтвердите новый пароль',
            'update_password' => 'Обновить пароль',

            'uploaded_games' => 'Загруженные игры',
            'no_uploaded_games' => 'Вы еще не загрузили ни одной игры EGF.',

            'delete_account' => 'Удалить аккаунт',
            'delete_account_warning' => 'Удаление аккаунта необратимо. Загруженные вами игры EGF останутся доступными, но будут показаны как анонимные загрузки.',
            'delete_my_account' => 'Удалить мой аккаунт',
            'delete_game_confirm' => 'Удалить эту игру EGF? Это действие нельзя отменить.',
            'delete_account_confirm' => 'Удалить ваш аккаунт? Это действие нельзя отменить.',

            'reset_password' => 'Сбросить пароль',
            'account_email_address' => 'Адрес эл. почты аккаунта',
            'send_reset_link' => 'Отправить ссылку для сброса',
            'choose_new_password' => 'Выберите новый пароль',

            'username' => 'Имя пользователя',
            'password' => 'Пароль',
            'forgot_password' => 'Забыли пароль?',
            'no_account_yet' => 'Еще нет аккаунта?',
            'already_have_account' => 'Уже есть аккаунт?',
            'confirm_password' => 'Подтвердите пароль',

            'registration_disabled' => 'Регистрация сейчас отключена.',
            'verify_email' => 'Подтвердить эл. почту',
            'verify_email_change' => 'Подтвердить изменение эл. почты',

            'account_deleted' => 'Ваш аккаунт удален.',
            'game_deleted_success' => 'Игра успешно удалена.',
            'game_delete_failed' => 'Не удалось удалить эту игру.',
            'account_delete_failed' => 'Не удалось удалить ваш аккаунт. Проверьте пароль.',

            'missing_verification_token' => 'Отсутствует токен подтверждения.',
            'missing_password_reset_token' => 'Отсутствует токен сброса пароля.',
            'expired_account_link_help' => 'Если срок действия ссылки истек, создайте новый аккаунт или свяжитесь с администратором.',
            'expired_email_change_help' => 'Если срок действия ссылки истек, запросите новое изменение эл. почты в настройках аккаунта.',

            'invalid_account_action' => 'Недопустимое действие с аккаунтом.',
            'new_passwords_do_not_match' => 'Новые пароли не совпадают.',
            'passwords_do_not_match' => 'Пароли не совпадают.',

            'login_success' => 'Вход выполнен успешно.',
            'invalid_username_or_password' => 'Недопустимое имя пользователя или пароль.',
            'login_temporarily_locked' => 'Слишком много неудачных попыток входа. Повторите попытку через {duration}.',

            'file_uploads_disabled' => 'Загрузка файлов сейчас отключена.',
            'no_file_submitted' => 'Файл не был отправлен.',
            'upload_failed_code' => 'Загрузка не удалась. Код ошибки: {code}.',
            'only_egf_allowed' => 'Разрешены только файлы .egf.',
            'uploaded_file_empty' => 'Загруженный файл пуст.',
            'upload_too_large' => 'Файл слишком большой. Максимальный размер: {size}.',
            'upload_not_verified' => 'Не удалось проверить загрузку.',
            'invalid_egf_file' => 'Файл EGF недопустим или не поддерживается. {errors}',
            'additional_validation_errors' => 'Обнаружены дополнительные ошибки проверки.',
            'fingerprint_failed' => 'Не удалось проверить отпечаток загруженного файла.',
            'duplicate_egf_file' => 'Этот файл EGF уже был загружен.',
            'upload_success' => 'Файл успешно загружен: {filename}',
            'upload_save_failed' => 'Не удалось сохранить загруженный файл. Проверьте, что каталог uploads доступен для записи.',

            'duration_seconds' => '{count} секунд',
            'duration_minutes' => '{count} минут',

            'invalid_email_address' => 'Введите действительный адрес эл. почты.',
            'email_provider_not_allowed' => 'Этот почтовый провайдер не разрешен.',
            'email_provider_not_authorized' => 'Этот почтовый провайдер не авторизован.',

            'verify_account_email_subject' => 'Подтвердите ваш аккаунт EGF Station',
            'verify_account_email_body' => "Здравствуйте, {username}!\n\nПодтвердите ваш аккаунт EGF Station, открыв эту ссылку:\n\n{url}\n\nСрок действия ссылки истекает через 24 часа.\n\nЕсли вы не создавали этот аккаунт, вы можете проигнорировать это письмо.\n",

            'invalid_verification_link' => 'Недействительная ссылка подтверждения.',
            'user_account_not_found' => 'Аккаунт пользователя не найден.',
            'verification_link_expired' => 'Срок действия этой ссылки подтверждения истек.',
            'could_not_verify_account' => 'Не удалось подтвердить аккаунт.',
            'email_verified_login_now' => 'Ваш адрес эл. почты подтвержден. Теперь вы можете войти.',

            'not_allowed_delete_game' => 'У вас нет разрешения удалить эту игру.',
            'game_file_not_found' => 'Файл игры не найден.',
            'could_not_delete_game_file' => 'Не удалось удалить файл игры.',

            'invalid_username_rules' => 'Имя пользователя должно содержать от 3 до 32 символов: только буквы, цифры, подчеркивания или дефисы.',
            'password_min_length' => 'Пароль должен содержать не менее 8 символов.',
            'username_taken' => 'Это имя пользователя уже занято.',
            'email_already_used' => 'Этот адрес эл. почты уже используется другим аккаунтом.',
            'wait_before_creating_account' => 'Подождите {duration}, прежде чем создавать другой аккаунт.',
            'could_not_save_user_account' => 'Не удалось сохранить аккаунт пользователя.',
            'account_created_email_not_sent' => 'Аккаунт был создан, но письмо подтверждения не удалось отправить.',
            'account_created_check_email' => 'Аккаунт успешно создан. Проверьте эл. почту, чтобы подтвердить аккаунт.',
            'account_created_success' => 'Аккаунт успешно создан.',

            'account_invalid_email' => 'У этого аккаунта нет действительного адреса эл. почты.',
            'email_already_verified' => 'Адрес эл. почты уже подтвержден.',
            'verification_email_recently_sent' => 'Письмо подтверждения уже было недавно отправлено. Подождите {duration}, прежде чем запросить другое.',
            'could_not_save_verification_token' => 'Не удалось сохранить новый токен подтверждения.',
            'could_not_send_verification_email' => 'Не удалось отправить письмо подтверждения.',
            'new_verification_email_sent' => 'Новое письмо подтверждения отправлено.',

            'verify_email_before_login_new_sent' => 'Подтвердите ваш адрес эл. почты перед входом. Новое письмо подтверждения отправлено.',
            'verify_email_before_login_send_failed' => 'Подтвердите ваш адрес эл. почты перед входом. Однако новое письмо подтверждения не удалось отправить: {message}',

            'email_change_subject' => 'Подтвердите новый адрес эл. почты для EGF Station',
            'email_change_body' => "Здравствуйте, {username}!\n\nПодтвердите ваш новый адрес эл. почты, открыв эту ссылку:\n\n{url}\n\nСрок действия ссылки истекает через 24 часа.\n\nЕсли вы не запрашивали это изменение, вы можете проигнорировать это письмо.\n",

            'must_login_change_email' => 'Вы должны войти, чтобы изменить адрес эл. почты.',
            'current_password_incorrect' => 'Текущий пароль неверен.',
            'email_unchanged' => 'Адрес эл. почты не изменен.',
            'could_not_save_email_change' => 'Не удалось сохранить запрос на изменение эл. почты.',
            'email_change_saved_email_not_sent' => 'Запрос на изменение эл. почты был сохранен, но письмо подтверждения не удалось отправить.',
            'email_change_link_sent' => 'Ссылка подтверждения отправлена на ваш новый адрес эл. почты.',

            'must_login_delete_account' => 'Вы должны войти, чтобы удалить аккаунт.',
            'could_not_delete_account' => 'Не удалось удалить аккаунт.',

            'must_login_change_password' => 'Вы должны войти, чтобы изменить пароль.',
            'new_password_min_length' => 'Новый пароль должен содержать не менее 8 символов.',
            'could_not_update_password' => 'Не удалось обновить пароль.',
            'password_updated_success' => 'Пароль успешно обновлен.',

            'email_verification_link_expired' => 'Срок действия этой ссылки подтверждения эл. почты истек.',
            'could_not_update_email' => 'Не удалось обновить адрес эл. почты.',
            'email_updated_success' => 'Ваш адрес эл. почты успешно обновлен.',

            'password_reset_email_subject' => 'Сбросьте пароль EGF Station',
            'password_reset_email_body' => "Здравствуйте, {username}!\n\nБыл запрошен сброс пароля для вашего аккаунта EGF Station.\n\nВы можете выбрать новый пароль, открыв эту ссылку:\n\n{url}\n\nСрок действия ссылки истекает через 1 час.\n\nЕсли вы не запрашивали это, вы можете проигнорировать это письмо.\n",
            'password_reset_generic_message' => 'Если аккаунт использует этот адрес эл. почты, ссылка для сброса пароля была отправлена.',
            'could_not_create_password_reset' => 'Не удалось создать запрос на сброс пароля.',
            'password_reset_link_expired' => 'Срок действия этой ссылки сброса пароля истек.',
            'password_updated_login_now' => 'Ваш пароль обновлен. Теперь вы можете войти.',
            'invalid_password_reset_link' => 'Недействительная ссылка сброса пароля.',

            'could_not_determine_ip' => 'Не удалось определить ваш IP-адрес.',
            'uploads_blocked_ip' => 'Загрузка файлов с вашего IP-адреса не разрешена.',
            'uploads_restricted_ip' => 'Загрузка файлов разрешена только с авторизованных IP-адресов.',
            'must_login_upload' => 'Вы должны войти, чтобы загружать файлы EGF.',

            'invalid_file_request' => 'Недопустимый запрос файла.',
            'file_not_found' => 'Файл не найден.',
            'ziparchive_unavailable' => 'ZipArchive недоступен.',
            'cover_not_found' => 'Обложка не найдена.',
            'could_not_open_egf_file' => 'Не удалось открыть файл EGF.',
            'invalid_cover' => 'Недопустимая обложка.',
            'method_not_allowed' => 'Метод не разрешен.',
            'invalid_security_token' => 'Недействительный токен безопасности.',

            'unknown_creator' => 'Неизвестный автор',
            'no_description' => 'Нет описания',
            'no_identifier' => 'Нет идентификатора',
            'no_modification_date' => 'Нет даты изменения',

            'ziparchive_extension_unavailable' => 'Расширение PHP ZipArchive недоступно.',
            'uploaded_file_cannot_be_read' => 'Загруженный файл не может быть прочитан.',
            'not_valid_zip_archive' => 'Файл не является действительным ZIP-архивом.',

            'egf_package_incomplete' => 'Пакет EGF неполный.',
            'mimetype_must_be_first' => 'Файл mimetype должен быть первым файлом в архиве.',
            'mimetype_missing' => 'Файл mimetype отсутствует.',
            'mimetype_must_not_be_compressed' => 'Файл mimetype не должен быть сжат.',
            'mimetype_invalid_size' => 'Файл mimetype имеет недопустимый размер.',
            'mimetype_invalid_content' => 'Файл mimetype должен содержать ровно: application/egf+zip.',

            'container_missing_or_too_large' => 'META-INF/container.xml отсутствует или слишком велик.',
            'container_not_valid' => 'META-INF/container.xml не является действительным контейнером EGF.',
            'container_version_required' => 'Элемент container должен иметь version="1.0".',
            'container_one_rootfiles' => 'Контейнер должен содержать ровно один элемент rootfiles.',
            'container_one_rootfile' => 'Контейнер должен содержать ровно один элемент rootfile.',
            'rootfile_path_invalid' => 'Атрибут full-path элемента rootfile недопустим.',
            'rootfile_id_required' => 'Атрибут id элемента rootfile обязателен.',

            'core_missing_or_too_large' => 'Основной файл EGF отсутствует или слишком велик.',
            'core_must_have_egf_root' => 'Основной файл EGF должен иметь корневой элемент <egf>.',
            'core_version_required' => 'Основной файл EGF должен объявлять атрибут version.',
            'egf_version_format' => 'Версия EGF должна использовать формат major.minor, например 1.0 или 1.1.',
            'egf_version_not_supported' => 'Эта версия EGF пока не поддерживается этим архивом.',

            'metadata_missing' => 'Элемент metadata отсутствует.',
            'metadata_title_required' => 'Метаданные должны содержать непустой dc:title.',
            'metadata_language_required' => 'Метаданные должны содержать непустой dc:language.',
            'metadata_modified_required' => 'Метаданные должны содержать meta property="dcterms:modified".',
            'metadata_modified_utc' => 'Значение dcterms:modified должно быть UTC dateTime, например 2025-11-12T10:00:00Z.',

            'manifest_missing' => 'Элемент manifest отсутствует.',
            'manifest_children_item_only' => 'Все дочерние элементы manifest должны быть элементами item.',
            'manifest_item_id_required' => 'Каждый элемент manifest item должен иметь атрибут id.',
            'manifest_item_role_required' => 'Каждый элемент manifest item должен иметь атрибут role.',
            'manifest_duplicate_id' => 'Идентификаторы элементов manifest должны быть уникальными. Повторяющийся id: {id}.',
            'manifest_custom_x_roles_not_allowed' => 'Пользовательские роли, начинающиеся с x-, не разрешены для соответствующего пакета EGF 1.1.',
            'manifest_item_empty_href' => 'Элемент manifest "{id}" имеет пустой href.',
            'manifest_item_invalid_href' => 'Элемент manifest "{id}" имеет недопустимый href.',
            'manifest_item_missing_resource' => 'Элемент manifest "{id}" ссылается на отсутствующий ресурс: {path}.',
            'manifest_item_media_type_required' => 'Элемент manifest "{id}" с href должен иметь атрибут media-type.',
            'cover_must_be_png_or_jpeg' => 'Элемент egf_cover должен использовать image/png или image/jpeg.',
            'max_wrong_answers_positive_integer' => 'Элемент max_wrong_answers должен иметь положительное целочисленное значение.',
            'manifest_role_exactly_once' => 'manifest должен содержать ровно один элемент с role="{role}".',

            'sequence_missing' => 'Элемент sequence отсутствует.',
            'sequence_children_scene_only' => 'Все дочерние элементы sequence должны быть элементами scene.',
            'scene_ref_required' => 'Каждая scene должна иметь атрибут ref.',
            'sequence_unknown_manifest_item' => 'sequence ссылается на неизвестный элемент manifest: {ref}.',
            'sequence_duplicate_ref' => 'Каждый scene ref должен быть уникальным в sequence для EGF 1.1. Повторяющийся ref: {ref}.',
            'sequence_minimum_scenes' => 'sequence должен содержать как минимум сцены title, congratulations, game over и credits.',
            'title_scene_first' => 'Сцена Game Title должна быть первой сценой в sequence.',
            'credits_scene_last' => 'Сцена Credits должна быть последней сценой в sequence.',
            'game_over_scene_penultimate' => 'Сцена Game Over должна быть предпоследней сценой в sequence.',
            'congratulations_scene_antepenultimate' => 'Сцена Congratulations должна быть третьей с конца сценой в sequence.',

            'settings_missing' => 'Элемент settings отсутствует.',
            'settings_children_setting_only' => 'Все дочерние элементы settings должны быть элементами setting.',
            'setting_ref_required' => 'Каждый setting должен иметь атрибут ref.',
            'settings_unknown_manifest_item' => 'settings ссылается на неизвестный элемент manifest: {ref}.',
            'settings_max_wrong_answers_once' => 'settings должен содержать ровно один setting для max_wrong_answers.',

            'download' => 'Скачать',
            'play' => 'Играть',
        ],
    ];
}
