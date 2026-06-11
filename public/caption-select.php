<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin_if_enabled();

$videos = list_media_assets('video', 100);
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Select Video for Caption — Personal AI Auto Poster</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <main class="wrap">
    <section class="card hero">
      <div>
        <p class="eyebrow">Phase 3</p>
        <h1>เลือกวิดีโอเพื่อสร้าง Caption</h1>
        <p class="muted">เลือกวิดีโอที่อัปโหลดแล้ว จากนั้นเปิดหน้า Generate Caption</p>
      </div>
      <div class="actions">
        <a class="button secondary" href="/media.php">Media Library</a>
        <a class="button secondary" href="/captions.php">Captions</a>
        <a class="button secondary" href="/">Dashboard</a>
      </div>
    </section>

    <section class="card">
      <h2>วิดีโอทั้งหมด</h2>
      <?php if (count($videos) === 0): ?>
        <p class="empty">ยังไม่มีวิดีโอ ให้ไปอัปโหลดก่อน</p>
      <?php else: ?>
        <div class="media-list">
          <?php foreach ($videos as $video): ?>
            <article class="media-item">
              <div>
                <h3>#<?= h((string)$video['id']) ?> · <?= h($video['original_name'] ?: basename((string)$video['local_path'])) ?></h3>
                <p class="muted">
                  <?= h(human_duration(isset($video['duration_sec']) ? (float)$video['duration_sec'] : null)) ?> ·
                  <?= h((string)($video['width'] ?? '-')) ?>x<?= h((string)($video['height'] ?? '-')) ?> ·
                  <?= h(human_bytes(isset($video['bytes']) ? (int)$video['bytes'] : null)) ?>
                </p>
              </div>
              <div class="actions">
                <a class="text-link" href="/media.php?id=<?= h((string)$video['id']) ?>">ดูวิดีโอ</a>
                <a class="button" href="/caption.php?asset_id=<?= h((string)$video['id']) ?>">Generate Caption</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
