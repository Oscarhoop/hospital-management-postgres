<?php
/**
 * M-Pesa Mock Mode - For testing when Daraja is down
 * Simulates M-Pesa responses without calling the real API
 */

function is_mock_mode() {
    return getenv('MPESA_MOCK_MODE') === 'true';
}

function mock_generate_access_token() {
    return 'MOCK_ACCESS_TOKEN_' . bin2hex(random_bytes(16));
}

function mock_initiate_stk_push($phone_number, $amount, $account_reference, $transaction_desc) {
    // Simulate a successful STK push
    $merchant_request_id = 'MOCK-MR-' . time() . '-' . rand(1000, 9999);
    $checkout_request_id = 'ws_CO_' . date('dmYHis') . rand(100000, 999999);
    
    return [
        'success' => true,
        'message' => 'Payment initiated successfully (MOCK MODE)',
        'merchant_request_id' => $merchant_request_id,
        'checkout_request_id' => $checkout_request_id,
        'response_code' => '0',
        'response_description' => 'Success. Request accepted for processing',
        'customer_message' => 'Success. Request accepted for processing (MOCK MODE - Daraja is down)',
        'is_mock' => true
    ];
}

function mock_query_stk_push_status($checkout_request_id) {
    // Simulate a successful payment
    return [
        'success' => true,
        'data' => [
            'ResponseCode' => '0',
            'ResponseDescription' => 'The service request has been accepted successfully',
            'MerchantRequestID' => 'MOCK-MR-' . time(),
            'CheckoutRequestID' => $checkout_request_id,
            'ResultCode' => '0',
            'ResultDesc' => 'The service request is processed successfully (MOCK)',
        ],
        'is_mock' => true
    ];
}

function mock_process_callback($billing_id) {
    // Simulate automatic payment success after 3 seconds
    global $pdo;
    
    sleep(3); // Simulate processing delay
    
    // Mark the bill as paid
    $receipt_number = 'MOCK' . strtoupper(uniqid());
    
    $stmt = $pdo->prepare('
        UPDATE billing SET
            status = ?,
            payment_method = ?,
            payment_date = CURRENT_TIMESTAMP,
            mpesa_receipt_number = ?,
            transaction_status = ?,
            mpesa_response_description = ?
        WHERE id = ?
    ');
    
    $stmt->execute([
        'paid',
        'M-Pesa (Mock)',
        $receipt_number,
        'completed',
        'Payment completed successfully in MOCK mode',
        $billing_id
    ]);
    
    return [
        'success' => true,
        'receipt_number' => $receipt_number,
        'is_mock' => true
    ];
}

return [
    'is_mock_mode' => is_mock_mode(),
    'mock_generate_access_token' => 'mock_generate_access_token',
    'mock_initiate_stk_push' => 'mock_initiate_stk_push',
    'mock_query_stk_push_status' => 'mock_query_stk_push_status',
    'mock_process_callback' => 'mock_process_callback'
];
?>
