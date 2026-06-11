<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/OpenAIClient.php';
require_once __DIR__ . '/../app/CaptionRepository.php';
require_admin_if_enabled();

$assetId = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : (int)($_POST['asset_id'] ?? 0);
$asset = $assetId > 0 ? find_media_asset($assetId) : null;
$error = '';
$success = '';
$newCaption = null;

$form = [
    'topic' => (string)($_POST['topic'] ?? ''),
    'tone' => (string)($_POST['tone'] ?? 'จริงใจ อ่านง่าย'),
    'target_audience' => (string)($_POST['target_audience'] ?? 'คนดูทั่วไป'),
    'max_length' => (string)($_POST['max_length'] ?? '150'),
    'hashtag_count' => (string)($_POST['hashtag_count'] ?? '4'),
];

if (!$asset || ($asset['kind'] ?? '') !== 'video') {
    http_response_code(404);
    $error = 'ไม่พบวิดีโอที่เลือก หรือ asset นี้ไม่ใช่วิดีโอ';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $request = validate_caption_request($_POST);
        if ((int)$request['asset_id'] !== (int)$asset['id']) {
            throw new RuntimeException('asset_id ไม่ตรงกับวิดีโอที่เปิดอยู่');
        }

        $generated = openai_generate_caption($asset, $request);
        $captionId = save_caption([
            'media_asset_id' => (int)$asset['id'],
            'topic' => $request['topic'],
            'tone' => $request['tone'],
            'target_audience' => $request['target_audience'],
            'prompt_text' => $generated['prompt_text'],
            'caption_text' => $generated['caption_text'],
            'model_name' => $generated['model_name'],
            'raw_response_json' => $generated['raw_response_json'],
        ]);
        $newCaption = find_caption($captionId);
        $success = 'สร้างแคปชั่นสำเร็จ และบันทึกลงฐานข้อมูลแล้ว';
    } catch (Throwable $e) {
        $error = $e->getMessage();
        app_log('warning', 'caption generation failed', ['asset_id' => $assetId, 'error' => $error]);
    }
}

$captions = $asset ? list_captions_for_asset((int)$asset['id'], 20) : [];
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Generate Caption — Personal AI Auto Poster</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <main class="wrap">
    <section class="card hero">
      <div>
        <p class="eyebrow">Phase 3</p>
        <h1>สร้างแคปชั่นด้วย AI</h1>
        <p class="muted">เลือกวิดีโอ ใส่หัวข้อ แล้วให้ระบบช่วยเขียน caption ภาษาไทย</p>
      </div>
      <div class="actions">
        <a class="button secondary" href="/media.php">Media Library</a>
        <a class="button secondary" href="/captions.php">Captions</a>
        <a class="button secondary" href="/">Dashboard</a>
      </div>
    </section>

    <?php if ($error !== ''): ?>
      <section class="notice error"><strong>ไม่สำเร็จ:</strong> <?= h($error) ?></section>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
      <section class="notice success"><strong><?= h($success) ?></strong></section>
    <?php endif; ?>

    <?php if ($asset): ?>
      <section class="grid">
        <div class="card">
          <h2>วิดีโอที่เลือก</h2>
          <p class="muted">Asset ID: #<?= h((string)$asset['id']) ?></p>
          <video class="video-preview" controls preload="metadata" src="/media-file.php?id=<?= h((string)$asset['id']) ?>"></video>
          <table class="detail-table"><tbody>
            <tr><th>ชื่อไฟล์</th><td><?= h($asset['original_name'] ?: basename((string)$asset['local_path'])) ?></td></tr>
            <tr><th>ความยาว</th><td><?= h(human_duration(isset($asset['duration_sec']) ? (float)$asset['duration_sec'] : null)) ?></td></tr>
            <tr><th>ความละเอียด</th><td><?= h((string)($asset['width'] ?? '-')) ?>x<?= h((string)($asset['height'] ?? '-')) ?></td></tr>
            <tr><th>Codec</th><td><?= h((string)($asset['video_codec'] ?? '-')) ?> / <?= h((string)($asset['audio_codec'] ?? '-')) ?></td></tr>
          </tbody></table>
        </div>

        <div class="card">
          <h2>ข้อมูลสำหรับ AI</h2>
          <form method="post" class="upload-form">
            <input type="hidden" name="asset_id" value="<?= h((string)$asset['id']) ?>">
            <label><strong>หัวข้อคลิป</strong><input class="input" type="text" name="topic" value="<?= h($form['topic']) ?>" required></label>
            <label><strong>โทน</strong><input class="input" type="text" name="tone" value="<?= h($form['tone']) ?>"></label>
            <label><strong>กลุ่มเป้าหมาย</strong><input class="input" type="text" name="target_audience" value="<?= h($form['target_audience']) ?>"></label>
            <div class="form-grid">
              <label><strong>ความยาวสูงสุด</strong><input class="input" type="number" name="max_length" value="<?= h($form['max_length']) ?>" min="1" max="2200"></label>
              <label><strong>จำนวน hashtag</strong><input class="input" type="number" name="hashtag_count" value="<?= h($form['hashtag_count']) ?>" min="0" max="10"></label>
            </div>
            <button class="button" type="submit">Generate Caption</button>
          </form>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($newCaption): ?>
      <section class="card">
        <h2>แคปชั่นล่าสุด</h2>
        <textarea class="textarea" readonly><?= h((string)$newCaption['caption_text']) ?></textarea>
      </section>
    <?php endif; ?>

    <?php if (count($captions) > 0): ?>
      <section class="card">
        <h2>แคปชั่นของวิดีโอนี้</h2>
        <div class="media-list">
          <?php foreach ($captions as $caption): ?>
            <article class="media-item caption-card">
              <div>
                <h3>#<?= h((string)$caption['id']) ?> · <?= h((string)($caption['topic'] ?? '-')) ?></h3>
                <p><?= nl2br(h((string)$caption['caption_text'])) ?></p>
                <p class="muted"><?= h((string)$caption['created_at']) ?> · <?= h((string)($caption['model_name'] ?? '-')) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
