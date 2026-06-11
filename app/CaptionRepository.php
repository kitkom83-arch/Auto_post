<?php
declare(strict_types=1);

function save_caption(array $caption): int
{
    $sql = 'INSERT INTO captions (
        media_asset_id, topic, tone, target_audience, prompt, prompt_text, caption_text, model_name, raw_response_json
    ) VALUES (
        :media_asset_id, :topic, :tone, :target_audience, :prompt, :prompt_text, :caption_text, :model_name, :raw_response_json
    )';

    $promptText = (string)$caption['prompt_text'];

    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':media_asset_id' => $caption['media_asset_id'],
        ':topic' => $caption['topic'],
        ':tone' => $caption['tone'] ?? null,
        ':target_audience' => $caption['target_audience'] ?? null,
        ':prompt' => $promptText,
        ':prompt_text' => $promptText,
        ':caption_text' => $caption['caption_text'],
        ':model_name' => $caption['model_name'] ?? null,
        ':raw_response_json' => $caption['raw_response_json'] ?? null,
    ]);

    return (int)db()->lastInsertId();
}

function find_caption(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM captions WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function list_captions_for_asset(int $mediaAssetId, int $limit = 20): array
{
    $limit = max(1, min($limit, 100));
    $stmt = db()->prepare("SELECT * FROM captions WHERE media_asset_id = :media_asset_id ORDER BY id DESC LIMIT {$limit}");
    $stmt->execute([':media_asset_id' => $mediaAssetId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function list_recent_captions(int $limit = 50): array
{
    $limit = max(1, min($limit, 200));
    $stmt = db()->prepare("SELECT c.*, m.original_name, m.local_path, m.duration_sec, m.width, m.height
        FROM captions c
        LEFT JOIN media_assets m ON m.id = c.media_asset_id
        ORDER BY c.id DESC
        LIMIT {$limit}");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function validate_caption_request(array $input): array
{
    $assetId = (int)($input['asset_id'] ?? 0);
    if ($assetId <= 0) {
        throw new RuntimeException('ไม่พบ asset_id ของวิดีโอ');
    }

    $topic = trim((string)($input['topic'] ?? ''));
    if ($topic === '') {
        throw new RuntimeException('กรุณาใส่หัวข้อคลิปก่อนสร้างแคปชั่น');
    }

    $maxLength = (int)($input['max_length'] ?? 150);
    if ($maxLength <= 0) {
        $maxLength = 150;
    }
    $maxLength = min($maxLength, 2200);

    $hashtagCount = (int)($input['hashtag_count'] ?? 4);
    $hashtagCount = max(0, min($hashtagCount, 10));

    return [
        'asset_id' => $assetId,
        'topic' => $topic,
        'tone' => trim((string)($input['tone'] ?? '')),
        'target_audience' => trim((string)($input['target_audience'] ?? '')),
        'max_length' => $maxLength,
        'hashtag_count' => $hashtagCount,
    ];
}
