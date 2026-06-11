<?php
declare(strict_types=1);

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__);
    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function load_env_file(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function storage_path(string $path = ''): string
{
    $storage = base_path('storage');
    return $path === '' ? $storage : $storage . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function normalize_path(string $path): string
{
    if ($path === '') {
        return $path;
    }

    // Absolute Linux/macOS path or Windows drive path.
    if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path)) {
        return $path;
    }

    return base_path($path);
}

function app_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $timezone = env_value('APP_TIMEZONE', 'Asia/Bangkok') ?: 'Asia/Bangkok';
    date_default_timezone_set($timezone);

    $config = [
        'env' => env_value('APP_ENV', 'local'),
        'url' => env_value('APP_URL', 'http://localhost:8080'),
        'timezone' => $timezone,
        'db_path' => normalize_path(env_value('DB_PATH', 'storage/app.db') ?: 'storage/app.db'),
        'openai_api_key' => env_value('OPENAI_API_KEY', ''),
        'openai_model' => env_value('OPENAI_MODEL', 'gpt-5.5'),
        'zernio_api_key' => env_value('ZERNIO_API_KEY', ''),
        'r2_account_id' => env_value('R2_ACCOUNT_ID', ''),
        'r2_access_key_id' => env_value('R2_ACCESS_KEY_ID', ''),
        'r2_secret_access_key' => env_value('R2_SECRET_ACCESS_KEY', ''),
        'r2_bucket' => env_value('R2_BUCKET', 'personal-poster'),
        'r2_public_base_url' => env_value('R2_PUBLIC_BASE_URL', ''),
        'admin_username' => env_value('ADMIN_USERNAME', 'admin'),
        'admin_password_hash' => env_value('ADMIN_PASSWORD_HASH', ''),
        'upload_max_mb' => (int)(env_value('UPLOAD_MAX_MB', '500') ?: '500'),
    ];

    return $config;
}
