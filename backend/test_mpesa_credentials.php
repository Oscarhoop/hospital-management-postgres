<?php
/**
 * M-Pesa Credential Test Script
 * Tests your M-Pesa credentials against both sandbox and production
 */

// Your credentials
$consumer_key = 'D6IiUoJbaMiTcGv5EyAuDVjJqLFCGwYdMeLC4kqKChVTCRv1';
$consumer_secret = 'np2jFBGvNwt1Iu0AAoQDuHnghjWnWD5mD7ah5YIoqwkeLCmdlX7kOEe5Y9nRO4JR';

echo "============================================\n";
echo "M-Pesa Credential Tester\n";
echo "============================================\n\n";

function test_credentials($env_name, $base_url, $consumer_key, $consumer_secret) {
    echo "Testing $env_name environment...\n";
    echo "URL: $base_url\n";
    
    $credentials = base64_encode($consumer_key . ':' . $consumer_secret);
    $url = $base_url . '/oauth/v1/generate?grant_type=client_credentials';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    echo "Status Code: $status_code\n";
    
    if ($curl_error) {
        echo "CURL Error: $curl_error\n";
    }
    
    if ($response) {
        $result = json_decode($response, true);
        echo "Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
        
        if (isset($result['access_token'])) {
            echo "✓ SUCCESS! Access token generated.\n";
            echo "Token (first 20 chars): " . substr($result['access_token'], 0, 20) . "...\n";
            return true;
        } else {
            echo "✗ FAILED! No access token received.\n";
            if (isset($result['errorMessage'])) {
                echo "Error: " . $result['errorMessage'] . "\n";
            }
            return false;
        }
    } else {
        echo "✗ FAILED! No response received.\n";
        return false;
    }
    
    echo "\n";
}

// Test Sandbox
echo "============================================\n";
$sandbox_works = test_credentials(
    'SANDBOX',
    'https://sandbox.safaricom.co.ke',
    $consumer_key,
    $consumer_secret
);

echo "\n============================================\n";

// Test Production
$production_works = test_credentials(
    'PRODUCTION',
    'https://api.safaricom.co.ke',
    $consumer_key,
    $consumer_secret
);

echo "\n============================================\n";
echo "SUMMARY\n";
echo "============================================\n";
echo "Sandbox: " . ($sandbox_works ? "✓ WORKS" : "✗ FAILED") . "\n";
echo "Production: " . ($production_works ? "✓ WORKS" : "✗ FAILED") . "\n";
echo"\n";

if ($production_works && !$sandbox_works) {
    echo "⚠ WARNING: Your credentials are for PRODUCTION!\n";
    echo "Update your .env file:\n";
    echo "  MPESA_ENVIRONMENT=production\n";
    echo "  And use MPESA_PROD_* variables instead\n";
} elseif ($sandbox_works) {
    echo "✓ Your credentials are for SANDBOX (testing)\n";
} else {
    echo "✗ Your credentials don't work for either environment\n";
    echo "Please check:\n";
    echo "  1. Are the credentials correct?\n";
    echo "  2. Are they copied correctly (no extra spaces)?\n";
    echo "  3. Are they still valid? (not expired)\n";
}

echo "============================================\n";
?>
