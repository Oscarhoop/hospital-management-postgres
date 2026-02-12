<?php
// Centralized bootstrap to run optional setup tasks before starting the PHP server.

require_once __DIR__ . '/env.php';
load_project_env();

$projectRoot = dirname(__DIR__);
$backendDir = __DIR__;

function env_flag(string $key, bool $default = false): bool {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

function run_script(string $label, string $scriptPath): void {
    if (!file_exists($scriptPath)) {
        fwrite(STDERR, "[BOOT] {$label} skipped – script not found: {$scriptPath}\n");
        return;
    }

    $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($scriptPath);
    echo "[BOOT] Running {$label}...\n";
    passthru($cmd, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, "[BOOT] {$label} failed with exit code {$exitCode}.\n");
        exit($exitCode);
    }
}

$isLocalEnv = app_env() === 'local';
$runDbReset = env_flag('RUN_DB_RESET', false);
$runSchedulingSetup = env_flag('RUN_SCHEDULING_SETUP', $isLocalEnv);
$runSampleData = env_flag('RUN_SAMPLE_DATA', $isLocalEnv);
$runInitDb = env_flag('RUN_INIT_DB', $isLocalEnv);

if ($runInitDb) {
    run_script('Database initialization', $backendDir . '/init_db.php');
} else {
    echo "[BOOT] Database initialization skipped (set RUN_INIT_DB=true to enable).\n";
}

if ($runDbReset && is_production()) {
    fwrite(STDERR, "[BOOT] RUN_DB_RESET is blocked in production.\n");
    exit(1);
}

if ($runSchedulingSetup) {
    run_script('Scheduling tables setup', $backendDir . '/setup_scheduling_tables.php');
}

run_script('M-Pesa migrations', $backendDir . '/migrations/add_mpesa_tables.php');
run_script('rejection_reason column migration', $backendDir . '/migrations/add_rejection_reason_column.php');

if ($runSampleData) {
    run_script('Sample Kenyan data seeding', $backendDir . '/add_sample_kenyan_data.php');
}

$port = getenv('PORT') ?: '10000';
$router = $projectRoot . '/router.php';

echo "[BOOT] Starting PHP server on 0.0.0.0:{$port}\n";
$serverCmd = escapeshellcmd(PHP_BINARY) . ' -S 0.0.0.0:' . escapeshellarg($port) . ' ' . escapeshellarg($router);
passthru($serverCmd);
