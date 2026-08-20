
# EGF Station

EGF Station is a lightweight PHP web application for hosting, sharing, validating, downloading, and **playing** Educational Game Format (EGF) game packages.

It provides optional user accounts, email verification, password reset, upload ownership tracking, duplicate detection, cover extraction, IP-based upload restrictions, a multilingual interface, and an integrated game player based on the Embedded EGF 1.1 Reader.

## Main Features

- Upload and validate `.egf` game packages.
- Display available games with metadata, size, cover image, creator, identifier, and modification date.
- Download hosted EGF games.
- **Play** any hosted EGF game directly in the browser (no extra software required).
- Detect duplicate uploads using SHA-1 fingerprints.
- Optional user accounts with registration, login, email verification, password reset, and account settings.
- Let users delete their own uploads and delete their account, with automatic upload anonymization.
- Optional upload restrictions by IP whitelist or blacklist.
- Multilingual interface with browser-language detection, manual selection, and RTL support for Arabic and Urdu.

## Installation Settings

### Basic installation

Place the project on a PHP-capable web server and make sure PHP can write to these directories:

```text
uploads/
data/
```

The application creates missing data files automatically, including user and rate-limit JSON files.

Required PHP features:

```text
ZipArchive
SimpleXML
JSON
Sessions
mail() or a configured mail transport
```

### Main configuration

Edit `config.php` before deploying:

```php
const APP_NAME    = "EGF Station";
const APP_VERSION = "1.0";

const APP_BASE_URL    = "https://example.com";
const MAIL_FROM_EMAIL = "no-reply@example.com";
const MAIL_FROM_NAME  = "EGF Station";
```

Set `APP_BASE_URL` to the public URL of your installation. This is used for verification, email-change confirmation, and password-reset links.

### Upload settings

```php
const UPLOADS_ENABLED = true;
const MAX_UPLOAD_BYTES = 1024 * 1024 * 1024;
```

Use `UPLOADS_ENABLED` to enable or disable uploads globally. Use `MAX_UPLOAD_BYTES` to change the maximum accepted file size.

Your PHP/server upload limits must also allow the same size, for example:

```ini
upload_max_filesize = 1024M
post_max_size = 1024M
```

### Account and registration settings

```php
const REGISTRATION_ENABLED = true;
const REQUIRE_ACCOUNT_FOR_UPLOAD = false;
const EMAIL_VERIFICATION_REQUIRED = true;
```

These settings control different parts of the account system:

- `REGISTRATION_ENABLED` controls whether visitors can create new accounts.
- `REQUIRE_ACCOUNT_FOR_UPLOAD` controls whether uploading requires a logged-in account.
- `EMAIL_VERIFICATION_REQUIRED` controls whether new users must verify their email address before logging in.

Common configurations:

```php
// Public archive: anyone can upload, accounts are optional.
const REGISTRATION_ENABLED = true;
const REQUIRE_ACCOUNT_FOR_UPLOAD = false;
```

In this mode, anonymous uploads are allowed. Users may still create an account if they want to manage and delete their own uploads later.

```php
// Community archive: users must have an account to upload.
const REGISTRATION_ENABLED = true;
const REQUIRE_ACCOUNT_FOR_UPLOAD = true;
```

In this mode, visitors can create an account, verify their email address, log in, and then upload games.

```php
// Private or invitation-only archive: only existing accounts can upload.
const REGISTRATION_ENABLED = false;
const REQUIRE_ACCOUNT_FOR_UPLOAD = true;
```

In this mode, the registration form is disabled. Only accounts that already exist in `data/users.json` can log in and upload games.

```php
// Public upload with closed registration.
const REGISTRATION_ENABLED = false;
const REQUIRE_ACCOUNT_FOR_UPLOAD = false;
```

In this mode, anonymous uploads are allowed, but new account creation is disabled.

Important: if `REQUIRE_ACCOUNT_FOR_UPLOAD` is `true` and `REGISTRATION_ENABLED` is `false`, make sure at least one user account already exists. Otherwise, nobody will be able to upload.

### IP upload restrictions

Uploads can be restricted by IP address in `config.php`.

If `UPLOAD_ALLOWED_IPS` is empty, all IPs are allowed except blocked ones:

```php
const UPLOAD_ALLOWED_IPS = [];
```

To allow uploads only from specific IPs or networks:

```php
const UPLOAD_ALLOWED_IPS = [
    "203.0.113.10",
    "203.0.113.0/24",
    "2001:db8::/32",
];
```

To block specific IPs or networks:

```php
const UPLOAD_BLOCKED_IPS = [
    "198.51.100.20",
    "198.51.100.0/24",
];
```

Blocked IPs always take priority over allowed IPs.

### Email provider restrictions

You can allow or block email domains:

```php
const EMAIL_ALLOWED_DOMAINS = [];

const EMAIL_BLOCKED_DOMAINS = [
    "yopmail.com",
    "mailinator.com",
    "*.temporary.example",
];
```

If `EMAIL_ALLOWED_DOMAINS` is empty, all providers are allowed except blocked ones. If it is not empty, only listed domains are allowed.

### Multilingual settings

The interface language is selected in this order:

1. `?lang=` URL parameter.
2. Session language.
3. Language cookie.
4. Browser/system language.
5. Default locale.

Supported locales are configured in `i18n.php`.

```php
const DEFAULT_LOCALE = 'en';
```

Arabic and Urdu automatically use right-to-left layout.

## Source

The original source code of this program is available at:

https://directory.yvisherve.net/egf_station.zip



