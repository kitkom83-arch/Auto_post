<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('PHP extension pdo_sqlite is not installed. Install php8.3-sqlite3 or enable pdo_sqlite.');
    }

    $config = app_config();
    $dbPath = $config['db_path'];
    $dbDir = dirname($dbPath);

    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ]);

    // Important pragmas for this small 24/7 worker system.
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA busy_timeout = 5000;');
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA wal_autocheckpoint = 1000;');
    $pdo->exec('PRAGMA journal_size_limit = 67108864;');

    return $pdo;
}

function db_scalar(string $sql): mixed
{
    $stmt = db()->query($sql);
    if ($stmt === false) {
        return null;
    }
    return $stmt->fetchColumn();
}
