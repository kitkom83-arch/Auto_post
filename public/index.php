<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin_if_enabled();

$config = app_config();
$videos = [];
try {
    $videos = list_media_assets('video', 8);
} catch (Throwable $e) {
    app_log('warning', 'dashboard could not load media assets', ['error' => $e->getMessage()]);
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Personal AI Auto Poster</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <main class="wrap">
    <section class="card hero">
      <div>
        <p class="eyebrow">Phase 2</p>
        <h1>Personal AI Auto Poster</h1>
        <p class="muted">อัปโหลดวิดีโอ + อ่าน metadata ด้วย ffprobe + บันทึกลง SQLite</p>
      </div>
      <div class="actions">
        <a class="button" href="/upload.php">อัปโหลดวิดีโอ</a>
        <a class="button secondary" href="/health.php">Health Check</a>
      </div>
    </section>

    <section class="grid">
      <div class="card">
        <h2>สถานะ Phase 2</h2>
        <ul class="checklist">
          <li>✅ Upload form</li>
          <li>✅ ตรวจ MIME ด้วย fileinfo</li>
          <li>✅ ย้ายไฟล์ด้วย move_uploaded_file</li>
          <li>✅ อ่าน duration / size / codec ด้วย ffprobe</li>
          <li>✅ บันทึกลง media_assets</li>
        </ul>
      </div>

      <div class="card">
        <h2>ข้อมูลระบบ</h2>
        <p><strong>Environment:</strong> <?= h($config['env']) ?></p>
        <p><strong>Timezone:</strong> <?= h($config['timezone']) ?></p>
        <p><strong>Upload limit app:</strong> <?= h((string)$config['upload_max_mb']) ?> MB</p>
        <p><strong>Database:</strong> <code><?= h($config['db_path']) ?></code></p>
      </div>
    </section>

    <section class="card">
      <div class="section-head">
        <div>
          <h2>วิดีโอล่าสุด</h2>
          <p class="muted">รายการนี้มาจากตาราง <code>media_assets</code></p>
        </div>
        <a class="button secondary" href="/media.php">ดูทั้งหมด</a>
      </div>

      <?php if (count($videos) === 0): ?>
        <p class="empty">ยังไม่มีวิดีโอ กด “อัปโหลดวิดีโอ” เพื่อเริ่ม Phase 2</p>
      <?php else: ?>
        <div class="media-list">
          <?php foreach ($videos as $video): ?>
            <article class="media-item">
              <div>
                <h3><?= h($video['original_name'] ?: basename((string)$video['local_path'])) ?></h3>
                <p class="muted">
                  #<?= h((string)$video['id']) ?> ·
                  <?= h((string)($video['width'] ?? '-')) ?>x<?= h((string)($video['height'] ?? '-')) ?> ·
                  <?= h(human_duration(isset($video['duration_sec']) ? (float)$video['duration_sec'] : null)) ?> ·
                  <?= h(human_bytes(isset($video['bytes']) ? (int)$video['bytes'] : null)) ?>
                </p>
              </div>
              <a class="text-link" href="/media.php?id=<?= h((string)$video['id']) ?>">เปิดดู</a>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
