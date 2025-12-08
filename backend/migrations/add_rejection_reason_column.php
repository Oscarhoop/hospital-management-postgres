<?php
/**
 * Migration script to add rejection_reason column to leave_requests table
 */

require_once __DIR__ . '/../db.php';

function log_message($message) {
    echo "[MIGRATION] $message\n";
}

function get_driver(): string {
    $config = include __DIR__ . '/../config.php';
    if (!empty($config['driver'])) {
        return $config['driver'];
    }
    $dsn = $config['dsn'] ?? '';
    return str_starts_with($dsn, 'pgsql:') ? 'pgsql' : 'sqlite';
}

try {
    $pdo = get_pdo();
    $driver = get_driver();
    
    log_message("Adding rejection_reason column to leave_requests table for $driver");
    
    // Check if column already exists
    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns 
                              WHERE table_name = 'leave_requests' AND column_name = 'rejection_reason'");
        $stmt->execute();
        if ($stmt->fetch()) {
            log_message("rejection_reason column already exists");
            exit(0);
        }
        
        // Add column for PostgreSQL
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN rejection_reason TEXT");
        log_message("Added rejection_reason column to leave_requests table (PostgreSQL)");
        
    } else {
        // SQLite version
        $stmt = $pdo->prepare("PRAGMA table_info(leave_requests)");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            if ($column['name'] === 'rejection_reason') {
                log_message("rejection_reason column already exists");
                exit(0);
            }
        }
        
        // For SQLite, we need to recreate the table
        $pdo->exec("ALTER TABLE leave_requests RENAME TO leave_requests_old");
        
        $pdo->exec("CREATE TABLE leave_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            leave_type TEXT NOT NULL,
            reason TEXT,
            status TEXT NOT NULL DEFAULT 'pending',
            approved_by INTEGER,
            approved_at DATETIME,
            rejection_reason TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
        )");
        
        $pdo->exec("INSERT INTO leave_requests (id, user_id, start_date, end_date, leave_type, reason, status, approved_by, approved_at, created_at)
                    SELECT id, user_id, start_date, end_date, leave_type, reason, status, approved_by, approved_at, created_at
                    FROM leave_requests_old");
        
        $pdo->exec("DROP TABLE leave_requests_old");
        log_message("Added rejection_reason column to leave_requests table (SQLite)");
    }
    
    log_message("Migration completed successfully");
    
} catch (PDOException $e) {
    log_message('Error: ' . $e->getMessage());
    exit(1);
}
