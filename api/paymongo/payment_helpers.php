<?php
// filepath: c:\xampp\htdocs\WRSOMS\api\paymongo\payment_helpers.php
require_once 'paymongo_config.php';

// Generic PayMongo request helper (supports GET and POST)
function requestPaymongo($method, $endpoint, $payload = null) {
    $url = "https://api.paymongo.com/v1" . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = ['Content-Type: application/json', 'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':')];

    $method = strtoupper($method);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    } else if ($method === 'GET') {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    } else {
        // support other methods if needed
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['error' => $err];
    }

    $decoded = json_decode($response, true);
    return $decoded;
}

function sendToPaymongo($endpoint, $payload) {
    // backward compatible alias for POST
    return requestPaymongo('POST', $endpoint, $payload);
}

function generateUniqueRef() {
    return 'REF-' . time() . '-' . rand(1000, 9999);
}