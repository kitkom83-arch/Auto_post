<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

try {
    $now = date('c');
    $heartbeatPath = storage_path('worker.heartbeat');
    file_put_contents($heartbeatPath, $now . PHP_EOL, LOCK_EX);

    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO worker_heartbeat (id, last_seen_at, note)
        VALUES (1, :last_seen_at, :note)
        ON CONFLICT(id) DO UPDATE SET last_seen_at = excluded.last_seen_at, note = excluded.note');
    $stmt->execute([
        ':last_seen_at' => $now,
        ':note' => 'Phase 1 worker heartbeat only. Real queue processing starts in Phase 6.',
    ]);

    app_log('info', 'worker heartbeat', ['last_seen_at' => $now]);
    echo "Worker heartbeat updated: {$now}" . PHP_EOL;
} catch (Throwable $e) {
    app_log('error', 'worker failed', ['error' => $e->getMessage()]);
    fwrite(STDERR, "Worker failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
