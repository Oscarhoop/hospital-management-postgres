<?php
/**
 * Mock M-Pesa Callback Simulator
 * Completes pending mock payments automatically
 */

require_once 'backend/db.php';

$pdo = get_pdo();

echo "============================================\n";
echo "Mock M-Pesa Payment Completion\n";
echo "============================================\n\n";

// Find all initiated mock payments
$stmt = $pdo->query("
    SELECT id, amount, mpesa_checkout_request_id
    FROM billing
    WHERE transaction_status = 'initiated'
    AND mpesa_checkout_request_id LIKE 'ws_CO_%'
    ORDER BY created_at DESC
");

$pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pending)) {
    echo "No pending mock payments found.\n";
    exit;
}

echo "Found " . count($pending) . " pending payment(s)\n\n";

foreach ($pending as $bill) {
    echo "Processing Bill ID: " . $bill['id'] . "\n";
    echo "Amount: KES " . number_format($bill['amount'], 2) . "\n";
    echo "Checkout ID: " . $bill['mpesa_checkout_request_id'] . "\n";
    
    // Generate mock receipt
    $receipt_number = 'MOCK' . strtoupper(uniqid());
    
    // Update billing record
    $update = $pdo->prepare('
        UPDATE billing SET
            status = ?,
            payment_method = ?,
            payment_date = CURRENT_TIMESTAMP,
            mpesa_receipt_number = ?,
            transaction_status = ?,
            mpesa_response_description = ?
        WHERE id = ?
    ');
    
    $update->execute([
        'paid',
        'M-Pesa (Mock)',
        $receipt_number,
        'completed',
        'Payment completed successfully in MOCK mode',
        $bill['id']
    ]);
    
    // Update transaction record
    $tx_update = $pdo->prepare('
        UPDATE mpesa_transactions SET
            status = ?,
            mpesa_receipt_number = ?,
            result_code = ?,
            result_desc = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE checkout_request_id = ?
    ');
    
    $tx_update->execute([
        'completed',
        $receipt_number,
        '0',
        'The service request is processed successfully (MOCK)',
        $bill['mpesa_checkout_request_id']
    ]);
    
    echo "✓ Payment completed!\n";
    echo "Receipt Number: " . $receipt_number . "\n";
    echo "--------------------------------------------\n\n";
}

echo "============================================\n";
echo "All pending payments completed!\n";
echo "Refresh your browser to see the updates.\n";
echo "============================================\n";
?>
