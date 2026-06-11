<?php
declare(strict_types=1);

function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_OK => 'Upload complete.',
        UPLOAD_ERR_INI_SIZE => 'File is larger than upload_max_filesize in php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'File is larger than MAX_FILE_SIZE in the form.',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload directory.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write upload to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.',
        default => 'Unknown upload error code: ' . $code,
    };
}

function allowed_video_mime_types(): array
{
    return [
        'video/mp4',
        'video/quicktime',
        'video/webm',
        'video/x-m4v',
        'application/octet-stream', // Some Windows builds report mp4/mov this way; ffprobe is the final validator.
    ];
}

function allowed_video_extensions(): array
{
    return ['mp4', 'mov', 'm4v', 'webm'];
}

function safe_original_name(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[^A-Za-z0-9._ -]+/u', '_', $name) ?: 'video';
    return trim($name, ' ._-') ?: 'video';
}

function detect_mime_type(string $path): string
{
    if (!extension_loaded('fileinfo')) {
        throw new RuntimeException('PHP fileinfo extension is not loaded.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($path);
    return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
}

function handle_video_upload(array $file): int
{
    $config = app_config();
    $maxMb = (int)($config['upload_max_mb'] ?? 500);
    $maxBytes = $maxMb * 1024 * 1024;

    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message($error));
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Upload temp file is not valid.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Uploaded file is empty.');
    }
    if ($size > $maxBytes) {
        throw new RuntimeException('Uploaded file is too large. App limit is ' . $maxMb . ' MB.');
    }

    $originalName = safe_original_name((string)($file['name'] ?? 'video'));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, allowed_video_extensions(), true)) {
        throw new RuntimeException('File extension .' . $extension . ' is not allowed. Allowed: ' . implode(', ', allowed_video_extensions()));
    }

    $mimeType = detect_mime_type($tmpName);
    if (!in_array($mimeType, allowed_video_mime_types(), true)) {
        throw new RuntimeException('MIME type is not allowed: ' . $mimeType);
    }

    $dateDir = date('Y-m');
    $targetDir = storage_path('input' . DIRECTORY_SEPARATOR . $dateDir);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Cannot create upload directory: ' . $targetDir);
    }

    $safeId = date('Ymd_His') . '_' . bin2hex(random_bytes(6));
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $safeId . '.' . $extension;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new RuntimeException('Cannot move uploaded file to storage/input.');
    }

    try {
        $metadata = ffprobe_metadata($targetPath);
    } catch (Throwable $e) {
        @unlink($targetPath);
        throw new RuntimeException('Uploaded file failed ffprobe validation: ' . $e->getMessage());
    }

    $assetId = save_video_asset([
        'original_name' => $originalName,
        'local_path' => $targetPath,
        'public_url' => null,
        'mime_type' => $mimeType,
        'bytes' => $metadata['bytes'] ?? filesize($targetPath),
        'duration_sec' => $metadata['duration_sec'] ?? null,
        'width' => $metadata['width'] ?? null,
        'height' => $metadata['height'] ?? null,
        'sha256' => hash_file('sha256', $targetPath),
        'extension' => $extension,
        'video_codec' => $metadata['video_codec'] ?? null,
        'audio_codec' => $metadata['audio_codec'] ?? null,
        'ffprobe_json' => json_encode($metadata['raw'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'status' => 'ready',
    ]);

    app_log('info', 'video uploaded', [
        'asset_id' => $assetId,
        'original_name' => $originalName,
        'path' => $targetPath,
        'mime_type' => $mimeType,
        'bytes' => $metadata['bytes'] ?? $size,
    ]);

    return $assetId;
}
