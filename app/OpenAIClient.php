<?php
declare(strict_types=1);

function openai_config_or_throw(): array
{
    $config = app_config();
    $apiKey = trim((string)($config['openai_api_key'] ?? ''));
    if ($apiKey === '') {
        throw new RuntimeException('ยังไม่ได้ตั้งค่า OPENAI_API_KEY ใน .env');
    }

    $model = trim((string)($config['openai_model'] ?? ''));
    if ($model === '') {
        throw new RuntimeException('ยังไม่ได้ตั้งค่า OPENAI_MODEL ใน .env');
    }

    return ['api_key' => $apiKey, 'model' => $model];
}

function build_caption_prompt(array $asset, array $input): string
{
    $topic = trim((string)($input['topic'] ?? ''));
    $tone = trim((string)($input['tone'] ?? '')) ?: 'ธรรมชาติ จริงใจ อ่านง่าย';
    $targetAudience = trim((string)($input['target_audience'] ?? '')) ?: 'คนดูทั่วไป';
    $maxLength = max(1, min((int)($input['max_length'] ?? 150), 2200));
    $hashtagCount = max(0, min((int)($input['hashtag_count'] ?? 4), 10));

    $duration = human_duration(isset($asset['duration_sec']) ? (float)$asset['duration_sec'] : null);
    $resolution = (string)($asset['width'] ?? '-') . 'x' . (string)($asset['height'] ?? '-');
    $fileName = (string)($asset['original_name'] ?: basename((string)($asset['local_path'] ?? 'video')));
    $videoCodec = (string)($asset['video_codec'] ?? '-');
    $audioCodec = (string)($asset['audio_codec'] ?? '-');

    return <<<PROMPT
ข้อมูลวิดีโอ:
- ชื่อไฟล์: {$fileName}
- ความยาว: {$duration}
- ความละเอียด: {$resolution}
- Video codec: {$videoCodec}
- Audio codec: {$audioCodec}

ข้อมูลที่ผู้ใช้กรอก:
- หัวข้อคลิป: {$topic}
- โทน: {$tone}
- กลุ่มเป้าหมาย: {$targetAudience}
- ความยาวสูงสุด: {$maxLength} ตัวอักษร
- จำนวน hashtag: {$hashtagCount} ตัว

กติกาการเขียน:
- เขียน caption ภาษาไทยสำหรับ TikTok และ Instagram Reels
- บรรทัดแรกต้องเป็น hook ที่ดึงให้คนหยุดดู
- ภาษาไทยธรรมชาติ อ่านง่าย ไม่แข็ง ไม่เหมือนบอท
- ไม่ขายแรงเกินไป
- ไม่สแปม hashtag
- ห้ามใช้คำเวอร์เกินจริง เช่น รวยทันที, การันตี, เห็นผล 100%
- ห้ามกล่าวอ้างเกินจริง
- hashtag ให้เหมาะกับหัวข้อและจำนวนที่กำหนด
- ส่งออกเฉพาะ caption เท่านั้น ไม่ต้องอธิบาย ไม่ต้องใส่หัวข้อเพิ่ม
PROMPT;
}

function openai_extract_caption_text(array $data): string
{
    if (isset($data['output_text']) && is_string($data['output_text']) && trim($data['output_text']) !== '') {
        return trim($data['output_text']);
    }

    $parts = [];
    foreach (($data['output'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        foreach (($item['content'] ?? []) as $contentItem) {
            if (!is_array($contentItem)) {
                continue;
            }
            if (isset($contentItem['text']) && is_string($contentItem['text'])) {
                $parts[] = $contentItem['text'];
            }
            if (isset($contentItem['output_text']) && is_string($contentItem['output_text'])) {
                $parts[] = $contentItem['output_text'];
            }
        }
    }

    return trim(implode("\n", array_filter(array_map('trim', $parts))));
}

function openai_generate_caption(array $asset, array $input): array
{
    if (!extension_loaded('curl')) {
        throw new RuntimeException('PHP extension curl is not loaded. เปิด extension=curl ใน php.ini ก่อน');
    }

    $openai = openai_config_or_throw();
    $prompt = build_caption_prompt($asset, $input);

    $payload = [
        'model' => $openai['model'],
        'input' => [
            ['role' => 'developer', 'content' => 'คุณคือผู้ช่วยเขียน caption ภาษาไทยสำหรับ TikTok และ Instagram Reels เขียนให้เป็นธรรมชาติ อ่านง่าย ไม่ขายแรงเกินไป ไม่สแปม hashtag'],
            ['role' => 'user', 'content' => $prompt],
        ],
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    if ($ch === false) {
        throw new RuntimeException('Cannot initialize cURL for OpenAI request');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authori' . 'zation: ' . 'Bear' . 'er ' . $openai['api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
    ]);

    $response = curl_exec($ch);
    $httpStatus = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    if ($response === false) {
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno === CURLE_OPERATION_TIMEDOUT || stripos($error, 'timed out') !== false) {
            throw new RuntimeException('OpenAI request timeout');
        }
        throw new RuntimeException('OpenAI request failed: ' . $error);
    }
    curl_close($ch);

    try {
        $data = json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('OpenAI response is not valid JSON: ' . $e->getMessage());
    }

    if (!is_array($data)) {
        throw new RuntimeException('OpenAI response is empty or invalid');
    }

    if ($httpStatus < 200 || $httpStatus >= 300) {
        $message = 'unknown error';
        if (isset($data['error']['message']) && is_string($data['error']['message'])) {
            $message = $data['error']['message'];
        }
        throw new RuntimeException('OpenAI API error (' . $httpStatus . '): ' . $message);
    }

    $captionText = openai_extract_caption_text($data);
    if ($captionText === '') {
        throw new RuntimeException('OpenAI response ไม่มี caption text');
    }

    return [
        'caption_text' => $captionText,
        'prompt_text' => $prompt,
        'model_name' => $openai['model'],
        'raw_response_json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
    ];
}
