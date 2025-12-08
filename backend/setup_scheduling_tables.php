<?php
/**
 * Script to set up scheduling-related database tables
 */

require_once __DIR__ . '/db.php';

function log_message($message) {
    echo "[SETUP] $message\n";
}

function get_driver(): string {
    $config = include __DIR__ . '/config.php';
    if (!empty($config['driver'])) {
        return $config['driver'];
    }
    $dsn = $config['dsn'] ?? '';
    return str_starts_with($dsn, 'pgsql:') ? 'pgsql' : 'sqlite';
}

try {
    $pdo = get_pdo();
    $driver = get_driver();

    if ($driver === 'sqlite') {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    $pdo->beginTransaction();

    $tableSql = [];

    if ($driver === 'pgsql') {
        $tableSql = [
            <<<'SQL'
CREATE TABLE IF NOT EXISTS shift_templates (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name TEXT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    color TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS staff_schedules (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    schedule_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status TEXT NOT NULL DEFAULT 'scheduled',
    notes TEXT,
    shift_template_id BIGINT REFERENCES shift_templates(id) ON DELETE SET NULL,
    created_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ
);
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS leave_requests (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    leave_type TEXT NOT NULL,
    reason TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    approved_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    approved_at TIMESTAMPTZ,
    rejection_reason TEXT,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS shift_swaps (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    requesting_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    target_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    schedule_id BIGINT NOT NULL REFERENCES staff_schedules(id) ON DELETE CASCADE,
    reason TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    approved_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    approved_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS audit_trail (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    action TEXT NOT NULL,
    type TEXT NOT NULL,
    record_id BIGINT,
    data TEXT,
    created_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
SQL,
        ];
    } else {
        $tableSql = [
            <<<'SQL'
CREATE TABLE IF NOT EXISTS shift_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    color TEXT,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS staff_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    schedule_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status TEXT NOT NULL DEFAULT 'scheduled',
    notes TEXT,
    shift_template_id INTEGER,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (shift_template_id) REFERENCES shift_templates(id) ON DELETE SET NULL
);
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS leave_requests (
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
);
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS shift_swaps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    requesting_user_id INTEGER NOT NULL,
    target_user_id INTEGER NOT NULL,
    schedule_id INTEGER NOT NULL,
    reason TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    approved_by INTEGER,
    approved_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requesting_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES staff_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS audit_trail (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action TEXT NOT NULL,
    type TEXT NOT NULL,
    record_id INTEGER,
    data TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
SQL,
        ];
    }

    foreach ($tableSql as $sql) {
        $pdo->exec($sql);
    }

    $stmt = $pdo->query('SELECT COUNT(*) as count FROM shift_templates');
    $count = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    if ($count === 0) {
        log_message('Adding sample shift templates...');
        $templates = [
            ['Morning Shift', '08:00:00', '16:00:00', '#4CAF50'],
            ['Afternoon Shift', '14:00:00', '22:00:00', '#2196F3'],
            ['Night Shift', '22:00:00', '06:00:00', '#9C27B0'],
            ['Weekend Shift', '09:00:00', '17:00:00', '#FF9800']
        ];

        $stmt = $pdo->prepare('INSERT INTO shift_templates (name, start_time, end_time, color) VALUES (?, ?, ?, ?)');
        foreach ($templates as $template) {
            $stmt->execute($template);
        }
        log_message('Added ' . count($templates) . ' sample shift templates');
    }

    $pdo->commit();
    log_message('Scheduling tables created successfully for ' . $driver);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    log_message('Error: ' . $e->getMessage());
    log_message('Stack trace: ' . $e->getTraceAsString());
    exit(1);
}
