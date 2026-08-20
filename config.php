<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Application
|--------------------------------------------------------------------------
*/

const APP_NAME      = "EGF Station";
const APP_VERSION   = "1.0";
const APP_BASE_URL  = "https://example.com";


/*
|--------------------------------------------------------------------------
| Storage paths
|--------------------------------------------------------------------------
*/

const UPLOAD_DIR                = __DIR__ . "/uploads";
const PUBLIC_UPLOAD_PATH        = "uploads";

const DATA_DIR                   = __DIR__ . "/data";
const USERS_FILE                 = DATA_DIR . "/users.json";
const RATE_LIMITS_FILE           = DATA_DIR . "/rate_limits.json";

/*
|--------------------------------------------------------------------------
| Upload settings
|--------------------------------------------------------------------------
*/

const UPLOADS_ENABLED = true;

const MAX_UPLOAD_BYTES          = 1024 * 1024 * 1024; // 1024 MB
const MAX_CONTAINER_XML_BYTES   = 1024 * 1024;       // 1 MB
const MAX_CORE_XML_BYTES        = 10 * 1024 * 1024;  // 10 MB

/*
 * If true, only logged-in users can upload EGF files.
 *
 * If false, uploads are public.
 * Users may still create accounts if REGISTRATION_ENABLED is true
 */
const REQUIRE_ACCOUNT_FOR_UPLOAD = false;


/*
|--------------------------------------------------------------------------
| Upload IP filtering
|--------------------------------------------------------------------------
|
| UPLOAD_BLOCKED_IPS always takes priority over UPLOAD_ALLOWED_IPS.
|
| If UPLOAD_ALLOWED_IPS is empty:
| - all IP addresses are allowed, except those in UPLOAD_BLOCKED_IPS.
|
| If UPLOAD_ALLOWED_IPS is not empty:
| - only listed IP addresses/ranges can upload.
|
| Accepted formats:
| - Exact IP:  "203.0.113.10"
| - IPv4 CIDR: "203.0.113.0/24"
| - IPv6 CIDR: "2001:db8::/32"
*/

const UPLOAD_ALLOWED_IPS = [];

const UPLOAD_BLOCKED_IPS = [];


/*
|--------------------------------------------------------------------------
| Account registration
|--------------------------------------------------------------------------
*/

/*
 * If true, visitors can create accounts from register.php.
 *
 * If false, only existing accounts can log in.
 * This is useful for private or invitation-only archives.
 */
const REGISTRATION_ENABLED = true;

/*
 * If true, new accounts must verify their email address before logging in.
 */
const EMAIL_VERIFICATION_REQUIRED = true;


/*
|--------------------------------------------------------------------------
| Email sending
|--------------------------------------------------------------------------
*/

const MAIL_FROM_EMAIL           = 'no-reply@example.com';
const MAIL_FROM_NAME            = 'EGF Station';


/*
|--------------------------------------------------------------------------
| Email provider filtering
|--------------------------------------------------------------------------
|
| Examples:
| - "gmail.com"
| - "outlook.com"
| - "proton.me"
| - "*.university.edu"
|
| If EMAIL_ALLOWED_DOMAINS is empty:
| - every provider is allowed, except blocked ones.
|
| If EMAIL_ALLOWED_DOMAINS is not empty:
| - only these providers are allowed.
|
| EMAIL_BLOCKED_DOMAINS always has priority.
*/

const EMAIL_ALLOWED_DOMAINS = [];

const EMAIL_BLOCKED_DOMAINS = [
    // 'yopmail.com',
    // 'mailinator.com',
    // 'tempmail.com',
];


/*
|--------------------------------------------------------------------------
| Tokens and cooldowns
|--------------------------------------------------------------------------
*/

const VERIFICATION_TOKEN_LIFETIME_SECONDS           = 24 * 60 * 60; // 24 hours
const PASSWORD_RESET_TOKEN_LIFETIME_SECONDS         = 60 * 60;      // 1 hour

const REGISTRATION_EMAIL_COOLDOWN_SECONDS           = 10 * 60;      // 10 minutes
const VERIFICATION_EMAIL_RESEND_COOLDOWN_SECONDS    = 10 * 60;     // 10 minutes
const PASSWORD_RESET_EMAIL_COOLDOWN_SECONDS         = 10 * 60;      // 10 minutes

// Login brute-force protection
const LOGIN_MAX_FAILED_ATTEMPTS                     = 5;            // max failed attempts
const LOGIN_LOCKOUT_SECONDS                         = 15 * 60;      // 15 minutes lockout

function getAppBaseUrl(): string
{
    if (defined('APP_BASE_URL') && trim(APP_BASE_URL) !== '')
    {
        return rtrim(APP_BASE_URL, '/');
    }

    $https = $_SERVER['HTTPS'] ?? '';
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

    if ($forwardedProto === 'https')
    {
        $scheme = 'https';
    }
    else
    {
        $scheme = (!empty($https) && $https !== 'off') ? 'https' : 'http';
    }

    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host) ?? 'localhost';

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $directory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    if ($directory === '.' || $directory === '/')
    {
        $directory = '';
    }

    return $scheme . '://' . $host . $directory;
}

function normalizeEmail(string $email): string
{
    return strtolower(trim($email));
}

function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function getEmailDomain(string $email): string
{
    $email = normalizeEmail($email);

    $atPosition = strrpos($email, '@');

    if ($atPosition === false)
    {
        return '';
    }

    return strtolower(substr($email, $atPosition + 1));
}

function emailDomainMatchesRule(string $domain, string $rule): bool
{
    $domain = strtolower(trim($domain));
    $rule = strtolower(trim($rule));

    if ($domain === '' || $rule === '')
    {
        return false;
    }

    if (str_starts_with($rule, '*.'))
    {
        $baseDomain = substr($rule, 2);

        return $domain !== $baseDomain && str_ends_with($domain, '.' . $baseDomain);
    }

    return $domain === $rule;
}

function emailDomainMatchesAnyRule(string $domain, array $rules): bool
{
    foreach ($rules as $rule)
    {
        if (emailDomainMatchesRule($domain, (string) $rule))
        {
            return true;
        }
    }

    return false;
}

function validateEmailProvider(string $email): array
{
    if (!isValidEmail($email))
    {
        return [
            'valid' => false,
            'message' => t('invalid_email_address'),
        ];
    }

    $domain = getEmailDomain($email);

    if ($domain === '')
    {
        return [
            'valid' => false,
            'message' => t('invalid_email_address'),
        ];
    }

    if (emailDomainMatchesAnyRule($domain, EMAIL_BLOCKED_DOMAINS))
    {
        return [
            'valid' => false,
            'message' => t('email_provider_not_allowed'),
        ];
    }

    if (count(EMAIL_ALLOWED_DOMAINS) > 0 && !emailDomainMatchesAnyRule($domain, EMAIL_ALLOWED_DOMAINS))
    {
        return [
            'valid' => false,
            'message' => t('email_provider_not_authorized'),
        ];
    }

    return [
        'valid' => true,
        'message' => '',
    ];
}

function generateVerificationToken(): string
{
    return bin2hex(random_bytes(32));
}

function sendVerificationEmail(string $email, string $username, string $token): bool
{
    $verificationUrl = getAppBaseUrl() . '/verify-email.php?token=' . rawurlencode($token);

    $subject = t('verify_account_email_subject');

    $body = t('verify_account_email_body', [
        'username' => $username,
        'url' => $verificationUrl,
    ]);

    $headers = [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_EMAIL . '>',
        'Reply-To: ' . MAIL_FROM_EMAIL,
        'Content-Type: text/plain; charset=UTF-8',
    ];

    return mail($email, $subject, $body, implode("\r\n", $headers));
}

