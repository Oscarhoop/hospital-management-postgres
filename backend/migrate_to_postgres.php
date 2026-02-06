<?php
/**
 * Migration script to transfer data from SQLite to PostgreSQL (Supabase)
 * 
 * Usage:
 * 1. Ensure your .env file has Supabase credentials (DB_CONNECTION=pgsql)
 * 2. Run: php backend/migrate_to_postgres.php
 * 
 * This script will:
 * - Initialize the PostgreSQL schema
 * - Export data from SQLite
 * - Import data into PostgreSQL
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "============================================\n";
echo "SQLite to PostgreSQL Migration Tool\n";
echo "============================================\n\n";

// Step 1: Check if SQLite database exists
$projectRoot = dirname(__DIR__);
$sqlitePath = $projectRoot . '/backend/data/database.sqlite';

if (!file_exists($sqlitePath)) {
    die("❌ SQLite database not found at: {$sqlitePath}\n");
}

echo "✓ Found SQLite database: {$sqlitePath}\n";

// Step 2: Connect to SQLite
try {
    $sqlitePdo = new PDO('sqlite:' . $sqlitePath);
    $sqlitePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to SQLite database\n\n";
} catch (PDOException $e) {
    die("❌ Could not connect to SQLite: " . $e->getMessage() . "\n");
}

// Step 3: Load PostgreSQL config and connect
require_once __DIR__ . '/config.php';
$config = include __DIR__ . '/config.php';

if ($config['driver'] !== 'pgsql') {
    die("❌ Please set DB_CONNECTION=pgsql in your .env file first!\n");
}

echo "Connecting to PostgreSQL (Supabase)...\n";
echo "  Host: " . (getenv('DB_HOST') ?: 'not set') . "\n";
echo "  Database: " . (getenv('DB_DATABASE') ?: 'not set') . "\n\n";

try {
    require_once __DIR__ . '/db.php';
    $pgPdo = get_pdo();
    echo "✓ Connected to PostgreSQL (Supabase)\n\n";
} catch (PDOException $e) {
    die("❌ Could not connect to PostgreSQL: " . $e->getMessage() . "\n");
}

// Step 4: Initialize PostgreSQL schema
echo "Initializing PostgreSQL schema...\n";
require_once __DIR__ . '/init_db.php';
echo "\n";

// Step 5: Migrate data
$tables = ['users', 'patients', 'doctors', 'rooms', 'appointments', 'medical_records', 'billing', 'audit_trail'];

echo "Starting data migration...\n";
echo "============================================\n";

foreach ($tables as $table) {
    echo "\nMigrating table: {$table}\n";
    
    try {
        // Get all data from SQLite
        $stmt = $sqlitePdo->query("SELECT * FROM {$table}");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = count($rows);
        
        if ($count === 0) {
            echo "  ⚠ No data to migrate (empty table)\n";
            continue;
        }
        
        echo "  Found {$count} rows\n";
        
        // Get column names (excluding 'id' which is auto-generated)
        $firstRow = $rows[0];
        $columns = array_keys($firstRow);
        $columns = array_filter($columns, fn($col) => $col !== 'id');
        
        // Prepare insert statement for PostgreSQL
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $columnList = implode(',', $columns);
        $insertSql = "INSERT INTO {$table} ({$columnList}) VALUES ({$placeholders})";
        $insertStmt = $pgPdo->prepare($insertSql);
        
        // Insert each row
        $pgPdo->beginTransaction();
        $migrated = 0;
        
        foreach ($rows as $row) {
            // Remove 'id' from row data
            unset($row['id']);
            
            // Convert boolean values for PostgreSQL if needed
            foreach ($row as $key => $value) {
                if ($value === '1' && in_array($key, ['is_active', 'is_available'])) {
                    $row[$key] = true;
                } elseif ($value === '0' && in_array($key, ['is_active', 'is_available'])) {
                    $row[$key] = false;
                }
            }
            
            $values = array_values($row);
            $insertStmt->execute($values);
            $migrated++;
        }
        
        $pgPdo->commit();
        echo "  ✓ Successfully migrated {$migrated} rows\n";
        
    } catch (Exception $e) {
        if ($pgPdo->inTransaction()) {
            $pgPdo->rollBack();
        }
        echo "  ❌ Error migrating {$table}: " . $e->getMessage() . "\n";
    }
}

echo "\n============================================\n";
echo "Migration Complete!\n";
echo "============================================\n\n";

// Step 6: Verify migration
echo "Verification Summary:\n";
echo "--------------------------------------------\n";

foreach ($tables as $table) {
    try {
        $sqliteCount = $sqlitePdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        $pgCount = $pgPdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        
        $status = $sqliteCount == $pgCount ? '✓' : '⚠';
        echo sprintf("  %s %-20s SQLite: %4d | PostgreSQL: %4d\n", 
            $status, $table . ':', $sqliteCount, $pgCount);
    } catch (Exception $e) {
        echo "  ❌ {$table}: Could not verify\n";
    }
}

echo "\n============================================\n";
echo "Next Steps:\n";
echo "1. Verify your data in Supabase Dashboard\n";
echo "2. Test your application thoroughly\n";
echo "3. Create a backup of your SQLite database\n";
echo "4. Consider keeping SQLite as a backup\n";
echo "============================================\n";
?>
