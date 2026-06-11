<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin_if_enabled();

function run_command_head(string $command): ?string
{
    $output = @shell_exec($command . ' 2>&1');
    if (!is_string($output) || trim($output) === '') {
        return null;
    }
    $lines = preg_split('/\r?\n/', trim($output));
    return $lines[0] ?? trim($output);
}

function add_check(array &$checks, string $name, string $status, string $detail): void
{
    $checks[] = [
        'name' => $name,
        'status' => $status,
        'detail' => $detail,
    ];
}

function ini_size_to_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float)$value;

    return match ($unit) {
        'g' => (int)($number * 1024 * 1024 * 1024),
        'm' => (int)($number * 1024 * 1024),
        'k' => (int)($number * 1024),
        default => (int)$number,
    };
}

$checks = [];
$config = app_config();

add_check($checks, 'PHP version', version_compare(PHP_VERSION, '8.3.0', '>=') ? 'pass' : 'fail', PHP_VERSION);

foreach (['PDO', 'pdo_sqlite', 'fileinfo', 'json', 'curl'] as $ext) {
    add_check(
        $checks,
        'PHP extension: ' . $ext,
        extension_loaded($ext) ? 'pass' : ($ext === 'curl' ? 'warn' : 'fail'),
        extension_loaded($ext) ? 'loaded' : 'missing'
    );
}

foreach (['input', 'output', 'music', 'tmp', 'logs'] as $dir) {
    $path = storage_path($dir);
    add_check($checks, 'Writable storage/' . $dir, is_dir($path) && is_writable($path) ? 'pass' : 'fail', $path);
}

try {
    $pdo = db();
    add_check($checks, 'SQLite connection', 'pass', $config['db_path']);

    $journalMode = (string)db_scalar('PRAGMA journal_mode;');
    add_check($checks, 'SQLite WAL mode', strtolower($journalMode) === 'wal' ? 'pass' : 'fail', 'journal_mode=' . $journalMode);

    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    $requiredTables = ['accounts', 'media_assets', 'captions', 'posts', 'post_attempts', 'worker_heartbeat'];
    $missing = array_values(array_diff($requiredTables, $tables ?: []));
    add_check($checks, 'Database tables', count($missing) === 0 ? 'pass' : 'fail', count($missing) === 0 ? 'all required tables exist' : 'missing: ' . implode(', ', $missing));

    $mediaColumns = ['original_name', 'extension', 'video_codec', 'audio_codec', 'ffprobe_json', 'status'];
    $missingMediaColumns = [];
    foreach ($mediaColumns as $column) {
        if (!db_has_column('media_assets', $column)) {
            $missingMediaColumns[] = $column;
        }
    }
    add_check($checks, 'Phase 2 media columns', count($missingMediaColumns) === 0 ? 'pass' : 'fail', count($missingMediaColumns) === 0 ? 'ready' : 'missing: ' . implode(', ', $missingMediaColumns));
} catch (Throwable $e) {
    add_check($checks, 'SQLite connection', 'fail', $e->getMessage());
}

$ffmpeg = run_command_head('ffmpeg -version');
add_check($checks, 'ffmpeg', $ffmpeg ? 'pass' : 'fail', $ffmpeg ?: 'not found');

$ffprobe = run_command_head('ffprobe -version');
add_check($checks, 'ffprobe', $ffprobe ? 'pass' : 'fail', $ffprobe ?: 'not found');

$uploadMax = ini_get('upload_max_filesize') ?: '0';
$postMax = ini_get('post_max_size') ?: '0';
$appMaxBytes = ((int)($config['upload_max_mb'] ?? 500)) * 1024 * 1024;
add_check($checks, 'PHP upload_max_filesize', ini_size_to_bytes($uploadMax) >= min($appMaxBytes, 100 * 1024 * 1024) ? 'pass' : 'warn', $uploadMax . ' (increase in php.ini if uploading large videos)');
add_check($checks, 'PHP post_max_size', ini_size_to_bytes($postMax) >= min($appMaxBytes, 100 * 1024 * 1024) ? 'pass' : 'warn', $postMax . ' (must be larger than upload_max_filesize)');

add_check($checks, 'OPENAI_API_KEY', $config['openai_api_key'] ? 'pass' : 'warn', $config['openai_api_key'] ? 'set' : 'empty until Phase 3');
add_check($checks, 'ZERNIO_API_KEY', $config['zernio_api_key'] ? 'pass' : 'warn', $config['zernio_api_key'] ? 'set' : 'empty until Phase 5');

$r2Ready = $config['r2_account_id'] && $config['r2_access_key_id'] && $config['r2_secret_access_key'] && $config['r2_bucket'];
add_check($checks, 'R2 config', $r2Ready ? 'pass' : 'warn', $r2Ready ? 'set' : 'empty or incomplete until Phase 5');

$free = @disk_free_space(storage_path());
$total = @disk_total_space(storage_path());
if ($free !== false && $total !== false && $total > 0) {
    $freeGb = round($free / 1024 / 1024 / 1024, 2);
    $percentFree = round(($free / $total) * 100, 1);
    add_check($checks, 'Disk space', $percentFree >= 15 ? 'pass' : 'warn', $freeGb . ' GB free (' . $percentFree . '%)');
} else {
    add_check($checks, 'Disk space', 'warn', 'cannot read disk space');
}

$heartbeatPath = storage_path('worker.heartbeat');
if (is_file($heartbeatPath)) {
    $age = time() - filemtime($heartbeatPath);
    add_check($checks, 'Worker heartbeat file', $age < 180 ? 'pass' : 'warn', 'last update ' . $age . ' seconds ago');
} else {
    add_check($checks, 'Worker heartbeat file', 'warn', 'run: php workers/loop.php');
}

$summary = ['pass' => 0, 'warn' => 0, 'fail' => 0];
foreach ($checks as $check) {
    $summary[$check['status']]++;
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Health Check</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <main class="wrap">
    <section class="card hero">
      <div>
        <p class="eyebrow">System Check</p>
        <h1>Health Check</h1>
        <p class="muted">Pass: <?= (int)$summary['pass'] ?> · Warn: <?= (int)$summary['warn'] ?> · Fail: <?= (int)$summary['fail'] ?></p>
      </div>
      <a class="button" href="/">กลับ Dashboard</a>
    </section>

    <section class="card">
      <table>
        <thead>
          <tr>
            <th>รายการ</th>
            <th>สถานะ</th>
            <th>รายละเอียด</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($checks as $check): ?>
          <tr>
            <td><?= h($check['name']) ?></td>
            <td><span class="badge <?= h($check['status']) ?>"><?= strtoupper(h($check['status'])) ?></span></td>
            <td><code><?= h($check['detail']) ?></code></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>
</body>
</html>
