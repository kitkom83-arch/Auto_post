<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin_if_enabled();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected = $id > 0 ? find_media_asset($id) : null;
$videos = list_media_assets('video', 100);
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Media Library — Personal AI Auto Poster</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <main class="wrap">
    <section class="card hero">
      <div>
        <p class="eyebrow">Phase 2</p>
        <h1>Media Library</h1>
        <p class="muted">วิดีโอที่อัปโหลดแล้วทั้งหมด</p>
      </div>
      <div class="actions">
        <a class="button" href="/upload.php">อัปโหลดวิดีโอ</a>
        <a class="button secondary" href="/">Dashboard</a>
      </div>
    </section>

    <?php if ($selected): ?>
      <section class="card">
        <div class="section-head">
          <div>
            <h2><?= h($selected['original_name'] ?: 'Video #' . $selected['id']) ?></h2>
            <p class="muted">Asset ID: #<?= h((string)$selected['id']) ?></p>
          </div>
          <a class="button secondary" href="/media-file.php?id=<?= h((string)$selected['id']) ?>" target="_blank">เปิดไฟล์จริง</a>
        </div>

        <video class="video-preview" controls preload="metadata" src="/media-file.php?id=<?= h((string)$selected['id']) ?>"></video>

        <table class="detail-table">
          <tbody>
            <tr><th>ชื่อไฟล์เดิม</th><td><?= h($selected['original_name']) ?></td></tr>
            <tr><th>Path</th><td><code><?= h(asset_relative_path((string)$selected['local_path'])) ?></code></td></tr>
            <tr><th>MIME</th><td><?= h($selected['mime_type']) ?></td></tr>
            <tr><th>ขนาด</th><td><?= h(human_bytes(isset($selected['bytes']) ? (int)$selected['bytes'] : null)) ?></td></tr>
            <tr><th>ความยาว</th><td><?= h(human_duration(isset($selected['duration_sec']) ? (float)$selected['duration_sec'] : null)) ?></td></tr>
            <tr><th>ความละเอียด</th><td><?= h((string)($selected['width'] ?? '-')) ?>x<?= h((string)($selected['height'] ?? '-')) ?></td></tr>
            <tr><th>Video codec</th><td><?= h($selected['video_codec'] ?? '') ?></td></tr>
            <tr><th>Audio codec</th><td><?= h($selected['audio_codec'] ?? '') ?></td></tr>
            <tr><th>Status</th><td><span class="badge pass"><?= h($selected['status'] ?? 'ready') ?></span></td></tr>
            <tr><th>SHA256</th><td><code><?= h($selected['sha256'] ?? '') ?></code></td></tr>
          </tbody>
        </table>
      </section>
    <?php endif; ?>

    <section class="card">
      <h2>รายการวิดีโอ</h2>
      <?php if (count($videos) === 0): ?>
        <p class="empty">ยังไม่มีวิดีโอ</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>ชื่อไฟล์</th>
              <th>ขนาด</th>
              <th>เวลา</th>
              <th>ขนาดภาพ</th>
              <th>Codec</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($videos as $video): ?>
            <tr>
              <td>#<?= h((string)$video['id']) ?></td>
              <td><?= h($video['original_name'] ?: basename((string)$video['local_path'])) ?></td>
              <td><?= h(human_bytes(isset($video['bytes']) ? (int)$video['bytes'] : null)) ?></td>
              <td><?= h(human_duration(isset($video['duration_sec']) ? (float)$video['duration_sec'] : null)) ?></td>
              <td><?= h((string)($video['width'] ?? '-')) ?>x<?= h((string)($video['height'] ?? '-')) ?></td>
              <td><?= h((string)($video['video_codec'] ?? '-')) ?></td>
              <td><a class="text-link" href="/media.php?id=<?= h((string)$video['id']) ?>">ดู</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
