<?php
// Central database configuration with APP_ENV-aware bootstrap support.
// Supports both SQLite (default) and PostgreSQL based on DB_CONNECTION.

require_once __DIR__ . '/env.php';
load_project_env();

$projectRoot = dirname(__DIR__);

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
