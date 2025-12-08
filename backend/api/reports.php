<?php
// Reports API endpoints
// Set secure session cookie parameters BEFORE starting session
$isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] == 443);
$isLocalhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $isSecure ? '1' : '0');
ini_set('session.cookie_samesite', $isLocalhost ? 'Lax' : 'Strict');

session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => $isLocalhost ? 'Lax' : 'Strict'
]);

session_start([
    'cookie_lifetime' => 86400,
    'use_strict_mode' => true,
    'use_only_cookies' => 1
]);

require_once __DIR__ . '/../error_handler.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:8000'));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/permissions.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_pdo();
$driver = get_db_driver();

function group_by_month_expression(string $column, string $driver): string {
    if ($driver === 'pgsql') {
        return "to_char(date_trunc('month', {$column}), 'YYYY-MM')";
    }
    return "strftime('%Y-%m', {$column})";
}

function month_equals_now_expression(string $column, string $driver): string {
    if ($driver === 'pgsql') {
        return "date_trunc('month', {$column}) = date_trunc('month', CURRENT_TIMESTAMP)";
    }
    return "strftime('%Y-%m', {$column}) = strftime('%Y-%m', 'now')";
}

function boolean_value(string $driver, string $value): string {
    return $driver === 'pgsql' ? ($value === '1' ? 'TRUE' : 'FALSE') : $value;
}

