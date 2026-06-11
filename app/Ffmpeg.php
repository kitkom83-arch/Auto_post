<?php
declare(strict_types=1);

function command_exists(string $command): bool
{
    $probe = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
        ? 'where ' . escapeshellarg($command)
        : 'command -v ' . escapeshellarg($command);

    $output = [];
    $code = 1;
    @exec($probe . ' 2>&1', $output, $code);
    return $code === 0;
}

function ffprobe_json(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('Video file not found: ' . $path);
    }

    if (!command_exists('ffprobe')) {
        throw new RuntimeException('ffprobe command not found. Install FFmpeg and restart your terminal/server.');
    }

    $cmd = 'ffprobe -v error -show_format -show_streams -output_format json ' . escapeshellarg($path);
    $output = [];
    $code = 1;
    @exec($cmd . ' 2>&1', $output, $code);
    $json = trim(implode("\n", $output));

    if ($code !== 0 || $json === '') {
        throw new RuntimeException('ffprobe failed: ' . ($json !== '' ? $json : 'empty output'));
    }

    try {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('ffprobe returned invalid JSON: ' . $e->getMessage());
    }

    if (!is_array($data)) {
        throw new RuntimeException('ffprobe output is not an object.');
    }

    return $data;
}

function ffprobe_metadata(string $path): array
{
    $data = ffprobe_json($path);
    $streams = $data['streams'] ?? [];
    $format = $data['format'] ?? [];

    if (!is_array($streams)) {
        $streams = [];
    }
    if (!is_array($format)) {
        $format = [];
    }

    $video = null;
    $audio = null;

    foreach ($streams as $stream) {
        if (!is_array($stream)) {
            continue;
        }
        if (($stream['codec_type'] ?? '') === 'video' && $video === null) {
            $video = $stream;
        }
        if (($stream['codec_type'] ?? '') === 'audio' && $audio === null) {
            $audio = $stream;
        }
    }

    if ($video === null) {
        throw new RuntimeException('No video stream found. Please upload a real video file.');
    }

    $duration = isset($format['duration']) ? (float)$format['duration'] : null;
    if ($duration === null || $duration <= 0) {
        $duration = isset($video['duration']) ? (float)$video['duration'] : null;
    }

    $bytes = isset($format['size']) ? (int)$format['size'] : filesize($path);

    return [
        'duration_sec' => $duration,
        'bytes' => $bytes ?: filesize($path),
        'width' => isset($video['width']) ? (int)$video['width'] : null,
        'height' => isset($video['height']) ? (int)$video['height'] : null,
        'video_codec' => (string)($video['codec_name'] ?? ''),
        'audio_codec' => $audio !== null ? (string)($audio['codec_name'] ?? '') : '',
        'format_name' => (string)($format['format_name'] ?? ''),
        'raw' => $data,
    ];
}

function human_bytes(int|float|null $bytes): string
{
    if ($bytes === null) {
        return '-';
    }
    $bytes = (float)$bytes;
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return rtrim(rtrim(number_format($bytes, 2), '0'), '.') . ' ' . $units[$i];
}

function human_duration(float|int|null $seconds): string
{
    if ($seconds === null || $seconds <= 0) {
        return '-';
    }

    $total = (int)round((float)$seconds);
    $minutes = intdiv($total, 60);
    $secs = $total % 60;

    if ($minutes >= 60) {
        $hours = intdiv($minutes, 60);
        $minutes = $minutes % 60;
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    }

    return sprintf('%d:%02d', $minutes, $secs);
}
