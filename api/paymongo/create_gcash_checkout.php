<?php
// filepath: c:\xampp\htdocs\WRSOMS\api\paymongo\create_gcash_checkout.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'payment_helpers.php';

$input = json_decode(file_get_contents('php://input'), true);
$cart = isset($input['cart']) ? $input['cart'] : [];
$order = isset($input['order']) ? $input['order'] : [];

if (empty($cart)) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty']);
    exit;
}

// build line items for PayMongo (amount in centavos)
$line_items = [];
$total_amount = 0;
foreach ($cart as $item) {
    $isPurchaseNew = (isset($item['order_type_name']) && $item['order_type_name'] === 'Purchase New Container/s')
        || (isset($item['order_type_id']) && (int)$item['order_type_id'] === 2);
    $name = $item['name'] ?? '';
    $nameLower = strtolower($name);
    $isSmallSlim = (preg_match('/^small slim container\b/i', $nameLower) === 1);
    $isBigMin250 = (preg_match('/^(slim container|round container)\b/i', $nameLower) === 1);

    // base candidates
    $base = isset($item['price']) ? (float)$item['price'] : 0.0;
    $purchasePrice = isset($item['purchase_price']) ? (float)$item['purchase_price'] : 0.0;
    $orderTypePrice = isset($item['order_type_price']) ? (float)$item['order_type_price'] : 0.0;

    if ($isPurchaseNew) {
        if ($isSmallSlim) {
            $amount = $purchasePrice > 0 ? $purchasePrice : ($orderTypePrice > 0 ? $orderTypePrice : 100.0);
            if ($amount < 100) $amount = 100.0;
        } elseif ($isBigMin250) {
            $amount = $base > 0 ? $base : ($purchasePrice > 0 ? $purchasePrice : ($orderTypePrice > 0 ? $orderTypePrice : 250.0));
            if ($amount < 250) $amount = 250.0;
        } else {
            $amount = $base > 0 ? $base : ($purchasePrice > 0 ? $purchasePrice : $orderTypePrice);
        }
    } else {
        $amount = $base;
    }

    $qty = isset($item['quantity']) ? (int)$item['quantity'] : 1;
    $line_items[] = [
        'currency' => 'PHP',
        'amount' => (int)round($amount * 100),
        'name' => $name ?: 'Item',
        'quantity' => $qty
    ];
    $total_amount += $amount * $qty;
}

// generate a reference number to correlate the checkout with our session
$reference_number = generateUniqueRef();

$payload = [
    'data' => [
        'attributes' => [
            'line_items' => $line_items,
            'payment_method_types' => ['gcash'],
            'success_url' => SUCCESS_URL,
            'cancel_url' => FAILED_URL,
            'reference_number' => $reference_number
        ]
    ]
];

// call PayMongo
$resp = requestPaymongo('POST', '/checkout_sessions', $payload);

// attempt to extract checkout_url and session id
$checkout_url = $resp['data']['attributes']['checkout_url'] ?? null;
$paymongo_session_id = $resp['data']['id'] ?? null;

if ($checkout_url) {
    $customerId = $_SESSION['customer_id'] ?? null;
    if (!$customerId) {
        // Mark for later debugging if session lost
        $_SESSION['pending_payment_missing_customer'] = true;
    }
    $_SESSION['pending_payment'] = [
        'reference' => $reference_number,
        'paymongo_session_id' => $paymongo_session_id,
        'checkout_url' => $checkout_url,
        'total_amount' => $total_amount,
        'customer_id' => $customerId ?: null,
        // Build payload expected by orders/create.php to create the order later
        'payload' => [
            'order_type' => $order['order_type'] ?? 1,
            'delivery_option' => $order['delivery_option'] ?? 1,
            // payment method id for GCash; adapt if your DB uses a different id
            'payment_method' => 2,
            'skip_business_time_checks' => true,
            'items' => array_map(function($it){
                $isPurchaseNew = (isset($it['order_type_name']) && $it['order_type_name'] === 'Purchase New Container/s')
                    || (isset($it['order_type_id']) && (int)$it['order_type_id'] === 2);
                $name = $it['name'] ?? '';
                $nameLower = strtolower($name);
                $isSmallSlim = (preg_match('/^small slim container\b/i', $nameLower) === 1);
                $isBigMin250 = (preg_match('/^(slim container|round container)\b/i', $nameLower) === 1);
                $base = isset($it['price']) ? (float)$it['price'] : 0.0;
                $purchasePrice = isset($it['purchase_price']) ? (float)$it['purchase_price'] : 0.0;
                $orderTypePrice = isset($it['order_type_price']) ? (float)$it['order_type_price'] : 0.0;

                if ($isPurchaseNew) {
                    if ($isSmallSlim) {
                        $price = $purchasePrice > 0 ? $purchasePrice : ($orderTypePrice > 0 ? $orderTypePrice : 100.0);
                        if ($price < 100) $price = 100.0;
                    } elseif ($isBigMin250) {
                        $price = $base > 0 ? $base : ($purchasePrice > 0 ? $purchasePrice : ($orderTypePrice > 0 ? $orderTypePrice : 250.0));
                        if ($price < 250) $price = 250.0;
                    } else {
                        $price = $base > 0 ? $base : ($purchasePrice > 0 ? $purchasePrice : $orderTypePrice);
                    }
                } else {
                    $price = $base;
                }

                return [
                    'container_id' => isset($it['container_id']) ? (int)$it['container_id'] : (isset($it['id']) ? (int)$it['id'] : null),
                    'water_type_id' => isset($it['water_type_id']) ? (int)$it['water_type_id'] : null,
                    'order_type_id' => isset($it['order_type_id']) ? (int)$it['order_type_id'] : null,
                    'quantity' => isset($it['quantity']) ? (int)$it['quantity'] : 0,
                    'price' => $price,
                    'name' => $name ?: 'Container',
                    'water_type_name' => $it['water_type_name'] ?? '',
                    'order_type_name' => $it['order_type_name'] ?? ''
                ];
            }, $cart),
            'notes' => $order['notes'] ?? '',
            'delivery' => [
                'street' => $order['street'] ?? ($order['delivery'] ? ($order['delivery']['street'] ?? '') : ''),
                'city' => $order['city'] ?? ($order['delivery'] ? ($order['delivery']['city'] ?? '') : ''),
                'barangay' => $order['barangay'] ?? ($order['delivery'] ? ($order['delivery']['barangay'] ?? '') : ''),
                'deliveryDate' => $order['deliveryDate'] ?? ($order['delivery'] ? ($order['delivery']['deliveryDate'] ?? '') : '')
            ]
        ],
        'created_at' => time(),
        'status' => 'pending'
    ];

    echo json_encode([
        'success' => true,
        'checkout_url' => $checkout_url,
        'reference' => $reference_number,
        'paymongo_session_id' => $paymongo_session_id
    ]);
    exit;
} else {
    $msg = $resp['errors'][0]['detail'] ?? 'PayMongo API error';
    echo json_encode(['success' => false, 'message' => $msg, 'raw' => $resp]);
    exit;
}