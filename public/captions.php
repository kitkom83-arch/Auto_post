<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/CaptionRepository.php';
require_admin_if_enabled();

$captions = [];
$error = '';
try {
    $captions = list_recent_captions(100);
} catch (Throwable $e) {
    $error = $e->getMessage();
    app_log('warning', 'could not load captions page', ['error' => $error]);
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Captions — Personal AI Auto Poster</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <main class="wrap">
    <section class="card hero">
      <div>
        <p class="eyebrow">Phase 3</p>
        <h1>Captions</h1>
        <p class="muted">รวมแคปชั่นที่สร้างแล้ว เรียงจากใหม่ไปเก่า</p>
      </div>
      <div class="actions">
        <a class="button secondary" href="/media.php">Media Library</a>
        <a class="button secondary" href="/">Dashboard</a>
      </div>
    </section>

    <?php if ($error !== ''): ?>
      <section class="notice error"><strong>โหลดข้อมูลไม่สำเร็จ:</strong> <?= h($error) ?></section>
    <?php endif; ?>

    <section class="card">
      <h2>รายการแคปชั่นล่าสุด</h2>
      <?php if (count($captions) === 0): ?>
        <p class="empty">ยังไม่มีแคปชั่น ให้ไปที่ Media Library แล้วกด “สร้างแคปชั่น”</p>
      <?php else: ?>
        <div class="media-list">
          <?php foreach ($captions as $caption): ?>
            <article class="media-item caption-card">
              <div>
                <h3>#<?= h((string)$caption['id']) ?> · <?= h((string)($caption['topic'] ?? '-')) ?></h3>
                <p class="muted">
                  Video #<?= h((string)($caption['media_asset_id'] ?? '-')) ?> ·
                  <?= h((string)($caption['original_name'] ?? '')) ?> ·
                  <?= h((string)$caption['created_at']) ?>
                </p>
                <p><?= nl2br(h((string)$caption['caption_text'])) ?></p>
              </div>
              <div class="actions">
                <?php if (!empty($caption['media_asset_id'])): ?>
                  <a class="text-link" href="/media.php?id=<?= h((string)$caption['media_asset_id']) ?>">ดูวิดีโอ</a>
                  <a class="text-link" href="/caption.php?asset_id=<?= h((string)$caption['media_asset_id']) ?>">สร้างใหม่</a>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
