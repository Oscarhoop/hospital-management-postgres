<?php
// db.php - helper utilities for database access

require_once __DIR__ . '/env.php';
configure_error_handling();

function get_db_config() {
    static $config = null;
    if ($config === null) {
        $config = include __DIR__ . '/config.php';
    }
    return $config;
}

function get_db_driver(): string {
    $cfg = get_db_config();
    if (!empty($cfg['driver'])) {
        return strtolower($cfg['driver']);
    }
    $dsn = $cfg['dsn'] ?? '';
    return str_starts_with($dsn, 'pgsql:') ? 'pgsql' : 'sqlite';
}

function get_pdo() {
    static $pdo = null;
    if ($pdo) return $pdo;

    $cfg = get_db_config();
    $dsn = $cfg['dsn'];
    $user = $cfg['user'];
    $pass = $cfg['pass'];

    try {
        // Ensure SQLite file directories exist when using sqlite DSN
        if (strpos($dsn, 'sqlite:') === 0) {
            $dbPath = substr($dsn, strlen('sqlite:'));
            $dbDir = dirname($dbPath);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0777, true);
            }
        }

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        error_log('Database error: ' . $e->getMessage());
        $message = is_production() ? 'DB connection failed' : ('DB connection failed: ' . $e->getMessage());
        echo json_encode(['error' => $message]);
        exit;
    }
    return $pdo;
}