function findUserByVerificationToken(string $token): ?string
{
    if ($token === '')
    {
        return null;
    }

    $tokenHash = hash('sha256', $token);
    $users = loadUsers();

    foreach ($users as $username => $user)
    {
        if (!isset($user['verification_token_hash']))
        {
            continue;
        }

        if (hash_equals( (string) $user['verification_token_hash'], $tokenHash) )
        {
            return (string) $username;
        }
    }

    return null;
}

function verifyUserEmail(string $token): array
{
    $username = findUserByVerificationToken($token);

    if ($username === null)
    {
        return [
            'success' => false,
            'message' => t('invalid_verification_link'),
        ];
    }

    $users = loadUsers();

    if (!isset($users[$username]))
    {
        return [
            'success' => false,
            'message' => t('user_account_not_found'),
        ];
    }

    $expiresAt = (int) ($users[$username]['verification_expires_at'] ?? 0);

    if ($expiresAt < time())
    {
        return [
            'success' => false,
            'message' => t('verification_link_expired'),
        ];
    }

    $users[$username]['email_verified'] = true;

    unset(
        $users[$username]['verification_token_hash'],
        $users[$username]['verification_expires_at'],
        $users[$username]['verification_email_sent_at']
    );

    if (!saveUsers($users))
    {
        return [
            'success' => false,
            'message' => t('could_not_verify_account'),
        ];
    }

    return [
        'success' => true,
        'message' => t('email_verified_login_now'),
    ];
}

