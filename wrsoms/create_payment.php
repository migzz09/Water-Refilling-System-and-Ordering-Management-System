<?php
session_start();
if (!isset($_POST['confirm_payment'])) {
    die('Invalid payment request.');
}
require_once 'connect.php'; // PDO connection

$paymongo_secret_key = 'sk_test_Qi1teT4pWNqqfWmfAoBB44AE';
$success_url = 'http://localhost/wrsoms/payment_success.php';
$cancel_url  = 'http://localhost/wrsoms/payment_failed.php';

// Must be logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: index.php#login");
    exit;
}
$customer_id = $_SESSION['customer_id'];

// Get cart
$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    die('Your cart is empty.');
}

// Calculate total + build line items
$total_amount = 0;
$line_items = [];
foreach ($cart as $item) {
    $subtotal = $item['price'] * $item['quantity'];
    $total_amount += $subtotal;

    $line_items[] = [
        'currency' => 'PHP',
        'amount' => intval(round($item['price'] * 100)),
        'description' => $item['name'] . ' (' . $item['water_type_name'] . ', ' . $item['order_type_name'] . ')',
        'name' => $item['name'],
        'quantity' => $item['quantity']
    ];
}

// Generate reference ID (for PayMongo metadata)
$reference_id = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

// Create PayMongo checkout session
$payload = [
    "data" => [
        "attributes" => [
            "send_email_receipt" => false,
            "show_description" => true,
            "show_line_items" => true,
            "description" => "Order #$reference_id - Water Refilling",
            "line_items" => $line_items,
            "payment_method_types" => ["gcash", "card", "grab_pay"],
            "reference_number" => $reference_id,
            "success_url" => $success_url . "?ref=" . urlencode($reference_id),
            "cancel_url"  => $cancel_url . "?ref=" . urlencode($reference_id),
            "metadata" => [
                "customer_id" => $customer_id
            ]
        ]
    ]
];

$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_USERPWD, $paymongo_secret_key . ':');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode >= 200 && $httpcode < 300) {
    $data = json_decode($response, true);
    $checkout_url = $data['data']['attributes']['checkout_url'];

    // Store cart temporarily so success page can process it
    $_SESSION['pending_payment'] = [
        'reference_id' => $reference_id,
        'total_amount' => $total_amount,
        'cart' => $cart
    ];

    header("Location: $checkout_url");
    exit;
} else {
    echo "<h3>⚠️ PayMongo API Error ($httpcode)</h3>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}
?>
