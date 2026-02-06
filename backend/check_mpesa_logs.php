<?php
require_once 'backend/db.php';

$pdo = get_pdo();

echo "============================================\n";
echo "M-Pesa Error Logs (Most Recent)\n";
echo "============================================\n\n";

try {
    $stmt = $pdo->query("
        SELECT created_at, request_type, status_code, error_message, response_data
        FROM mpesa_logs
        ORDER BY created_at DESC
        LIMIT 5
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Time: " . $row['created_at'] . "\n";
        echo "Type: " . $row['request_type'] . "\n";
        echo "Status Code: " . ($row['status_code'] ?? 'N/A') . "\n";
        echo "Error: " . ($row['error_message'] ?? 'None') . "\n";
        
        if ($row['response_data']) {
            $response = json_decode($row['response_data'], true);
            echo "Response: " . print_r($response, true) . "\n";
        }
        
        echo "--------------------------------------------\n\n";
    }
} catch (Exception $e) {
    echo "Error fetching logs: " . $e->getMessage() . "\n";
} ?>
