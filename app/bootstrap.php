<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

load_env_file(base_path('.env'));

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Ffmpeg.php';
require_once __DIR__ . '/MediaRepository.php';
require_once __DIR__ . '/FileUpload.php';

function ensure_storage_dirs(): void
{
    $dirs = [
        storage_path(),
        storage_path('input'),
        storage_path('output'),
        storage_path('music'),
        storage_path('tmp'),
        storage_path('logs'),
    ];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}

function require_admin_if_enabled(): void
{
    $config = app_config();
    $hash = (string)($config['admin_password_hash'] ?? '');
    if ($hash === '') {
        return;
    }

    $username = (string)($config['admin_username'] ?? 'admin');
    $givenUser = $_SERVER['PHP_AUTH_USER'] ?? '';
    $givenPass = $_SERVER['PHP_AUTH_PW'] ?? '';

    if ($givenUser === $username && password_verify($givenPass, $hash)) {
        return;
    }

    header('WWW-Authenticate: Basic realm="Personal Poster"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication required';
    exit;
}

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

ensure_storage_dirs();
