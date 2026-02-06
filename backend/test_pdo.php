<?php
/**
 * Database Connection Test
 * Tests connection to SQLite or PostgreSQL (Supabase)
 */

header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "============================================\n";
echo "Database Connection Test\n";
echo "============================================\n\n";

// Load configuration
$config = include __DIR__ . '/config.php';
$driver = $config['driver'];

echo "Configuration:\n";
echo "  Driver: {$driver}\n";

if ($driver === 'pgsql') {
    $host = getenv('DB_HOST') ?: 'not set';
    $port = getenv('DB_PORT') ?: 'not set';
    $database = getenv('DB_DATABASE') ?: 'not set';
    $user = getenv('DB_USERNAME') ?: 'not set';
    
    echo "  Host: {$host}\n";
    echo "  Port: {$port}\n";
    echo "  Database: {$database}\n";
    echo "  User: {$user}\n";
    
    if (strpos($host, 'supabase.co') !== false) {
        echo "  Type: Supabase (PostgreSQL)\n";
    }
} else {
    $dbPath = getenv('DB_PATH') ?: dirname(__DIR__) . '/backend/data/database.sqlite';
    echo "  Database: {$dbPath}\n";
}

echo "\n";
echo "Testing connection...\n";

try {
    require_once __DIR__ . '/db.php';
    $pdo = get_pdo();
    
    echo "✓ Connection successful!\n";
    echo "  PDO Driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";
    echo "  Server Version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
    
    // Test a simple query
    if ($driver === 'pgsql') {
        $result = $pdo->query("SELECT version()");
        $version = $result->fetchColumn();
        echo "  PostgreSQL: " . substr($version, 0, 50) . "...\n";
    } else {
        $result = $pdo->query("SELECT sqlite_version()");
        $version = $result->fetchColumn();
        echo "  SQLite Version: {$version}\n";
    }
    
    echo "\n";
    echo "============================================\n";
    echo "✓ Connection test PASSED\n";
    echo "============================================\n";
    
} catch (Exception $ex) {
    echo "❌ Connection FAILED\n";
    echo "Error: " . $ex->getMessage() . "\n\n";
    
    if ($driver === 'pgsql') {
        echo "Troubleshooting Tips:\n";
        echo "  1. Check your .env file has correct Supabase credentials\n";
        echo "  2. Verify DB_HOST is: db.xxxxx.supabase.co\n";
        echo "  3. Ensure DB_DATABASE is: postgres\n";
        echo "  4. Check your password is correct\n";
        echo "  5. Verify your Supabase project is active\n";
    } else {
        echo "Troubleshooting Tips:\n";
        echo "  1. Check if SQLite database file exists\n";
        echo "  2. Verify file permissions are correct\n";
        echo "  3. Ensure PDO SQLite extension is enabled\n";
    }
    
    echo "\n============================================\n";
    exit(1);
}
?>