function ensureUploadDirectory(): void
{
    if ( !is_dir(UPLOAD_DIR) )
    {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // Best-effort Apache protection. This file is ignored by non-Apache servers.

    $htaccess = UPLOAD_DIR . "/.htaccess";
    
    if ( !file_exists($htaccess) )
    {
        file_put_contents(
            $htaccess,
            "Options -Indexes\n" .
            "php_flag engine off\n" .
            "RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8\n" .
            "<FilesMatch \"\\.(sha1|json)$\">\n" .
            "    Require all denied\n" .
            "</FilesMatch>\n"
        );
    }
}

function humanFileSize(int $bytes): string
{
    $units  = ['B', 'KB', 'MB', 'GB'];
    $size   = (float) $bytes;

    foreach ($units as $unit)
    {
        if ($size < 1024 || $unit === 'GB')
        {
            return rtrim(rtrim(number_format($size, 2), '0'), '.') . ' ' . $unit;
        }

        $size /= 1024;
    }

    return $bytes . ' B';
}

function sanitizeBaseName(string $filename): string
{
    $filename = basename($filename);
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'file.egf';
    $filename = trim($filename, '._-');

    if ($filename === '')
    {
        $filename = 'file.egf';
    }

    return $filename;
}

function uniqueUploadName(string $filename): string
{
    $safeName = sanitizeBaseName($filename);
    $extension = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
    $baseName = pathinfo($safeName, PATHINFO_FILENAME);

    if ($extension !== "egf")
    {
        $safeName = $baseName . '.' . "egf";
    }

    $target = UPLOAD_DIR . '/' . $safeName;

    if ( !file_exists($target) )
    {
        return $safeName;
    }

    $counter = 1;
    do {
        $candidate = $baseName . '-' . date('Ymd-His') . '-' . $counter . '.' . "egf";
        $target = UPLOAD_DIR . '/' . $candidate;
        $counter++;
    } while (file_exists($target));

    return $candidate;
}

function normalizeZipInternalPath(string $path): ?string
{
    $path = str_replace('\\', '/', $path);
    $path = ltrim($path, '/');

    if ($path === '' || strpos($path, "\0") !== false || preg_match('~(^|/)\.\.(/|$)~', $path))
    {
        return null;
    }

    return $path;
}

function getZipEntryContents(ZipArchive $zip, string $entryName, int $maxBytes): ?string
{
    $index = $zip->locateName($entryName);

    if ($index === false)
    {
        return null;
    }

    $stat = $zip->statIndex($index);

    if (!is_array($stat) || (int) ($stat['size'] ?? 0) > $maxBytes)
    {
        return null;
    }

    $contents = $zip->getFromIndex($index);

    return is_string($contents) ? $contents : null;
}

function normalizeMetadataValue(string $value): string
{
    return preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
}

function resolveZipRelativePath(string $baseFilePath, string $href): ?string
{
    $href = str_replace('\\', '/', trim($href));

    if ($href === '' || str_starts_with($href, '/') || preg_match('~^[A-Za-z][A-Za-z0-9+.-]*:~', $href))
    {
        return null;
    }

    $baseFilePath = str_replace('\\', '/', $baseFilePath);
    $baseDirectory = dirname($baseFilePath);

    if ($baseDirectory === '.')
    {
        $baseDirectory = '';
    }

    $combinedPath = ($baseDirectory !== '' ? $baseDirectory . '/' : '') . $href;
    $parts = [];

    foreach (explode('/', $combinedPath) as $part)
    {
        if ($part === '' || $part === '.')
        {
            continue;
        }

        if ($part === '..')
        {
            if (count($parts) === 0)
            {
                return null;
            }

            array_pop($parts);
            continue;
        }

        $parts[] = $part;
    }

    return normalizeZipInternalPath(implode('/', $parts));
}

function extractEgfGameMetadata(string $path): array
{
    $metadata = [
        'title' => null,
        'creator' => null,
        'description' => null,
        'identifier' => null,
        'modified' => null,
        'cover_path' => null,
        'cover_media_type' => null,
    ];

    if (!class_exists('ZipArchive'))
    {
        return $metadata;
    }

    $zip = new ZipArchive();

    if ($zip->open($path) !== true)
    {
        return $metadata;
    }

    $previousXmlErrorSetting = libxml_use_internal_errors(true);

    try
    {
        $mimetype = getZipEntryContents($zip, 'mimetype', 128);

        if ($mimetype !== 'application/egf+zip')
        {
            return $metadata;
        }

        $containerXml = getZipEntryContents($zip, 'META-INF/container.xml', MAX_CONTAINER_XML_BYTES);

        if ($containerXml === null)
        {
            return $metadata;
        }

        $container = simplexml_load_string(
            $containerXml,
            'SimpleXMLElement',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );

        if ($container === false || !isset($container->rootfiles->rootfile[0]))
        {
            return $metadata;
        }

        $corePath = normalizeZipInternalPath( (string) $container->rootfiles->rootfile[0]['full-path']);

        if ($corePath === null)
        {
            return $metadata;
        }

        $coreXml = getZipEntryContents($zip, $corePath, MAX_CORE_XML_BYTES);

        if ($coreXml === null)
        {
            return $metadata;
        }

        $egf = simplexml_load_string(
            $coreXml,
            'SimpleXMLElement',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );

        if ($egf === false || !isset($egf->metadata))
        {
            return $metadata;
        }

        $dc = $egf->metadata->children('http://purl.org/dc/elements/1.1/');

        $title = normalizeMetadataValue( (string) $dc->title);

        if ($title !== '')
        {
            $metadata['title'] = $title;
        }

        $creators = [];

        foreach ($dc->creator as $creator)
        {
            $creator = normalizeMetadataValue( (string) $creator);

            if ($creator !== '')
            {
                $creators[] = $creator;
            }
        }

        if (count($creators) > 0)
        {
            $metadata['creator'] = implode(', ', array_unique($creators));
        }

        $descriptions = [];

        foreach ($dc->description as $descriptionNode)
        {
            $description = normalizeMetadataValue( (string) $descriptionNode);

            if ($description !== '')
            {
                $descriptions[] = $description;
            }
        }

        if (count($descriptions) > 0)
        {
            $metadata['description'] = implode(' ', array_unique($descriptions));
        }

        $identifiers = [];

        foreach ($dc->identifier as $identifierNode)
        {
            $identifier = normalizeMetadataValue( (string) $identifierNode);

            if ($identifier !== '')
            {
                $identifiers[] = $identifier;
            }
        }

        if (count($identifiers) > 0)
        {
            $metadata['identifier'] = implode(', ', array_unique($identifiers));
        }

        foreach ($egf->metadata->meta as $meta)
        {
            if ( (string) $meta['property'] !== 'dcterms:modified')
            {
                continue;
            }

            $modified = normalizeMetadataValue( (string) $meta);

            if ($modified !== '')
            {
                $metadata['modified'] = $modified;
            }

            break;
        }

        if (isset($egf->manifest))
        {
            foreach ($egf->manifest->item as $item)
            {
                if ( (string) $item['role'] !== 'egf_cover')
                {
                    continue;
                }

                $href = (string) $item['href'];
                $mediaType = (string) $item['media-type'];

                if (!in_array($mediaType, ['image/png', 'image/jpeg'], true))
                {
                    break;
                }

                $coverPath = resolveZipRelativePath($corePath, $href);

                if ($coverPath === null)
                {
                    break;
                }

                if ($zip->locateName($coverPath) === false)
                {
                    break;
                }

                $metadata['cover_path'] = $coverPath;
                $metadata['cover_media_type'] = $mediaType;

                break;
            }
        }

        return $metadata;
    }
    finally
    {
        libxml_clear_errors();
        libxml_use_internal_errors($previousXmlErrorSetting);
        $zip->close();
    }
}

function isSha1Hash(string $hash): bool
{
    return preg_match('/^[a-f0-9]{40}$/', strtolower($hash)) === 1;
}

function getUploadHashPath(string $filename): string
{
    return UPLOAD_DIR . '/' . basename($filename) . '.sha1';
}

function getUploadedFileSha1(string $path): ?string
{
    $filename = basename($path);
    $hashPath = getUploadHashPath($filename);

    if (is_file($hashPath))
    {
        $storedHash = strtolower(trim( (string) file_get_contents($hashPath)));

        if (isSha1Hash($storedHash))
        {
            return $storedHash;
        }
    }

    $hash = sha1_file($path);

    if ($hash === false)
    {
        return null;
    }

    $hash = strtolower($hash);

    if (!isSha1Hash($hash))
    {
        return null;
    }

    file_put_contents($hashPath, $hash . PHP_EOL, LOCK_EX);
    chmod($hashPath, 0644);

    return $hash;
}

function saveUploadedFileSha1(string $filename, string $sha1): void
{
    $sha1 = strtolower($sha1);

    if (!isSha1Hash($sha1))
    {
        return;
    }

    $hashPath = getUploadHashPath($filename);

    file_put_contents($hashPath, $sha1 . PHP_EOL, LOCK_EX);
    chmod($hashPath, 0644);
}

function getUploadMetadataPath(string $filename): string
{
    return UPLOAD_DIR . '/' . basename($filename) . '.meta.json';
}

function getUploadedEgfFilePath(string $filename): ?string
{
    ensureUploadDirectory();

    $filename = basename($filename);
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if ($filename === '' || $extension !== "egf")
    {
        return null;
    }

    $uploadRoot = realpath(UPLOAD_DIR);
    $path = realpath(UPLOAD_DIR . '/' . $filename);

    if ($uploadRoot === false || $path === false)
    {
        return null;
    }

    if (strncmp($path, $uploadRoot, strlen($uploadRoot)) !== 0 || !is_file($path))
    {
        return null;
    }

    return $path;
}

function getCsrfToken(): string
{
    startAppSession();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']))
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool
{
    startAppSession();

    return isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function currentUserCanDeleteUpload(string $filename): bool
{
    $currentUser = isLoggedIn() ? getCurrentUser() : null;

    if ($currentUser === null)
    {
        return false;
    }

    $uploadMetadata = getUploadMetadata($filename);
    $uploadedBy = normalizeMetadataValue( (string) ($uploadMetadata['uploaded_by'] ?? ''));

    if ($uploadedBy === '')
    {
        return false;
    }

    return hash_equals($currentUser, $uploadedBy);
}

function deleteUploadedEgfFile(string $filename): array
{
    $filename = basename($filename);

    if (!currentUserCanDeleteUpload($filename))
    {
        return [
            'success' => false,
            'message' => t('not_allowed_delete_game'),
        ];
    }

    $path = getUploadedEgfFilePath($filename);

    if ($path === null)
    {
        return [
            'success' => false,
            'message' => t('game_file_not_found'),
        ];
    }

    if (!unlink($path))
    {
        return [
            'success' => false,
            'message' => t('could_not_delete_game_file'),
        ];
    }

    $hashPath = getUploadHashPath($filename);
    $metadataPath = getUploadMetadataPath($filename);

    if (is_file($hashPath))
    {
        unlink($hashPath);
    }

    if (is_file($metadataPath))
    {
        unlink($metadataPath);
    }

    return [
        'success' => true,
        'message' => t('game_deleted_success'),
    ];
}

function getUploadMetadata(string $filename): array
{
    $metadataPath = getUploadMetadataPath($filename);

    if (!is_file($metadataPath))
    {
        return [];
    }

    $json = file_get_contents($metadataPath);

    if ($json === false)
    {
        return [];
    }

    $metadata = json_decode($json, true);

    return is_array($metadata) ? $metadata : [];
}

function saveUploadMetadata(string $filename, array $metadata): bool
{
    $metadataPath = getUploadMetadataPath($filename);

    $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false)
    {
        return false;
    }

    $saved = file_put_contents($metadataPath, $json, LOCK_EX) !== false;

    if ($saved)
    {
        chmod($metadataPath, 0644);
    }

    return $saved;
}

function findUploadedFileBySha1(string $sha1): ?string
{
    $sha1 = strtolower($sha1);

    if (!isSha1Hash($sha1))
    {
        return null;
    }

    ensureUploadDirectory();

    $files = glob(UPLOAD_DIR . '/*.' . "egf") ?: [];

    foreach ($files as $path)
    {
        if (!is_file($path))
        {
            continue;
        }

        $existingSha1 = getUploadedFileSha1($path);

        if ($existingSha1 === $sha1)
        {
            return basename($path);
        }
    }

    return null;
}

function isValidUtcDateTime(string $value): bool
{
    $value = trim($value);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]00:00)$/', $value))
    {
        return false;
    }

    try
    {
        new DateTimeImmutable($value);
        return true;
    }
    catch (Exception)
    {
        return false;
    }
}

function loadSafeXml(string $xml): SimpleXMLElement|false
{
    if (function_exists('mb_check_encoding') && !mb_check_encoding($xml, 'UTF-8'))
    {
        return false;
    }

    return simplexml_load_string(
        $xml,
        'SimpleXMLElement',
        LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
    );
}

function isSupportedEgfVersion(string $version): bool
{
    return in_array($version, ['1.0', '1.1'], true);
}

function getClientIpAddress(): string
{
    return trim( (string) ($_SERVER['REMOTE_ADDR'] ?? '') );
}

