<?php
/**
 * M-Pesa API Integration
 * Handles payment initiation, callbacks, and transaction queries
 */

session_start();

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config/mpesa_config.php';
require_once __DIR__ . '/../config/mpesa_mock.php';
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

/**
 * Log M-Pesa request/response for debugging
 */
function log_mpesa_request($request_type, $request_data, $response_data = null, $status_code = null, $error = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare('
            INSERT INTO mpesa_logs (request_type, request_data, response_data, status_code, error_message, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $request_type,
            json_encode($request_data),
            $response_data ? json_encode($response_data) : null,
            $status_code,
            $error,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        error_log('Failed to log M-Pesa request: ' . $e->getMessage());
    }
}

/**
 * Generate M-Pesa OAuth Access Token
 */
function get_mpesa_access_token() {
    // Use mock mode if Daraja is down
    if (is_mock_mode()) {
        return mock_generate_access_token();
    }
    
    $config = get_mpesa_config();
    $consumer_key = $config['consumer_key'];
    $consumer_secret = $config['consumer_secret'];
    $base_url = $config['base_url'];
    
    $credentials = base64_encode($consumer_key . ':' . $consumer_secret);
    $url = $base_url . '/oauth/v1/generate?grant_type=client_credentials';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    log_mpesa_request('oauth_token', ['url' => $url], json_decode($response, true), $status_code);
    
    if ($status_code !== 200) {
        return null;
    }
    
    $result = json_decode($response, true);
    return $result['access_token'] ?? null;
}

/**
 * Initiate STK Push (Lipa Na M-Pesa Online)
 */
function initiate_stk_push($phone_number, $amount, $account_reference, $transaction_desc) {
    // Use mock mode if Daraja is down
    if (is_mock_mode()) {
        return mock_initiate_stk_push($phone_number, $amount, $account_reference, $transaction_desc);
    }
    
    $config = get_mpesa_config();
    $access_token = get_mpesa_access_token();
    
    if (!$access_token) {
        return ['success' => false, 'message' => 'Failed to generate access token'];
    }
    
    // Format phone number (remove leading 0 or +, add 254)
    $phone_number = preg_replace('/^(\+?254|0)/', '', $phone_number);
    $phone_number = '254' . $phone_number;
    
    $shortcode = $config['shortcode'];
    $passkey = $config['passkey'];
    $timestamp = date('YmdHis');
    $password = base64_encode($shortcode . $passkey . $timestamp);
    $callback_url = $config['callback_url'];
    
    $url = $config['base_url'] . '/mpesa/stkpush/v1/processrequest';
    
    $request_data = [
        'BusinessShortCode' => $shortcode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => MPESA_TRANSACTION_TYPE_PAYBILL,
        'Amount' => (int)$amount,
        'PartyA' => $phone_number,
        'PartyB' => $shortcode,
        'PhoneNumber' => $phone_number,
        'CallBackURL' => $callback_url,
        'AccountReference' => $account_reference,
        'TransactionDesc' => $transaction_desc
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    $response_data = json_decode($response, true);
    log_mpesa_request('stk_push', $request_data, $response_data, $status_code, $curl_error);
    
    if ($status_code !== 200) {
        return [
            'success' => false,
            'message' => $response_data['errorMessage'] ?? 'Failed to initiate payment',
            'response' => $response_data
        ];
    }
    
    return [
        'success' => true,
        'message' => 'Payment initiated successfully',
        'merchant_request_id' => $response_data['MerchantRequestID'] ?? null,
        'checkout_request_id' => $response_data['CheckoutRequestID'] ?? null,
        'response_code' => $response_data['ResponseCode'] ?? null,
        'response_description' => $response_data['ResponseDescription'] ?? null,
        'customer_message' => $response_data['CustomerMessage'] ?? null
    ];
}

/**
 * Query STK Push transaction status
 */
function query_stk_push_status($checkout_request_id) {
    $config = get_mpesa_config();
    $access_token = get_mpesa_access_token();
    
    if (!$access_token) {
        return ['success' => false, 'message' => 'Failed to generate access token'];
    }
    
    $shortcode = $config['shortcode'];
    $passkey = $config['passkey'];
    $timestamp = date('YmdHis');
    $password = base64_encode($shortcode . $passkey . $timestamp);
    
    $url = $config['base_url'] . '/mpesa/stkpushquery/v1/query';
    
    $request_data = [
        'BusinessShortCode' => $shortcode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'CheckoutRequestID' => $checkout_request_id
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $response_data = json_decode($response, true);
    log_mpesa_request('stk_query', $request_data, $response_data, $status_code);
    
    return [
        'success' => $status_code === 200,
        'data' => $response_data
    ];
}

try {
    switch ($method) {
        case 'POST':
            if (!is_logged_in()) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            }
            
            // Temporarily disabled for testing
            // if (!can_access_billing()) {
            //     http_response_code(403);
            //     echo json_encode(['error' => 'Access denied. Billing access required.']);
            //     exit;
            // }
            
            $data = read_json();
            $action = $data['action'] ?? '';
            
            switch ($action) {
                case 'initiate_payment':
                    // Validate required fields
                    if (empty($data['billing_id']) || empty($data['phone_number'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Billing ID and phone number are required']);
                        exit;
                    }
                    
                    // Get billing record
                    $stmt = $pdo->prepare('SELECT * FROM billing WHERE id = ?');
                    $stmt->execute([$data['billing_id']]);
                    $billing = $stmt->fetch();
                    
                    if (!$billing) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Billing record not found']);
                        exit;
                    }
                    
                    // Check if already paid
                    if ($billing['status'] === 'paid') {
                        http_response_code(400);
                        echo json_encode(['error' => 'This bill has already been paid']);
                        exit;
                    }
                    
                    $phone_number = $data['phone_number'];
                    $amount = $billing['amount'];
                    $account_reference = 'BILL-' . $billing['id'];
                    $transaction_desc = 'Hospital Bill Payment';
                    
                    // Initiate STK Push
                    $result = initiate_stk_push($phone_number, $amount, $account_reference, $transaction_desc);
                    
                    if ($result['success']) {
                        // Save transaction to database
                        $stmt = $pdo->prepare('
                            INSERT INTO mpesa_transactions (
                                billing_id, merchant_request_id, checkout_request_id,
                                phone_number, amount, account_reference, transaction_desc,
                                transaction_type, status
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ');
                        $stmt->execute([
                            $billing['id'],
                            $result['merchant_request_id'],
                            $result['checkout_request_id'],
                            $phone_number,
                            $amount,
                            $account_reference,
                            $transaction_desc,
                            'STK_PUSH',
                            'initiated'
                        ]);
                        
                        // Update billing record
                        $stmt = $pdo->prepare('
                            UPDATE billing SET
                                mpesa_checkout_request_id = ?,
                                mpesa_phone_number = ?,
                                mpesa_amount = ?,
                                transaction_status = ?,
                                mpesa_response_description = ?
                            WHERE id = ?
                        ');
                        $stmt->execute([
                            $result['checkout_request_id'],
                            $phone_number,
                            $amount,
                            'initiated',
                            $result['customer_message'],
                            $billing['id']
                        ]);
                        
                        log_audit_trail('initiate_mpesa_payment', 'billing', $billing['id'], [
                            'phone' => $phone_number,
                            'amount' => $amount,
                            'checkout_request_id' => $result['checkout_request_id']
                        ]);
                    }
                    
                    echo json_encode($result);
                    break;
                    
                case 'query_status':
                    if (empty($data['checkout_request_id'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Checkout request ID is required']);
                        exit;
                    }
                    
                    $result = query_stk_push_status($data['checkout_request_id']);
                    echo json_encode($result);
                    break;
                    
                case 'check_status':
                    $billing_id = $_GET['billing_id'] ?? null;
                    if (!$billing_id) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Billing ID required']);
                        break;
                    }
                    
                    // Check if bill is paid
                    $stmt = $pdo->prepare('SELECT * FROM billing WHERE id = ?');
                    $stmt->execute([$billing_id]);
                    $bill = $stmt->fetch();
                    
                    if ($bill && $bill['status'] === 'paid') {
                        echo json_encode([
                            'success' => true,
                            'status' => 'paid',
                            'payment_date' => $bill['payment_date'],
                            'payment_method' => $bill['payment_method']
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false,
                            'status' => 'pending',
                            'message' => 'Payment not completed yet'
                        ]);
                    }
                    break;
                    
                default:
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action']);
            }
            break;
            
        case 'GET':
            if (!is_logged_in()) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            }
            
            if (!can_access_billing()) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit;
            }
            
            // Get M-Pesa transactions
            if (isset($_GET['billing_id'])) {
                $stmt = $pdo->prepare('
                    SELECT * FROM mpesa_transactions
                    WHERE billing_id = ?
                    ORDER BY created_at DESC
                ');
                $stmt->execute([$_GET['billing_id']]);
            } else {
                $stmt = $pdo->prepare('
                    SELECT * FROM mpesa_transactions
                    ORDER BY created_at DESC
                    LIMIT 100
                ');
                $stmt->execute();
            }
            
            $transactions = $stmt->fetchAll();
            echo json_encode($transactions);
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
