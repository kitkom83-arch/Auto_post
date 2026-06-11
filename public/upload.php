<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin_if_enabled();

$config = app_config();
$error = '';
$success = '';
$asset = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['video']) || !is_array($_FILES['video'])) {
            throw new RuntimeException('ไม่พบไฟล์วิดีโอใน request');
        }

        $assetId = handle_video_upload($_FILES['video']);
        $asset = find_media_asset($assetId);
        $success = 'อัปโหลดสำเร็จ และอ่าน metadata ด้วย ffprobe แล้ว';
    } catch (Throwable $e) {
        $error = $e->getMessage();
        app_log('warning', 'video upload failed', ['error' => $error]);
    }
}

$phpUploadMax = ini_get('upload_max_filesize') ?: '-';
$phpPostMax = ini_get('post_max_size') ?: '-';
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Upload Video — Personal AI Auto Poster</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <main class="wrap">
    <section class="card hero">
      <div>
        <p class="eyebrow">Phase 2</p>
        <h1>อัปโหลดวิดีโอ</h1>
        <p class="muted">ระบบจะเก็บไฟล์ลง <code>storage/input</code> แล้วใช้ <code>ffprobe</code> อ่านข้อมูลวิดีโอ</p>
      </div>
      <div class="actions">
        <a class="button secondary" href="/">Dashboard</a>
        <a class="button secondary" href="/media.php">Media Library</a>
      </div>
    </section>

    <?php if ($error !== ''): ?>
      <section class="notice error"><strong>อัปโหลดไม่สำเร็จ:</strong> <?= h($error) ?></section>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
      <section class="notice success"><strong><?= h($success) ?></strong></section>
    <?php endif; ?>

    <section class="card">
      <h2>เลือกไฟล์วิดีโอ</h2>
      <p class="muted">รองรับเบื้องต้น: MP4, MOV, M4V, WEBM</p>
      <form method="post" enctype="multipart/form-data" class="upload-form">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= h((string)((int)$config['upload_max_mb'] * 1024 * 1024)) ?>">
        <label class="file-box">
          <span>ลากไฟล์มาใส่ หรือคลิกเพื่อเลือกไฟล์</span>
          <input type="file" name="video" accept="video/mp4,video/quicktime,video/webm,.mp4,.mov,.m4v,.webm" required>
        </label>
        <button class="button" type="submit">อัปโหลดและอ่านข้อมูลวิดีโอ</button>
      </form>

      <div class="hint-box">
        <h3>ค่า limit ตอนนี้</h3>
        <p><strong>PHP upload_max_filesize:</strong> <?= h($phpUploadMax) ?></p>
        <p><strong>PHP post_max_size:</strong> <?= h($phpPostMax) ?></p>
        <p><strong>App UPLOAD_MAX_MB:</strong> <?= h((string)$config['upload_max_mb']) ?>M</p>
        <p class="muted">ถ้าอัปโหลดคลิปใหญ่แล้วพัง ให้เพิ่มค่า <code>upload_max_filesize</code> และ <code>post_max_size</code> ใน <code>C:\php83\php.ini</code></p>
      </div>
    </section>

    <?php if ($asset): ?>
      <section class="card">
        <div class="section-head">
          <div>
            <h2>ผลลัพธ์ล่าสุด</h2>
            <p class="muted">Asset ID: #<?= h((string)$asset['id']) ?></p>
          </div>
          <a class="button" href="/media.php?id=<?= h((string)$asset['id']) ?>">เปิดหน้ารายละเอียด</a>
        </div>
        <table>
          <tbody>
            <tr><th>ชื่อไฟล์เดิม</th><td><?= h($asset['original_name']) ?></td></tr>
            <tr><th>MIME</th><td><?= h($asset['mime_type']) ?></td></tr>
            <tr><th>ขนาด</th><td><?= h(human_bytes((int)$asset['bytes'])) ?></td></tr>
            <tr><th>ความยาว</th><td><?= h(human_duration((float)$asset['duration_sec'])) ?></td></tr>
            <tr><th>ความละเอียด</th><td><?= h((string)$asset['width']) ?>x<?= h((string)$asset['height']) ?></td></tr>
            <tr><th>Video codec</th><td><?= h($asset['video_codec']) ?></td></tr>
            <tr><th>Audio codec</th><td><?= h($asset['audio_codec']) ?></td></tr>
            <tr><th>SHA256</th><td><code><?= h($asset['sha256']) ?></code></td></tr>
          </tbody>
        </table>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