function ipMatchesRule(string $ip, string $rule): bool
{
    $ip = trim($ip);
    $rule = trim($rule);

    if ($ip === '' || $rule === '')
    {
        return false;
    }

    if (strpos($rule, '/') === false)
    {
        $ipBinary = @inet_pton($ip);
        $ruleBinary = @inet_pton($rule);

        return $ipBinary !== false && $ruleBinary !== false && $ipBinary === $ruleBinary;
    }

    [$network, $prefixLength] = explode('/', $rule, 2);

    $network = trim($network);
    $prefixLength = trim($prefixLength);

    if ($prefixLength === '' || !ctype_digit($prefixLength))
    {
        return false;
    }

    $ipBinary = @inet_pton($ip);
    $networkBinary = @inet_pton($network);

    if ($ipBinary === false || $networkBinary === false)
    {
        return false;
    }

    if (strlen($ipBinary) !== strlen($networkBinary))
    {
        return false;
    }

    $prefixLength = (int) $prefixLength;
    $maxPrefixLength = strlen($ipBinary) * 8;

    if ($prefixLength < 0 || $prefixLength > $maxPrefixLength)
    {
        return false;
    }

    $fullBytes = intdiv($prefixLength, 8);
    $remainingBits = $prefixLength % 8;

    if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($networkBinary, 0, $fullBytes))
    {
        return false;
    }

    if ($remainingBits === 0)
    {
        return true;
    }

    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

    return (ord($ipBinary[$fullBytes]) & $mask) === (ord($networkBinary[$fullBytes]) & $mask);
}

function ipMatchesAnyRule(string $ip, array $rules): bool
{
    foreach ($rules as $rule)
    {
        if (ipMatchesRule($ip, (string) $rule))
        {
            return true;
        }
    }

    return false;
}

function ensureDataDirectory(): void
{
    if (!is_dir(DATA_DIR))
    {
        mkdir(DATA_DIR, 0755, true);
    }

    $htaccess = DATA_DIR . "/.htaccess";

    if (!file_exists($htaccess))
    {
        file_put_contents($htaccess, "Require all denied\nDeny from all\n");
    }

    if (!file_exists(USERS_FILE))
    {
        file_put_contents(USERS_FILE, json_encode([], JSON_PRETTY_PRINT), LOCK_EX);
        chmod(USERS_FILE, 0644);
    }

    if (!file_exists(RATE_LIMITS_FILE))
    {
        file_put_contents(RATE_LIMITS_FILE, json_encode([], JSON_PRETTY_PRINT), LOCK_EX);
        chmod(RATE_LIMITS_FILE, 0644);
    }
}

function startAppSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE)
    {
        session_start();
    }
}

function normalizeUsername(string $username): string
{
    return strtolower(trim($username));
}

function isValidUsername(string $username): bool
{
    return preg_match('/^[a-zA-Z0-9_-]{3,32}$/', $username) === 1;
}

function loadUsers(): array
{
    ensureDataDirectory();

    $json = file_get_contents(USERS_FILE);

    if ($json === false)
    {
        return [];
    }

    $users = json_decode($json, true);

    return is_array($users) ? $users : [];
}

function saveUsers(array $users): bool
{
    ensureDataDirectory();

    $json = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false)
    {
        return false;
    }

    return file_put_contents(USERS_FILE, $json, LOCK_EX) !== false;
}

function loadRateLimits(): array
{
    ensureDataDirectory();

    $json = file_get_contents(RATE_LIMITS_FILE);

    if ($json === false)
    {
        return [];
    }

    $rateLimits = json_decode($json, true);

    return is_array($rateLimits) ? $rateLimits : [];
}

function saveRateLimits(array $rateLimits): bool
{
    ensureDataDirectory();

    $json = json_encode($rateLimits, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false)
    {
        return false;
    }

    return file_put_contents(RATE_LIMITS_FILE, $json, LOCK_EX) !== false;
}

function getRateLimitKey(string $purpose, string $value): string
{
    return $purpose . ':' . hash('sha256', $value);
}

function getCurrentIpRateLimitKey(string $purpose): string
{
    $ip = getClientIpAddress();

    if (filter_var($ip, FILTER_VALIDATE_IP) === false)
    {
        $ip = 'unknown';
    }

    return getRateLimitKey($purpose, $ip);
}

function getRateLimitRemainingSeconds(string $key, int $cooldownSeconds): int
{
    $rateLimits = loadRateLimits();
    $lastAttemptAt = (int) ($rateLimits[$key]['last_attempt_at'] ?? 0);

    if ($lastAttemptAt <= 0)
    {
        return 0;
    }

    $remaining = $cooldownSeconds - (time() - $lastAttemptAt);

    return max(0, $remaining);
}

function recordRateLimitAttempt(string $key): bool
{
    $rateLimits = loadRateLimits();

    $rateLimits[$key] = [
        'last_attempt_at' => time(),
    ];

    return saveRateLimits($rateLimits);
}

/**
 * Check whether the current IP is currently locked out from login attempts.
 * Returns the remaining lockout time in seconds (0 = allowed).
 */

function getLoginLockoutRemainingSeconds(): int
{
    $key            = getCurrentIpRateLimitKey('login_failed');
    $rateLimits     = loadRateLimits();

    $data           = $rateLimits[$key] ?? [];
    $attempts       = (int) ($data['attempts'] ?? 0);
    $lockedUntil    = (int) ($data['locked_until'] ?? 0);

    // If the maximum number of attempts has been reached and the lockout is still active

    if ( $attempts >= LOGIN_MAX_FAILED_ATTEMPTS && $lockedUntil > time() )
    {
        return $lockedUntil - time();
    }

    // If the lockout has expired, reset the counter

    if ( $lockedUntil > 0 && $lockedUntil <= time() )
    {
        unset($rateLimits[$key]);
        saveRateLimits($rateLimits);
    }

    return 0;
}

/**
 * Record a failed login attempt and activate the lockout if necessary.
 */

function recordFailedLoginAttempt(): void
{
    $key        = getCurrentIpRateLimitKey('login_failed');
    $rateLimits = loadRateLimits();

    $data = $rateLimits[$key] ?? [
        'attempts'      => 0,
        'locked_until'  => 0,
    ];

    $data['attempts'] = (int) ($data['attempts'] ?? 0) + 1;
    $data['last_attempt_at'] = time();

    if ($data['attempts'] >= LOGIN_MAX_FAILED_ATTEMPTS)
    {
        $data['locked_until'] = time() + LOGIN_LOCKOUT_SECONDS;
    }

    $rateLimits[$key] = $data;
    saveRateLimits($rateLimits);
}

/**
 * Clear the failed login counter after a successful login.
 */

function clearFailedLoginAttempts(): void
{
    $key        = getCurrentIpRateLimitKey('login_failed');
    $rateLimits = loadRateLimits();

    if ( isset($rateLimits[$key]) )
    {
        unset($rateLimits[$key]);
        saveRateLimits($rateLimits);
    }
}

function formatCooldownDuration(int $seconds): string
{
    if ($seconds <= 60)
    {
        return t('duration_seconds', [
            'count' => (string) $seconds,
        ]);
    }

    return t('duration_minutes', [
        'count' => (string) ceil($seconds / 60),
    ]);
}

