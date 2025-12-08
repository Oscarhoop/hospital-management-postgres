<?php
// Doctors API endpoints
session_start();

require_once __DIR__ . '/../cors.php';


require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/audit.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_pdo();
$driver = get_db_driver();

function ensure_doctor_license_column($pdo) {
    $driver = get_db_driver();
    try {
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info(doctors)");
            $hasColumn = false;
            while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($col['name'] === 'license_number') {
                    $hasColumn = true;
                    break;
                }
            }
            if (!$hasColumn) {
                $pdo->exec('ALTER TABLE doctors ADD COLUMN license_number TEXT');
            }
        } else {
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = :table AND column_name = :column");
            $stmt->execute([':table' => 'doctors', ':column' => 'license_number']);
            if (!$stmt->fetchColumn()) {
                $pdo->exec('ALTER TABLE doctors ADD COLUMN license_number TEXT');
            }
        }
    } catch (Exception $e) {
        if (strpos(strtolower($e->getMessage()), 'duplicate column name') === false) {
            throw $e;
        }
    }
}

ensure_doctor_license_column($pdo);

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
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single doctor
                log_audit_trail('view_doctor', 'doctor', $_GET['id'], ['filters' => $_GET]);
                $stmt = $pdo->prepare('SELECT * FROM doctors WHERE id = ?');
                $stmt->execute([$_GET['id']]);
                $doctor = $stmt->fetch();
                echo json_encode($doctor ?: null);
                exit;
            }
            
            // Get all doctors with optional filtering
            log_audit_trail('list_doctors', 'doctor', null, ['filters' => $_GET]);
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if (isset($_GET['specialty']) && !empty($_GET['specialty'])) {
                $whereClause .= " AND specialty LIKE ?";
                $params[] = '%' . $_GET['specialty'] . '%';
            }
            
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $whereClause .= " AND (first_name LIKE ? OR last_name LIKE ? OR specialty LIKE ?)";
                $searchTerm = '%' . $_GET['search'] . '%';
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            }
            
            $stmt = $pdo->prepare("SELECT * FROM doctors $whereClause ORDER BY last_name, first_name");
            $stmt->execute($params);
            $doctors = $stmt->fetchAll();
            
            echo json_encode($doctors);
            break;
            
        case 'POST':
            if (!is_logged_in()) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            }
            
            $data = read_json();
            $stmt = $pdo->prepare('
                INSERT INTO doctors (first_name, last_name, specialty, license_number, phone, email, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $data['first_name'] ?? '',
                $data['last_name'] ?? '',
                $data['specialty'] ?? null,
                $data['license_number'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $data['notes'] ?? null
            ]);
            
            $id = $pdo->lastInsertId();
            log_audit_trail('create_doctor', 'doctor', $id, $data);
            $stmt = $pdo->prepare('SELECT * FROM doctors WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch());
            break;
            
        case 'PUT':
            if (!is_logged_in()) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
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
            $stmt = $pdo->prepare('SELECT * FROM doctors WHERE id = ?');
            $stmt->execute([$id]);
            $before = $stmt->fetch();

            $data = read_json();
            $stmt = $pdo->prepare('
                UPDATE doctors SET 
                    first_name=?, last_name=?, specialty=?, license_number=?, phone=?, email=?, notes=?
                WHERE id=?
            ');
            $stmt->execute([
                $data['first_name'] ?? '',
                $data['last_name'] ?? '',
                $data['specialty'] ?? null,
                $data['license_number'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $data['notes'] ?? null,
                $id
            ]);
            
            // Get new state for audit log
            $stmt = $pdo->prepare('SELECT * FROM doctors WHERE id = ?');
            $stmt->execute([$id]);
            $after = $stmt->fetch();

            log_audit_trail('update_doctor', 'doctor', $id, ['before' => $before, 'after' => $after]);
            
            echo json_encode($after);
            break;
            
        case 'DELETE':
            if (!is_logged_in()) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            }
            
            parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
            $id = $qs['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing id']);
                exit;
            }
            
            log_audit_trail('delete_doctor', 'doctor', $id);

            // Hard delete since no is_active column
            $stmt = $pdo->prepare('DELETE FROM doctors WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Doctor deleted successfully']);
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
