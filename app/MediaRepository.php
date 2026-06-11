<?php
declare(strict_types=1);

function db_has_column(string $table, string $column): bool
{
    $stmt = db()->query('PRAGMA table_info(' . $table . ')');
    $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($columns as $row) {
        if (($row['name'] ?? '') === $column) {
            return true;
        }
    }
    return false;
}

function save_video_asset(array $asset): int
{
    $sql = 'INSERT INTO media_assets (
        kind, original_name, local_path, public_url, mime_type, bytes, duration_sec, width, height,
        sha256, extension, video_codec, audio_codec, ffprobe_json, status, error_message,
        license_type, license_source, license_url
    ) VALUES (
        :kind, :original_name, :local_path, :public_url, :mime_type, :bytes, :duration_sec, :width, :height,
        :sha256, :extension, :video_codec, :audio_codec, :ffprobe_json, :status, :error_message,
        :license_type, :license_source, :license_url
    )';

    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':kind' => 'video',
        ':original_name' => $asset['original_name'] ?? null,
        ':local_path' => $asset['local_path'] ?? null,
        ':public_url' => $asset['public_url'] ?? null,
        ':mime_type' => $asset['mime_type'] ?? null,
        ':bytes' => $asset['bytes'] ?? null,
        ':duration_sec' => $asset['duration_sec'] ?? null,
        ':width' => $asset['width'] ?? null,
        ':height' => $asset['height'] ?? null,
        ':sha256' => $asset['sha256'] ?? null,
        ':extension' => $asset['extension'] ?? null,
        ':video_codec' => $asset['video_codec'] ?? null,
        ':audio_codec' => $asset['audio_codec'] ?? null,
        ':ffprobe_json' => $asset['ffprobe_json'] ?? null,
        ':status' => $asset['status'] ?? 'ready',
        ':error_message' => $asset['error_message'] ?? null,
        ':license_type' => $asset['license_type'] ?? null,
        ':license_source' => $asset['license_source'] ?? null,
        ':license_url' => $asset['license_url'] ?? null,
    ]);

    return (int)db()->lastInsertId();
}

function list_media_assets(string $kind = 'video', int $limit = 50): array
{
    $limit = max(1, min($limit, 200));
    $stmt = db()->prepare("SELECT * FROM media_assets WHERE kind = :kind ORDER BY id DESC LIMIT {$limit}");
    $stmt->execute([':kind' => $kind]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function find_media_asset(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM media_assets WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function asset_relative_path(string $absolutePath): string
{
    $base = rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (str_starts_with($absolutePath, $base)) {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($absolutePath, strlen($base)));
    }
    return $absolutePath;
}
