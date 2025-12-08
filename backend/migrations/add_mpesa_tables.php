<?php
/**
 * M-Pesa Database Migration
 * Run this file once to add M-Pesa support to the database
 * 
 * Usage: php add_mpesa_tables.php
 */

require_once __DIR__ . '/../db.php';

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
    
    echo "Starting M-Pesa database migration using {$driver}...\n\n";
    
    // 1. Add M-Pesa columns to billing table
    echo "1. Adding M-Pesa columns to billing table...\n";
    
    $transactionStatusCheck = $driver === 'pgsql'
        ? "ALTER TABLE billing ADD COLUMN transaction_status TEXT DEFAULT NULL CHECK(transaction_status IN ('initiated','processing','completed','failed','cancelled'))"
        : "ALTER TABLE billing ADD COLUMN transaction_status TEXT DEFAULT NULL CHECK(transaction_status IN ('initiated', 'processing', 'completed', 'failed', 'cancelled'))";

    $alterBillingQueries = [
        "ALTER TABLE billing ADD COLUMN mpesa_checkout_request_id VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE billing ADD COLUMN mpesa_transaction_id VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE billing ADD COLUMN mpesa_receipt_number VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE billing ADD COLUMN mpesa_phone_number VARCHAR(20) DEFAULT NULL",
        "ALTER TABLE billing ADD COLUMN mpesa_amount NUMERIC(10,2) DEFAULT NULL",
        $transactionStatusCheck,
        "ALTER TABLE billing ADD COLUMN mpesa_response_description TEXT DEFAULT NULL",
    ];
    
    foreach ($alterBillingQueries as $query) {
        try {
            $pdo->exec($query);
            echo "  ✓ Executed: " . substr($query, 0, 80) . "...\n";
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'duplicate') !== false || stripos($e->getMessage(), 'exists') !== false) {
                echo "  ℹ Column already exists, skipping...\n";
            } else {
                echo "  ⚠ Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // 2. Create M-Pesa transactions table
    echo "\n2. Creating mpesa_transactions table...\n";
    
    if ($driver === 'pgsql') {
        $createTransactionsTable = <<<'SQL'
CREATE TABLE IF NOT EXISTS mpesa_transactions (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    billing_id BIGINT REFERENCES billing(id) ON DELETE SET NULL,
    merchant_request_id VARCHAR(100) NOT NULL,
    checkout_request_id VARCHAR(100) NOT NULL,
    result_code INTEGER,
    result_desc VARCHAR(255),
    mpesa_receipt_number VARCHAR(100),
    transaction_date TIMESTAMPTZ,
    phone_number VARCHAR(20) NOT NULL,
    amount NUMERIC(10,2) NOT NULL,
    account_reference VARCHAR(100),
    transaction_desc VARCHAR(255),
    transaction_type VARCHAR(50) DEFAULT 'STK_PUSH',
    status TEXT DEFAULT 'initiated' CHECK(status IN ('initiated','processing','completed','failed','cancelled','timeout')),
    callback_data TEXT,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
SQL;
        $updateTimestampTrigger = <<<'SQL'
CREATE OR REPLACE FUNCTION update_mpesa_transactions_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE TRIGGER trg_mpesa_transactions_updated
BEFORE UPDATE ON mpesa_transactions
FOR EACH ROW EXECUTE FUNCTION update_mpesa_transactions_updated_at();
SQL;
    } else {
        $createTransactionsTable = <<<'SQL'
CREATE TABLE IF NOT EXISTS mpesa_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    billing_id INTEGER DEFAULT NULL,
    merchant_request_id VARCHAR(100) NOT NULL,
    checkout_request_id VARCHAR(100) NOT NULL,
    result_code INTEGER DEFAULT NULL,
    result_desc VARCHAR(255) DEFAULT NULL,
    mpesa_receipt_number VARCHAR(100) DEFAULT NULL,
    transaction_date DATETIME DEFAULT NULL,
    phone_number VARCHAR(20) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    account_reference VARCHAR(100) DEFAULT NULL,
    transaction_desc VARCHAR(255) DEFAULT NULL,
    transaction_type VARCHAR(50) DEFAULT 'STK_PUSH',
    status TEXT DEFAULT 'initiated' CHECK(status IN ('initiated', 'processing', 'completed', 'failed', 'cancelled', 'timeout')),
    callback_data TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (billing_id) REFERENCES billing(id) ON DELETE SET NULL
);
SQL;
        $updateTimestampTrigger = '';
    }
    
    $pdo->exec($createTransactionsTable);
    if ($updateTimestampTrigger) {
        $pdo->exec($updateTimestampTrigger);
    }
    echo "  ✓ mpesa_transactions table created successfully\n";
    
    // 3. Create M-Pesa logs table for debugging
    echo "\n3. Creating mpesa_logs table...\n";
    
    $createLogsTable = $driver === 'pgsql'
        ? <<<'SQL'
CREATE TABLE IF NOT EXISTS mpesa_logs (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    request_type VARCHAR(50) NOT NULL,
    request_data TEXT,
    response_data TEXT,
    status_code INTEGER,
    error_message TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);
SQL
        : <<<'SQL'
CREATE TABLE IF NOT EXISTS mpesa_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_type VARCHAR(50) NOT NULL,
    request_data TEXT DEFAULT NULL,
    response_data TEXT DEFAULT NULL,
    status_code INTEGER DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
SQL;

    $pdo->exec($createLogsTable);
    echo "  ✓ mpesa_logs table created successfully\n";
    
    // 4. Add indexes for better performance
    echo "\n4. Adding performance indexes...\n";
    
    $indexQueries = [
        "CREATE INDEX IF NOT EXISTS idx_billing_mpesa_transaction ON billing(mpesa_transaction_id)",
        "CREATE INDEX IF NOT EXISTS idx_billing_mpesa_checkout ON billing(mpesa_checkout_request_id)",
        "CREATE INDEX IF NOT EXISTS idx_billing_transaction_status ON billing(transaction_status)",
        "CREATE INDEX IF NOT EXISTS idx_mpesa_trans_billing_id ON mpesa_transactions(billing_id)",
        "CREATE INDEX IF NOT EXISTS idx_mpesa_trans_checkout_request ON mpesa_transactions(checkout_request_id)",
        "CREATE INDEX IF NOT EXISTS idx_mpesa_trans_receipt ON mpesa_transactions(mpesa_receipt_number)",
        "CREATE INDEX IF NOT EXISTS idx_mpesa_trans_phone ON mpesa_transactions(phone_number)",
        "CREATE INDEX IF NOT EXISTS idx_mpesa_trans_status ON mpesa_transactions(status)",
        "CREATE INDEX IF NOT EXISTS idx_mpesa_logs_request_type ON mpesa_logs(request_type)",
        "CREATE INDEX IF NOT EXISTS idx_mpesa_logs_created_at ON mpesa_logs(created_at)",
    ];
    
    foreach ($indexQueries as $query) {
        try {
            $pdo->exec($query);
            echo "  ✓ Index created\n";
        } catch (PDOException $e) {
            echo "  ℹ Index might already exist: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✅ M-Pesa database migration completed successfully!\n\n";
    echo "Summary:\n";
    echo "- billing table updated with M-Pesa columns\n";
    echo "- mpesa_transactions table created\n";
    echo "- mpesa_logs table created\n";
    echo "- Performance indexes added\n\n";
    echo "You can now use M-Pesa payment functionality.\n";
    
} catch (PDOException $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
