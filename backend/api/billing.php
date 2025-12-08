<?php
// Billing API endpoints
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

require_once __DIR__ . '/../cors.php';

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/permissions.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_pdo();

// Helper functions
function read_json() {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    return $data ?: [];
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function nullable($value) {
    if ($value === null) {
        return null;
    }
    if (is_string($value) && trim($value) === '') {
        return null;
    }
    return $value;
}

try {
    switch ($method) {
        case 'GET':
            // Check if user can access billing
            if (!is_logged_in()) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            }
            if (!can_access_billing()) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied. Billing access required.']);
                exit;
            }
            
            if (isset($_GET['id'])) {
                // Get single billing record with patient details
                $stmt = $pdo->prepare('
                    SELECT b.*, 
                           p.first_name as patient_first_name, p.last_name as patient_last_name,
                           a.start_time as appointment_start_time
                    FROM billing b
                    LEFT JOIN patients p ON b.patient_id = p.id
                    LEFT JOIN appointments a ON b.appointment_id = a.id
                    WHERE b.id = ?
                ');
                $stmt->execute([$_GET['id']]);
                $billing = $stmt->fetch();
                echo json_encode($billing ?: null);
                exit;
            }
            
            // Get all billing records with filtering
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if (isset($_GET['patient_id']) && !empty($_GET['patient_id'])) {
                $whereClause .= " AND b.patient_id = ?";
                $params[] = $_GET['patient_id'];
            }
            
            if (isset($_GET['status']) && !empty($_GET['status'])) {
                $whereClause .= " AND b.status = ?";
                $params[] = $_GET['status'];
            }
            
            if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
                $whereClause .= " AND DATE(b.created_at) >= ?";
                $params[] = $_GET['date_from'];
            }
            
            if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
                $whereClause .= " AND DATE(b.created_at) <= ?";
                $params[] = $_GET['date_to'];
            }
            
            $orderBy = "ORDER BY b.created_at DESC";
            if (isset($_GET['sort'])) {
                $sortField = $_GET['sort'];
                $sortOrder = $_GET['order'] ?? 'DESC';
                if (in_array($sortField, ['amount', 'status', 'due_date', 'created_at'])) {
                    $orderBy = "ORDER BY b.$sortField $sortOrder";
                }
            }
            
            $stmt = $pdo->prepare("
                SELECT b.*, 
                       p.first_name as patient_first_name, p.last_name as patient_last_name,
                       a.start_time as appointment_start_time
                FROM billing b
                LEFT JOIN patients p ON b.patient_id = p.id
                LEFT JOIN appointments a ON b.appointment_id = a.id
                $whereClause
                $orderBy
            ");
            $stmt->execute($params);
            $billing = $stmt->fetchAll();
            
            echo json_encode($billing);
            break;
            
        case 'POST':
            if (!is_logged_in()) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            }
            
            if (!can_create_billing()) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied. You do not have permission to create billing records.']);
                exit;
            }
            
            $data = read_json();
            
            // Validate required fields
            if (empty($data['patient_id']) || !isset($data['amount'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Patient ID and amount are required']);
                exit;
            }
            
            $stmt = $pdo->prepare('
                INSERT INTO billing (
                    patient_id, appointment_id, amount, status, due_date, notes
                ) VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                (int)$data['patient_id'],
                nullable($data['appointment_id'] ?? null),
                isset($data['amount']) ? (float)$data['amount'] : null,
                nullable($data['status'] ?? 'pending'),
                nullable($data['due_date'] ?? null),
                nullable($data['notes'] ?? null)
            ]);
            
            $id = $pdo->lastInsertId();
            
            log_audit_trail('create_billing', 'billing', $id, $data);
            
            // Return billing record with patient details
            $stmt = $pdo->prepare('
                SELECT b.*, 
                       p.first_name as patient_first_name, p.last_name as patient_last_name,
                       a.start_time as appointment_start_time
                FROM billing b
                LEFT JOIN patients p ON b.patient_id = p.id
                LEFT JOIN appointments a ON b.appointment_id = a.id
                WHERE b.id = ?
            ');
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch());
            break;
            
        case 'PUT':
            if (!is_logged_in()) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            }
            
            if (!can_edit_billing()) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied. You do not have permission to edit billing records.']);
                exit;
            }
            
            parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
            $id = $qs['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing id']);
                exit;
            }
            
            // Get current state for audit log
            $stmt = $pdo->prepare('SELECT * FROM billing WHERE id = ?');
            $stmt->execute([$id]);
            $before = $stmt->fetch();

            $data = read_json();
            
            // Build dynamic UPDATE query based on provided fields
            $updates = [];
            $params = [];
            
            if (isset($data['patient_id'])) {
                $updates[] = 'patient_id=?';
                $params[] = (int)$data['patient_id'];
            }
            if (isset($data['appointment_id'])) {
                $updates[] = 'appointment_id=?';
                $params[] = nullable($data['appointment_id']);
            }
            if (isset($data['amount'])) {
                $updates[] = 'amount=?';
                $params[] = (float)$data['amount'];
            }
            if (isset($data['status'])) {
                $updates[] = 'status=?';
                $params[] = nullable($data['status']);
            }
            if (isset($data['due_date'])) {
                $updates[] = 'due_date=?';
                $params[] = nullable($data['due_date']);
            }
            if (isset($data['payment_method'])) {
                $updates[] = 'payment_method=?';
                $params[] = nullable($data['payment_method']);
            }
            if (isset($data['payment_date'])) {
                $updates[] = 'payment_date=?';
                $params[] = nullable($data['payment_date']);
            }
            if (isset($data['notes'])) {
                $updates[] = 'notes=?';
                $params[] = nullable($data['notes']);
            }
            
            $updates[] = 'updated_at=CURRENT_TIMESTAMP';
            $params[] = $id;
            
            $sql = 'UPDATE billing SET ' . implode(', ', $updates) . ' WHERE id=?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Get new state for audit log
            $stmt = $pdo->prepare('SELECT * FROM billing WHERE id = ?');
            $stmt->execute([$id]);
            $after = $stmt->fetch();

            log_audit_trail('update_billing', 'billing', $id, ['before' => $before, 'after' => $after]);

            // Return updated billing record
            $stmt = $pdo->prepare('
                SELECT b.*, 
                       p.first_name as patient_first_name, p.last_name as patient_last_name,
                       a.start_time as appointment_start_time
                FROM billing b
                LEFT JOIN patients p ON b.patient_id = p.id
                LEFT JOIN appointments a ON b.appointment_id = a.id
                WHERE b.id = ?
            ');
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch());
            break;
            
        case 'DELETE':
            if (!is_logged_in()) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            }
            
            // Only admin and billing can delete
            require_role(['admin', 'billing']);
            
            parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
            $id = $qs['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing id']);
                exit;
            }
            
            log_audit_trail('delete_billing', 'billing', $id);

            $stmt = $pdo->prepare('DELETE FROM billing WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Billing record deleted successfully']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