function createUserAccount(string $username, string $email, string $password): array
{
    $username = normalizeUsername($username);
    $email = normalizeEmail($email);

    if (!REGISTRATION_ENABLED)
    {
        return [
            'success' => false,
            'message' => t('registration_disabled'),
        ];
    }

    if (!isValidUsername($username))
    {
        return [
            'success' => false,
            'message' => t('invalid_username_rules'),
        ];
    }

    $emailProviderValidation = validateEmailProvider($email);

    if (!$emailProviderValidation['valid'])
    {
        return [
            'success' => false,
            'message' => $emailProviderValidation['message'],
        ];
    }

    if (strlen($password) < 8)
    {
        return [
            'success' => false,
            'message' => t('password_min_length'),
        ];
    }

    $users = loadUsers();

    if (isset($users[$username]))
    {
        return [
            'success' => false,
            'message' => t('username_taken'),
        ];
    }

    foreach ($users as $existingUser)
    {
        if (isset($existingUser['email']) && normalizeEmail( (string) $existingUser['email']) === $email)
        {
            return [
                'success' => false,
                'message' => t('email_already_used'),
            ];
        }
    }

    /*
     * Rate limit: prevent one IP from creating many accounts
     * and forcing the server to send many verification emails.
     */
    $registrationRateLimitKey = '';

    if (EMAIL_VERIFICATION_REQUIRED)
    {
        $registrationRateLimitKey = getCurrentIpRateLimitKey('registration_verification_email');
        $remainingSeconds = getRateLimitRemainingSeconds(
            $registrationRateLimitKey,
            REGISTRATION_EMAIL_COOLDOWN_SECONDS
        );

        if ($remainingSeconds > 0)
        {
            return [
                'success' => false,
                'message' => t('wait_before_creating_account', ['duration' => formatCooldownDuration($remainingSeconds)]),
            ];
        }
    }

    $verificationToken = generateVerificationToken();

    $users[$username] = [
        'username' => $username,
        'email' => $email,
        'email_verified' => !EMAIL_VERIFICATION_REQUIRED,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => gmdate('c'),
    ];

    if (EMAIL_VERIFICATION_REQUIRED)
    {
        $users[$username]['verification_token_hash'] = hash('sha256', $verificationToken);
        $users[$username]['verification_expires_at'] = time() + VERIFICATION_TOKEN_LIFETIME_SECONDS;
        $users[$username]['verification_email_sent_at'] = time();
    }

    if (!saveUsers($users))
    {
        return [
            'success' => false,
            'message' => t('could_not_save_user_account'),
        ];
    }

    if (EMAIL_VERIFICATION_REQUIRED)
    {
        recordRateLimitAttempt($registrationRateLimitKey);

        if (!sendVerificationEmail($email, $username, $verificationToken))
        {
            return [
                'success' => false,
                'message' => t('account_created_email_not_sent'),
            ];
        }

        return [
            'success' => true,
            'message' => t('account_created_check_email'),
        ];
    }

    return [
        'success' => true,
        'message' => t('account_created_success'),
    ];
}

function resendAccountVerificationEmail(string $username): array
{
    $username = normalizeUsername($username);
    $users = loadUsers();

    if (!isset($users[$username]))
    {
        return [
            'success' => false,
            'message' => t('user_account_not_found'),
        ];
    }

    $email = normalizeEmail( (string) ($users[$username]['email'] ?? ''));

    if (!isValidEmail($email))
    {
        return [
            'success' => false,
            'message' => t('account_invalid_email'),
        ];
    }

    if (!empty($users[$username]['email_verified']))
    {
        return [
            'success' => true,
            'message' => t('email_already_verified'),
        ];
    }

    $lastSentAt         = (int) ($users[$username]['verification_email_sent_at'] ?? 0);
    $remainingSeconds   = VERIFICATION_EMAIL_RESEND_COOLDOWN_SECONDS - (time() - $lastSentAt);

    if ($lastSentAt > 0 && $remainingSeconds > 0)
    {
        return [
            'success' => false,
            'message' => t('verification_email_recently_sent', ['duration' => formatCooldownDuration($remainingSeconds)]),
        ];
    }

    $verificationToken = generateVerificationToken();

    $users[$username]['verification_token_hash'] = hash('sha256', $verificationToken);
    $users[$username]['verification_expires_at'] = time() + VERIFICATION_TOKEN_LIFETIME_SECONDS;
    $users[$username]['verification_email_sent_at'] = time();

    if (!saveUsers($users))
    {
        return [
            'success' => false,
            'message' => t('could_not_save_verification_token'),
        ];
    }

    if (!sendVerificationEmail($email, $username, $verificationToken))
    {
        return [
            'success' => false,
            'message' => t('could_not_send_verification_email'),
        ];
    }

    return [
        'success' => true,
        'message' => t('new_verification_email_sent'),
    ];
}

function loginUser(string $username, string $password): array
{
    startAppSession();

    // Brute-force protection
    $remainingLockout = getLoginLockoutRemainingSeconds();

    if ($remainingLockout > 0)
    {
        return [
            'success' => false,
            'message' => t('login_temporarily_locked', [
                'duration' => formatCooldownDuration($remainingLockout),
            ]),
        ];
    }

    $username   = normalizeUsername($username);
    $users      = loadUsers();

    if ( !isset($users[$username]['password_hash']) )
    {
        recordFailedLoginAttempt();
        return [
            'success' => false,
            'message' => t('invalid_username_or_password'),
        ];
    }

    if ( !password_verify($password, $users[$username]['password_hash']) )
    {
        recordFailedLoginAttempt();
        return [
            'success' => false,
            'message' => t('invalid_username_or_password'),
        ];
    }

    // Password is correct → clear failed attempts
    clearFailedLoginAttempts();

    if ( EMAIL_VERIFICATION_REQUIRED && empty($users[$username]['email_verified']) )
    {
        $resendResult = resendAccountVerificationEmail($username);

        if ( $resendResult['success'] )
        {
            return [
                'success' => false,
                'message' => t('verify_email_before_login_new_sent'),
            ];
        }

        return [
            'success' => false,
            'message' => t('verify_email_before_login_send_failed', ['message' => $resendResult['message']]),
        ];
    }

    session_regenerate_id(true);
    $_SESSION['username'] = $username;

    return [
        'success' => true,
        'message' => t('login_success'),
    ];
}

