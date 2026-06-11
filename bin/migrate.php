<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (db_has_column($table, $column)) {
        return;
    }
    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
}

ensure_storage_dirs();

try {
    $pdo = db();

    $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );');

    $pdo->beginTransaction();

    $pdo->exec("CREATE TABLE IF NOT EXISTS accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        platform TEXT NOT NULL CHECK(platform IN ('tiktok','instagram','zernio')),
        zernio_account_id TEXT,
        display_name TEXT,
        status TEXT NOT NULL DEFAULT 'active',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS media_assets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kind TEXT NOT NULL CHECK(kind IN ('video','audio','output','image')),
        original_name TEXT,
        local_path TEXT,
        public_url TEXT,
        mime_type TEXT,
        bytes INTEGER,
        duration_sec REAL,
        width INTEGER,
        height INTEGER,
        sha256 TEXT,
        extension TEXT,
        video_codec TEXT,
        audio_codec TEXT,
        ffprobe_json TEXT,
        status TEXT NOT NULL DEFAULT 'ready',
        error_message TEXT,
        license_type TEXT,
        license_source TEXT,
        license_url TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );");

    // Upgrade existing Phase 1 database without deleting user data.
    add_column_if_missing($pdo, 'media_assets', 'original_name', 'TEXT');
    add_column_if_missing($pdo, 'media_assets', 'extension', 'TEXT');
    add_column_if_missing($pdo, 'media_assets', 'video_codec', 'TEXT');
    add_column_if_missing($pdo, 'media_assets', 'audio_codec', 'TEXT');
    add_column_if_missing($pdo, 'media_assets', 'ffprobe_json', 'TEXT');
    add_column_if_missing($pdo, 'media_assets', 'status', "TEXT NOT NULL DEFAULT 'ready'");
    add_column_if_missing($pdo, 'media_assets', 'error_message', 'TEXT');

    $pdo->exec("CREATE TABLE IF NOT EXISTS captions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        prompt TEXT NOT NULL,
        caption_text TEXT NOT NULL,
        model_name TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        platform TEXT NOT NULL CHECK(platform IN ('tiktok','instagram')),
        account_id INTEGER,
        source_video_id INTEGER,
        music_asset_id INTEGER,
        output_video_id INTEGER,
        caption_id INTEGER,
        scheduled_at TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'queued',
        attempt_count INTEGER NOT NULL DEFAULT 0,
        next_retry_at TEXT,
        zernio_post_id TEXT,
        zernio_status TEXT,
        last_error_code TEXT,
        last_error_message TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(account_id) REFERENCES accounts(id),
        FOREIGN KEY(source_video_id) REFERENCES media_assets(id),
        FOREIGN KEY(music_asset_id) REFERENCES media_assets(id),
        FOREIGN KEY(output_video_id) REFERENCES media_assets(id),
        FOREIGN KEY(caption_id) REFERENCES captions(id)
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS post_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        phase TEXT NOT NULL,
        success INTEGER NOT NULL,
        http_status INTEGER,
        error_code TEXT,
        error_message TEXT,
        raw_response TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(post_id) REFERENCES posts(id)
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS worker_heartbeat (
        id INTEGER PRIMARY KEY CHECK(id = 1),
        last_seen_at TEXT NOT NULL,
        note TEXT
    );");

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_due ON posts(status, scheduled_at);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_retry ON posts(status, next_retry_at);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_post_attempts_post ON post_attempts(post_id, created_at);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_media_assets_kind_created ON media_assets(kind, created_at);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_media_assets_sha256 ON media_assets(sha256);');

    $stmt = $pdo->prepare('INSERT OR IGNORE INTO migrations (name) VALUES (:name)');
    $stmt->execute([':name' => '001_phase1_base_schema']);
    $stmt->execute([':name' => '002_phase2_media_upload_metadata']);

    $pdo->commit();

    $journalMode = db_scalar('PRAGMA journal_mode;');
    echo "Migration completed.\n";
    echo "Database: " . app_config()['db_path'] . "\n";
    echo "SQLite journal_mode: " . $journalMode . "\n";
    echo "Phase 2 ready: upload video at /upload.php\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
