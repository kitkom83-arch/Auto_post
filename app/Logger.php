<?php
declare(strict_types=1);

function redact_context(array $context): array
{
    $blocked = ['token', 'secret', 'password', 'key', 'authorization', 'cookie'];
    $out = [];

    foreach ($context as $key => $value) {
        $lower = strtolower((string)$key);
        $shouldRedact = false;
        foreach ($blocked as $word) {
            if (str_contains($lower, $word)) {
                $shouldRedact = true;
                break;
            }
        }
        $out[$key] = $shouldRedact ? '[redacted]' : $value;
    }

    return $out;
}

function app_log(string $level, string $message, array $context = []): void
{
    $dir = storage_path('logs');
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $entry = [
        'time' => date('c'),
        'level' => strtoupper($level),
        'message' => $message,
        'context' => redact_context($context),
    ];

    $file = $dir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.jsonl';
    file_put_contents(
        $file,
        json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}
