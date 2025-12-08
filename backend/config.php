<?php
// Central database configuration with basic .env loading.
// Supports both SQLite (default) and PostgreSQL based on DB_CONNECTION.

// Lightweight .env loader so local values can be stored outside version control.
$projectRoot = dirname(__DIR__);
$envFile = $projectRoot . '/.env';
if (file_exists($envFile)) {
    $content = file_get_contents($envFile);
    // Handle UTF-16 LE with BOM (Windows PowerShell default)
    if (substr($content, 0, 2) === "\xFF\xFE") {
        $content = iconv('UTF-16LE', 'UTF-8', $content);
    }
    // Remove UTF-8 BOM if present
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        if ($key !== '') {
            putenv("{$key}={$value}");
        }
    }
}

$driver = strtolower(getenv('DB_CONNECTION') ?: 'sqlite');

if ($driver === 'pgsql') {
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '5432';
    $database = getenv('DB_DATABASE') ?: 'hospital_mgmt';
    $user = getenv('DB_USERNAME') ?: 'hospital_app';
    $pass = getenv('DB_PASSWORD') ?: '';

    return [
        'dsn' => sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $database),
        'user' => $user,
        'pass' => $pass,
        'driver' => 'pgsql',
    ];
}

// Default to SQLite (primarily used for quick local testing)
$dbPath = getenv('DB_PATH') ?: ($projectRoot . '/backend/data/database.sqlite');

return [
    'dsn' => 'sqlite:' . $dbPath,
    'user' => null,
    'pass' => null,
    'driver' => 'sqlite',
];