// Helper functions
function read_json() {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    return $data ?: [];
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

try {
    if ($method === 'GET') {
        if (!is_logged_in()) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
        
        // Check report access based on type
        $reportType = $_GET['type'] ?? '';
        if ($reportType === 'revenue' || $reportType === 'export') {
            // Financial reports - only admin and billing
            if (!can_view_reports() || (!is_admin() && !is_billing())) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied. Financial reports access required.']);
                exit;
            }
        } else if (!can_view_reports()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied. Reports access required.']);
            exit;
        }
        
        switch ($reportType) {
            case 'dashboard':
                // Dashboard statistics
                $stats = [];
                
                // Total patients
                try {
                    $stmt = $pdo->query('SELECT COUNT(*) FROM patients WHERE is_active = 1');
                    $stats['total_patients'] = $stmt->fetchColumn();
                } catch (Exception $e) {
                    $stmt = $pdo->query('SELECT COUNT(*) FROM patients');
                    $stats['total_patients'] = $stmt->fetchColumn();
                }
                
                // New patients this month
                try {
                    $expr = month_equals_now_expression('created_at', $driver);
                    $stmt = $pdo->query("SELECT COUNT(*) FROM patients WHERE is_active = 1 AND {$expr}");
                    $stats['new_patients_month'] = $stmt->fetchColumn();
                } catch (Exception $e) {
                    $expr = month_equals_now_expression('created_at', $driver);
                    $stmt = $pdo->query("SELECT COUNT(*) FROM patients WHERE {$expr}");
                    $stats['new_patients_month'] = $stmt->fetchColumn();
                }
                
                // Total appointments
                try {
                    $stmt = $pdo->query('SELECT COUNT(*) FROM appointments');
                    $stats['total_appointments'] = $stmt->fetchColumn();
                } catch (Exception $e) {
                    $stats['total_appointments'] = 0;
                }
                
                // Pending appointments
                try {
                    $stmt = $pdo->query('SELECT COUNT(*) FROM appointments WHERE status = "scheduled"');
                    $stats['pending_appointments'] = $stmt->fetchColumn();
                } catch (Exception $e) {
                    $stats['pending_appointments'] = 0;
                }
                
                // Total revenue
                try {
                    $stmt = $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM billing WHERE status = "paid"');
                    $stats['total_revenue'] = $stmt->fetchColumn();
                } catch (Exception $e) {
                    $stats['total_revenue'] = 0;
                }
                
                // Pending payments
                try {
                    $stmt = $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM billing WHERE status = "pending"');
                    $stats['pending_payments'] = $stmt->fetchColumn();
                } catch (Exception $e) {
                    $stats['pending_payments'] = 0;
                }
                
                // Active doctors
                try {
                    $stmt = $pdo->query('SELECT COUNT(*) FROM doctors WHERE is_active = 1');
                    $stats['active_doctors'] = $stmt->fetchColumn();
                } catch (Exception $e) {
                    try {
                        $stmt = $pdo->query('SELECT COUNT(*) FROM doctors');
                        $stats['active_doctors'] = $stmt->fetchColumn();
                    } catch (Exception $e2) {
                        $stats['active_doctors'] = 0;
                    }
                }
                
                echo json_encode($stats);
                break;
                
            case 'patients':
                // Patient demographics and trends
                $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
                $dateTo = $_GET['date_to'] ?? date('Y-m-d');
                
                $report = [];
                
                try {
                    // Gender distribution
                    $stmt = $pdo->prepare('
                        SELECT gender, COUNT(*) as count 
                        FROM patients 
                        WHERE created_at BETWEEN ? AND ?
                        GROUP BY gender
                    ');
                    $stmt->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
                    $report['gender_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $report['gender_distribution'] = [];
                }
                
                try {
                    // Age groups
                    $ageExpr = $driver === 'pgsql'
                        ? "CASE \n                                WHEN dob IS NULL THEN 'Unknown'\n                                WHEN EXTRACT(YEAR FROM AGE(dob)) < 18 THEN 'Under 18'\n                                WHEN EXTRACT(YEAR FROM AGE(dob)) BETWEEN 18 AND 30 THEN '18-30'\n                                WHEN EXTRACT(YEAR FROM AGE(dob)) BETWEEN 31 AND 50 THEN '31-50'\n                                WHEN EXTRACT(YEAR FROM AGE(dob)) BETWEEN 51 AND 70 THEN '51-70'\n                                ELSE 'Over 70'\n                           END"
                        : "CASE \n                                WHEN dob IS NULL THEN 'Unknown'\n                                WHEN (julianday('now') - julianday(dob))/365 < 18 THEN 'Under 18'\n                                WHEN (julianday('now') - julianday(dob))/365 BETWEEN 18 AND 30 THEN '18-30'\n                                WHEN (julianday('now') - julianday(dob))/365 BETWEEN 31 AND 50 THEN '31-50'\n                                WHEN (julianday('now') - julianday(dob))/365 BETWEEN 51 AND 70 THEN '51-70'\n                                ELSE 'Over 70'\n                           END";
                    $stmt = $pdo->prepare("SELECT {$ageExpr} as age_group, COUNT(*) as count FROM patients WHERE created_at BETWEEN ? AND ? GROUP BY age_group");
                    $stmt->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
                    $report['age_groups'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $report['age_groups'] = [];
                }
                
                // Monthly registrations trend
                try {
                    $monthExpr = group_by_month_expression('created_at', $driver);
                    $stmt = $pdo->prepare("SELECT {$monthExpr} as month, COUNT(*) as count FROM patients WHERE created_at BETWEEN ? AND ? GROUP BY {$monthExpr} ORDER BY month");
                    $stmt->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
                    $report['monthly_registrations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $report['monthly_registrations'] = [];
                }
                
                echo json_encode($report);
                break;
                
            case 'appointments':
                // Appointment analytics
                $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
                $dateTo = $_GET['date_to'] ?? date('Y-m-d');
                
                $report = [];
                
                try {
                    // Appointment status distribution
                    $stmt = $pdo->prepare('
                        SELECT status, COUNT(*) as count 
                        FROM appointments 
                        WHERE start_time BETWEEN ? AND ?
                        GROUP BY status
                    ');
                    $stmt->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
                    $report['status_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $report['status_distribution'] = [];
                }
                
                try {
                    // Doctor workload
                    $stmt = $pdo->prepare('
                        SELECT d.first_name, d.last_name, d.specialty, COUNT(a.id) as appointment_count
                        FROM doctors d
                        LEFT JOIN appointments a ON d.id = a.doctor_id AND a.start_time BETWEEN ? AND ?
                        GROUP BY d.id, d.first_name, d.last_name, d.specialty
                        ORDER BY appointment_count DESC
                    ');
                    $stmt->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
                    $report['doctor_workload'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $report['doctor_workload'] = [];
                }
                
                echo json_encode($report);
                break;
                
            case 'revenue':
                // Revenue analytics
                $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
                $dateTo = $_GET['date_to'] ?? date('Y-m-d');
                
                $report = [];
                
                // Normalize date range to cover full days
                $from = $dateFrom . ' 00:00:00';
                $to = $dateTo . ' 23:59:59';
                
                // Revenue by status
                $stmt = $pdo->prepare('
                    SELECT status, COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as count
                    FROM billing 
                    WHERE created_at BETWEEN ? AND ?
                    GROUP BY status
                ');
                $stmt->execute([$from, $to]);
                $report['revenue_by_status'] = $stmt->fetchAll();
                
                // Monthly revenue trends
                $monthExpr = group_by_month_expression('created_at', $driver);
                $stmt = $pdo->prepare("SELECT {$monthExpr} as month, COALESCE(SUM(amount), 0) as total_amount FROM billing WHERE created_at BETWEEN ? AND ? GROUP BY {$monthExpr} ORDER BY month");
                $stmt->execute([$from, $to]);
                $report['monthly_revenue'] = $stmt->fetchAll();
                
                // Payment methods
                $stmt = $pdo->prepare('
                    SELECT payment_method, COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as count
                    FROM billing 
                    WHERE payment_method IS NOT NULL AND created_at BETWEEN ? AND ?
                    GROUP BY payment_method
                ');
                $stmt->execute([$from, $to]);
                $report['payment_methods'] = $stmt->fetchAll();
                
                echo json_encode($report);
                break;
                
            case 'export':
                // Export data to CSV
                $table = $_GET['table'] ?? '';
                $format = $_GET['format'] ?? 'csv';
                
                if (!in_array($table, ['patients', 'appointments', 'billing', 'doctors'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid table name']);
                    exit;
                }
                
                $stmt = $pdo->query("SELECT * FROM $table ORDER BY created_at DESC");
                $data = $stmt->fetchAll();
                
                if ($format === 'csv') {
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $table . '_export.csv"');
                    
                    $output = fopen('php://output', 'w');
                    
                    if (!empty($data)) {
                        // Write headers
                        fputcsv($output, array_keys($data[0]));
                        
                        // Write data
                        foreach ($data as $row) {
                            fputcsv($output, $row);
                        }
                    }
                    
                    fclose($output);
                    exit;
                }
                
                echo json_encode($data);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid report type']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