function logoutUser(): void
{
    startAppSession();

    $_SESSION = [];

    if (ini_get('session.use_cookies'))
    {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}

function getCurrentUser(): ?string
{
    startAppSession();

    $username = $_SESSION['username'] ?? null;

    if (!is_string($username) || $username === '')
    {
        return null;
    }

    return $username;
}

function isLoggedIn(): bool
{
    $username = getCurrentUser();

    if ($username === null)
    {
        return false;
    }

    $users = loadUsers();

    if (!isset($users[$username]))
    {
        return false;
    }

    if (EMAIL_VERIFICATION_REQUIRED && empty($users[$username]['email_verified']))
    {
        return false;
    }

    return true;
}

function sendEmailChangeVerificationEmail(string $newEmail, string $username, string $token): bool
{
    $verificationUrl = getAppBaseUrl() . '/verify-email-change.php?token=' . rawurlencode($token);

    $subject = t('email_change_subject');

    $body = t('email_change_body', [
        'username' => $username,
        'url' => $verificationUrl,
    ]);

    $headers = [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_EMAIL . '>',
        'Reply-To: ' . MAIL_FROM_EMAIL,
        'Content-Type: text/plain; charset=UTF-8',
    ];

    return mail($newEmail, $subject, $body, implode("\r\n", $headers));
}

function changeCurrentEmail(string $newEmail, string $currentPassword): array
{
    startAppSession();

    $username = getCurrentUser();

    if ($username === null || !isLoggedIn())
    {
        return [
            'success' => false,
            'message' => t('must_login_change_email'),
        ];
    }

    $newEmail = normalizeEmail($newEmail);

    $emailProviderValidation = validateEmailProvider($newEmail);

    if (!$emailProviderValidation['valid'])
    {
        return [
            'success' => false,
            'message' => $emailProviderValidation['message'],
        ];
    }

    $users = loadUsers();

    if (!isset($users[$username]))
    {
        return [
            'success' => false,
            'message' => t('user_account_not_found'),
        ];
    }

    if (!isset($users[$username]['password_hash']) || !password_verify($currentPassword, $users[$username]['password_hash']))
    {
        return [
            'success' => false,
            'message' => t('current_password_incorrect'),
        ];
    }

    $currentEmail = normalizeEmail( (string) ($users[$username]['email'] ?? ''));

    if ($newEmail === $currentEmail)
    {
        return [
            'success' => true,
            'message' => t('email_unchanged'),
        ];
    }

    foreach ($users as $existingUsername => $existingUser)
    {
        if ($existingUsername === $username)
        {
            continue;
        }

        if (isset($existingUser['email']) && normalizeEmail( (string) $existingUser['email']) === $newEmail)
        {
            return [
                'success' => false,
                'message' => t('email_already_used'),
            ];
        }
    }

    $token = generateVerificationToken();

    $users[$username]['pending_email'] = $newEmail;
    $users[$username]['email_change_token_hash'] = hash('sha256', $token);
    $users[$username]['email_change_expires_at'] = time() + VERIFICATION_TOKEN_LIFETIME_SECONDS;

    if (!saveUsers($users))
    {
        return [
            'success' => false,
            'message' => t('could_not_save_email_change'),
        ];
    }

    if (!sendEmailChangeVerificationEmail($newEmail, $username, $token))
    {
        return [
            'success' => false,
            'message' => t('email_change_saved_email_not_sent'),
        ];
    }

    return [
        'success' => true,
        'message' => t('email_change_link_sent'),
    ];
}

function anonymizeUploadsByUser(string $username): void
{
    $username = normalizeUsername($username);

    if ($username === '')
    {
        return;
    }

    ensureUploadDirectory();

    $files = glob(UPLOAD_DIR . '/*.' . "egf") ?: [];

    foreach ($files as $path)
    {
        $filename = basename($path);
        $uploadMetadata = getUploadMetadata($filename);

        $uploadedBy = normalizeUsername( (string) ($uploadMetadata['uploaded_by'] ?? ''));

        if ($uploadedBy === '' || !hash_equals($username, $uploadedBy))
        {
            continue;
        }

        $uploadMetadata['uploaded_by'] = '';
        $uploadMetadata['account_deleted_at'] = gmdate('c');

        saveUploadMetadata($filename, $uploadMetadata);
    }
}

function deleteCurrentUserAccount(string $currentPassword): array
{
    startAppSession();

    $username = getCurrentUser();

    if ($username === null || !isLoggedIn())
    {
        return [
            'success' => false,
            'message' => t('must_login_delete_account'),
        ];
    }

    $users = loadUsers();

    if (!isset($users[$username]))
    {
        return [
            'success' => false,
            'message' => t('user_account_not_found'),
        ];
    }

    if (!isset($users[$username]['password_hash']) || !password_verify($currentPassword, $users[$username]['password_hash']))
    {
        return [
            'success' => false,
            'message' => t('current_password_incorrect'),
        ];
    }

    unset($users[$username]);

    if (!saveUsers($users))
    {
        return [
            'success' => false,
            'message' => t('could_not_delete_account'),
        ];
    }

    anonymizeUploadsByUser($username);
    logoutUser();

    return [
        'success' => true,
        'message' => t('account_deleted'),
    ];
}

function changeCurrentPassword(string $currentPassword, string $newPassword): array
{
    startAppSession();

    $username = getCurrentUser();

    if ($username === null || !isLoggedIn())
    {
        return [
            'success' => false,
            'message' => t('must_login_change_password'),
        ];
    }

    if (strlen($newPassword) < 8)
    {
        return [
            'success' => false,
            'message' => t('new_password_min_length'),
        ];
    }

    $users = loadUsers();

    if (!isset($users[$username]['password_hash']))
    {
        return [
            'success' => false,
            'message' => t('user_account_not_found'),
        ];
    }

    if (!password_verify($currentPassword, $users[$username]['password_hash']))
    {
        return [
            'success' => false,
            'message' => t('current_password_incorrect'),
        ];
    }

    $users[$username]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $users[$username]['password_changed_at'] = gmdate('c');

    if (!saveUsers($users))
    {
        return [
            'success' => false,
            'message' => t('could_not_update_password'),
        ];
    }

    session_regenerate_id(true);
    $_SESSION['username'] = $username;

    return [
        'success' => true,
        'message' => t('password_updated_success'),
    ];
}

function verifyEmailChange(string $token): array
{
    if ($token === '')
    {
        return [
            'success' => false,
            'message' => t('missing_verification_token'),
        ];
    }

    $tokenHash = hash('sha256', $token);
    $users = loadUsers();

    foreach ($users as $username => $user)
    {
        if (!isset($user['email_change_token_hash']))
        {
            continue;
        }

        if (!hash_equals( (string) $user['email_change_token_hash'], $tokenHash))
        {
            continue;
        }

        $expiresAt = (int) ($user['email_change_expires_at'] ?? 0);

        if ($expiresAt < time())
        {
            return [
                'success' => false,
                'message' => t('email_verification_link_expired'),
            ];
        }

        $pendingEmail = normalizeEmail( (string) ($user['pending_email'] ?? ''));

        $emailProviderValidation = validateEmailProvider($pendingEmail);

        if (!$emailProviderValidation['valid'])
        {
            return [
                'success' => false,
                'message' => $emailProviderValidation['message'],
            ];
        }

        foreach ($users as $otherUsername => $otherUser)
        {
            if ($otherUsername === $username)
            {
                continue;
            }

            if (isset($otherUser['email']) && normalizeEmail( (string) $otherUser['email']) === $pendingEmail)
            {
                return [
                    'success' => false,
                    'message' => t('email_already_used'),
                ];
            }
        }

        $users[$username]['email'] = $pendingEmail;
        $users[$username]['email_verified'] = true;
        $users[$username]['email_changed_at'] = gmdate('c');

        unset(
            $users[$username]['pending_email'],
            $users[$username]['email_change_token_hash'],
            $users[$username]['email_change_expires_at']
        );

        if (!saveUsers($users))
        {
            return [
                'success' => false,
                'message' => t('could_not_update_email'),
            ];
        }

        return [
            'success' => true,
            'message' => t('email_updated_success'),
        ];
    }

    return [
        'success' => false,
        'message' => t('invalid_verification_link'),
    ];
}

function sendPasswordResetEmail(string $email, string $username, string $token): bool
{
    $resetUrl = getAppBaseUrl() . '/reset-password.php?token=' . rawurlencode($token);

    $subject = t('password_reset_email_subject');

    $body = t('password_reset_email_body', [
        'username' => $username,
        'url' => $resetUrl,
    ]);

    $headers = [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_EMAIL . '>',
        'Reply-To: ' . MAIL_FROM_EMAIL,
        'Content-Type: text/plain; charset=UTF-8',
    ];

    return mail($email, $subject, $body, implode("\r\n", $headers));
}

function requestPasswordReset(string $email): array
{
    $email          = normalizeEmail($email);
    $genericMessage = t('password_reset_generic_message');

    if (!isValidEmail($email))
    {
        return [
            'success' => true,
            'message' => $genericMessage,
        ];
    }

    $users = loadUsers();

    foreach ($users as $username => $user)
    {
        $userEmail = normalizeEmail( (string) ($user['email'] ?? ''));

        if ($userEmail !== $email)
        {
            continue;
        }

        $lastRequestedAt = (int) ($user['password_reset_requested_at'] ?? 0);

        if ($lastRequestedAt > 0 && (time() - $lastRequestedAt) < PASSWORD_RESET_EMAIL_COOLDOWN_SECONDS)
        {
            return [
                'success' => true,
                'message' => $genericMessage,
            ];
        }

        $token = generateVerificationToken();

        $users[$username]['password_reset_token_hash'] = hash('sha256', $token);
        $users[$username]['password_reset_expires_at'] = time() + PASSWORD_RESET_TOKEN_LIFETIME_SECONDS;
        $users[$username]['password_reset_requested_at'] = time();

        if (!saveUsers($users))
        {
            return [
                'success' => false,
                'message' => t('could_not_create_password_reset'),
            ];
        }

        sendPasswordResetEmail($email, (string) $username, $token);

        return [
            'success' => true,
            'message' => $genericMessage,
        ];
    }

    return [
        'success' => true,
        'message' => $genericMessage,
    ];
}

function resetPasswordWithToken(string $token, string $newPassword): array
{
    if ($token === '')
    {
        return [
            'success' => false,
            'message' => t('missing_password_reset_token'),
        ];
    }

    if (strlen($newPassword) < 8)
    {
        return [
            'success' => false,
            'message' => t('password_min_length'),
        ];
    }

    $tokenHash = hash('sha256', $token);
    $users = loadUsers();

    foreach ($users as $username => $user)
    {
        if (!isset($user['password_reset_token_hash']))
        {
            continue;
        }

        if (!hash_equals( (string) $user['password_reset_token_hash'], $tokenHash) )
        {
            continue;
        }

        $expiresAt = (int) ($user['password_reset_expires_at'] ?? 0);

        if ($expiresAt < time())
        {
            return [
                'success' => false,
                'message' => t('password_reset_link_expired'),
            ];
        }

        $users[$username]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $users[$username]['password_changed_at'] = gmdate('c');

        unset(
            $users[$username]['password_reset_token_hash'],
            $users[$username]['password_reset_expires_at'],
            $users[$username]['password_reset_requested_at']
        );

        if (!saveUsers($users))
        {
            return [
                'success' => false,
                'message' => t('could_not_update_password'),
            ];
        }

        return [
            'success' => true,
            'message' => t('password_updated_login_now'),
        ];
    }

    return [
        'success' => false,
        'message' => t('invalid_password_reset_link'),
    ];
}

function getCurrentUserEmail(): ?string
{
    $username = getCurrentUser();

    if ($username === null)
    {
        return null;
    }

    $users = loadUsers();

    if (!isset($users[$username]['email']))
    {
        return null;
    }

    return (string) $users[$username]['email'];
}

function getUploadAccessStatus(): array
{
    if (!UPLOADS_ENABLED)
    {
        return [
            'allowed' => false,
            'message' => t('file_uploads_disabled'),
        ];
    }

    $ip = getClientIpAddress();

    if (filter_var($ip, FILTER_VALIDATE_IP) === false)
    {
        return [
            'allowed' => false,
            'message' => t('could_not_determine_ip'),
        ];
    }

    if (ipMatchesAnyRule($ip, UPLOAD_BLOCKED_IPS))
    {
        return [
            'allowed' => false,
            'message' => t('uploads_blocked_ip'),
        ];
    }

    if (count(UPLOAD_ALLOWED_IPS) > 0 && !ipMatchesAnyRule($ip, UPLOAD_ALLOWED_IPS))
    {
        return [
            'allowed' => false,
            'message' => t('uploads_restricted_ip'),
        ];
    }

    if (REQUIRE_ACCOUNT_FOR_UPLOAD && !isLoggedIn())
    {
        return [
            'allowed' => false,
            'message' => t('must_login_upload'),
        ];
    }

    return [
        'allowed' => true,
        'message' => '',
    ];
}

function validateEgfPackage(string $path): array
{
    $errors = [];

    if (!class_exists('ZipArchive'))
    {
        return [
            'valid' => false,
            'errors' => [t('ziparchive_extension_unavailable')],
        ];
    }

    if (!is_file($path) || !is_readable($path))
    {
        return [
            'valid' => false,
            'errors' => [t('uploaded_file_cannot_be_read')],
        ];
    }

    $zip = new ZipArchive();

    if ($zip->open($path) !== true)
    {
        return [
            'valid' => false,
            'errors' => [t('not_valid_zip_archive')],
        ];
    }

    $previousXmlErrorSetting = libxml_use_internal_errors(true);

    try
    {
        /*
         * 1. ZIP + mimetype
         */

        if ($zip->numFiles < 3)
        {
            $errors[] = t('egf_package_incomplete');
        }

        if ($zip->getNameIndex(0) !== 'mimetype')
        {
            $errors[] = t('mimetype_must_be_first');
        }

        $mimetypeStat = $zip->statName('mimetype');

        if (!is_array($mimetypeStat))
        {
            $errors[] = t('mimetype_missing');
        }
        else
        {
            if (isset($mimetypeStat['comp_method']) && defined('ZipArchive::CM_STORE') && (int) $mimetypeStat['comp_method'] !== ZipArchive::CM_STORE)
            {
                $errors[] = t('mimetype_must_not_be_compressed');
            }

            if ( (int) ($mimetypeStat['size'] ?? 0) !== strlen('application/egf+zip') )
            {
                $errors[] = t('mimetype_invalid_size');
            }
        }

        $mimetype = getZipEntryContents($zip, 'mimetype', 128);

        if ($mimetype !== 'application/egf+zip')
        {
            $errors[] = t('mimetype_invalid_content');
        }

        /*
         * 2. META-INF/container.xml
         */

        $containerXml = getZipEntryContents($zip, 'META-INF/container.xml', MAX_CONTAINER_XML_BYTES);

        if ($containerXml === null)
        {
            $errors[] = t('container_missing_or_too_large');
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        $container = loadSafeXml($containerXml);

        if ($container === false || $container->getName() !== 'container')
        {
            $errors[] = t('container_not_valid');
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        if ( (string) $container['version'] !== '1.0')
        {
            $errors[] = t('container_version_required');
        }

        if (!isset($container->rootfiles) || count($container->rootfiles) !== 1)
        {
            $errors[] = t('container_one_rootfiles');
        }

        if (!isset($container->rootfiles->rootfile) || count($container->rootfiles->rootfile) !== 1)
        {
            $errors[] = t('container_one_rootfile');
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        $rootfile = $container->rootfiles->rootfile[0];
        $corePath = normalizeZipInternalPath( (string) $rootfile['full-path']);

        if ($corePath === null)
        {
            $errors[] = t('rootfile_path_invalid');
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        if (trim( (string) $rootfile['id']) === '')
        {
            $errors[] = t('rootfile_id_required');
        }

        /*
         * 3. EGF core file
         */

        $coreXml = getZipEntryContents($zip, $corePath, MAX_CORE_XML_BYTES);

        if ($coreXml === null)
        {
            $errors[] = t('core_missing_or_too_large');
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        $egf = loadSafeXml($coreXml);

        if ($egf === false || $egf->getName() !== 'egf')
        {
            $errors[] = t('core_must_have_egf_root');
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        $egfVersion = trim( (string) $egf['version']);

        if ($egfVersion === '')
        {
            $errors[] = t('core_version_required');
        }

        elseif (!preg_match('/^\d+\.\d+$/', $egfVersion))
        {
            $errors[] = t('egf_version_format');
        }

        elseif (!isSupportedEgfVersion($egfVersion))
        {
            $errors[] = t('egf_version_not_supported');
        }

        /*
         * 4. Metadata
         */

        if (!isset($egf->metadata))
        {
            $errors[] = t('metadata_missing');
        }
        else
        {
            $dc = $egf->metadata->children('http://purl.org/dc/elements/1.1/');

            $title = normalizeMetadataValue( (string) $dc->title);
            $language = normalizeMetadataValue( (string) $dc->language);

            if ($title === '')
            {
                $errors[] = t('metadata_title_required');
            }

            if ($language === '')
            {
                $errors[] = t('metadata_language_required');
            }

            $modified = '';

            foreach ($egf->metadata->meta as $meta)
            {
                if ( (string) $meta['property'] === 'dcterms:modified')
                {
                    $modified = normalizeMetadataValue( (string) $meta);
                    break;
                }
            }

            if ($modified === '')
            {
                $errors[] = t('metadata_modified_required');
            }
            elseif (!isValidUtcDateTime($modified))
            {
                $errors[] = t('metadata_modified_utc');
            }
        }

        /*
         * 5. Manifest
         */

        if (!isset($egf->manifest))
        {
            $errors[] = t('manifest_missing');
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        $manifestIds = [];
        $roleCounts = [];
        $roleToIds = [];

        foreach ($egf->manifest->children() as $child)
        {
            if ($child->getName() !== 'item')
            {
                $errors[] = t('manifest_children_item_only');
                continue;
            }

            $id = trim( (string) $child['id']);
            $role = trim( (string) $child['role']);

            if ($id === '')
            {
                $errors[] = t('manifest_item_id_required');
                continue;
            }

            if ($role === '')
            {
                $errors[] = t('manifest_item_role_required');
            }

            if (isset($manifestIds[$id]))
            {
                $errors[] = t('manifest_duplicate_id', ['id' => $id]);
            }

            $manifestIds[$id] = true;

            if ($role !== '')
            {
                $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
                $roleToIds[$role][] = $id;
            }

            if (str_starts_with($role, 'x-'))
            {
                $errors[] = t('manifest_custom_x_roles_not_allowed');
            }

            if (isset($child['href']))
            {
                $href = trim( (string) $child['href']);
                $mediaType = trim( (string) $child['media-type']);

                if ($href === '')
                {
                    $errors[] = t('manifest_item_empty_href', ['id' => $id]);
                }
                else
                {
                    $resolvedPath = resolveZipRelativePath($corePath, $href);

                    if ($resolvedPath === null)
                    {
                        $errors[] = t('manifest_item_invalid_href', ['id' => $id]);
                    }
                    elseif ($zip->locateName($resolvedPath) === false)
                    {
                        $errors[] = t('manifest_item_missing_resource', ['id' => $id, 'path' => $resolvedPath]);
                    }
                }

                if ($mediaType === '')
                {
                    $errors[] = t('manifest_item_media_type_required', ['id' => $id]);
                }
            }

            if ($role === 'egf_cover')
            {
                $coverMediaType = trim( (string) $child['media-type']);

                if (!in_array($coverMediaType, ['image/png', 'image/jpeg'], true))
                {
                    $errors[] = t('cover_must_be_png_or_jpeg');
                }
            }

            if ($role === 'max_wrong_answers')
            {
                $value = trim( (string) $child['value']);

                if (!preg_match('/^[1-9][0-9]*$/', $value))
                {
                    $errors[] = t('max_wrong_answers_positive_integer');
                }
            }
        }

        $requiredRoles = [
            'game_title_simple',
            'congratulations_simple',
            'game_over_simple',
            'credits_simple',
            'max_wrong_answers',
        ];

        foreach ($requiredRoles as $role)
        {
            if ( ($roleCounts[$role] ?? 0) !== 1)
            {
                $errors[] = t('manifest_role_exactly_once', ['role' => $role]);
            }
        }

        /*
         * 6. Sequence
         */

        if (!isset($egf->sequence))
        {
            $errors[] = t('sequence_missing');
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        $sequenceRefs = [];

        foreach ($egf->sequence->children() as $child)
        {
            if ($child->getName() !== 'scene')
            {
                $errors[] = t('sequence_children_scene_only');
                continue;
            }

            $ref = trim( (string) $child['ref']);

            if ($ref === '')
            {
                $errors[] = t('scene_ref_required');
                continue;
            }

            if (!isset($manifestIds[$ref]))
            {
                $errors[] = t('sequence_unknown_manifest_item', ['ref' => $ref]);
            }

            if ( $egfVersion === '1.1' && in_array($ref, $sequenceRefs, true) )
            {
                $errors[] = t('sequence_duplicate_ref', ['ref' => $ref]);
            }

            $sequenceRefs[] = $ref;
        }

        if (count($sequenceRefs) < 4)
        {
            $errors[] = t('sequence_minimum_scenes');
        }
        else
        {
            $titleId = $roleToIds['game_title_simple'][0] ?? null;
            $congratulationsId = $roleToIds['congratulations_simple'][0] ?? null;
            $gameOverId = $roleToIds['game_over_simple'][0] ?? null;
            $creditsId = $roleToIds['credits_simple'][0] ?? null;

            if ($titleId !== null && $sequenceRefs[0] !== $titleId)
            {
                $errors[] = t('title_scene_first');
            }

            $lastIndex = count($sequenceRefs) - 1;

            if ($creditsId !== null && $sequenceRefs[$lastIndex] !== $creditsId)
            {
                $errors[] = t('credits_scene_last');
            }

            if ($gameOverId !== null && $sequenceRefs[$lastIndex - 1] !== $gameOverId)
            {
                $errors[] = t('game_over_scene_penultimate');
            }

            if ($congratulationsId !== null && $sequenceRefs[$lastIndex - 2] !== $congratulationsId)
            {
                $errors[] = t('congratulations_scene_antepenultimate');
            }
        }

        /*
         * 7. Settings
         */

        if (!isset($egf->settings))
        {
            $errors[] = t('settings_missing');
        }
        else
        {
            $maxWrongAnswersId = $roleToIds['max_wrong_answers'][0] ?? null;
            $maxWrongAnswersSettingCount = 0;

            foreach ($egf->settings->children() as $child)
            {
                if ($child->getName() !== 'setting')
                {
                    $errors[] = t('settings_children_setting_only');
                    continue;
                }

                $ref = trim( (string) $child['ref']);

                if ($ref === '')
                {
                    $errors[] = t('setting_ref_required');
                    continue;
                }

                if (!isset($manifestIds[$ref]))
                {
                    $errors[] = t('settings_unknown_manifest_item', ['ref' => $ref]);
                }

                if ($maxWrongAnswersId !== null && $ref === $maxWrongAnswersId)
                {
                    $maxWrongAnswersSettingCount++;
                }
            }

            if ($maxWrongAnswersSettingCount !== 1)
            {
                $errors[] = t('settings_max_wrong_answers_once');
            }
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
        ];
    }
    finally
    {
        libxml_clear_errors();
        libxml_use_internal_errors($previousXmlErrorSetting);
        $zip->close();
    }
}

function getUploadedEgfFiles(): array
{
    ensureUploadDirectory();

    $files = glob(UPLOAD_DIR . '/*.' . "egf") ?: [];

    usort($files, static function (string $a, string $b): int
    {
        return filemtime($b) <=> filemtime($a);
    });

    return array_map(static function (string $path): array {
        $filename = basename($path);
        $metadata = extractEgfGameMetadata($path);
        $uploadMetadata = getUploadMetadata($filename);

        $uploadedBy = normalizeMetadataValue( (string) ($uploadMetadata['uploaded_by'] ?? '') );

        return [
            'name' => $filename,
            'game_name' => $metadata['title'] ?? pathinfo($filename, PATHINFO_FILENAME),
            'game_creator' => $metadata['creator'] ?? t('unknown_creator'),
            'game_description' => $metadata['description'] ?? t('no_description'),
            'game_identifier' => $metadata['identifier'] ?? t('no_identifier'),
            'game_modified' => $metadata['modified'] ?? t('no_modification_date'),
            'has_cover' => $metadata['cover_path'] !== null,
            'size' => filesize($path) ?: 0,
            'uploaded_by' => $uploadedBy,
            'uploaded_by_known' => $uploadedBy !== '',
        ];
    }, $files);
}

function getUploadedEgfFilesByUser(string $username): array
{
    $username = normalizeUsername($username);

    if ($username === '')
    {
        return [];
    }

    return array_values(array_filter(
        getUploadedEgfFiles(),
        static function (array $file) use ($username): bool
        {
            $uploadedBy = normalizeUsername( (string) ($file['uploaded_by'] ?? '') );

            return $uploadedBy !== '' && hash_equals($username, $uploadedBy);
        }
    ));
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

require_once __DIR__ . '/i18n.php';