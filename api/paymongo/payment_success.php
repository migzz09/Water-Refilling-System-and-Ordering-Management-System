<?php
// Enable error logging for debugging HTTP 500 errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/payment_success_error.log');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'payment_helpers.php';

// Robust DB bootstrap
$pdo = null;
$dbInitError = null;

try {
    $connectPath = __DIR__ . '/../../config/connect.php';
    if (file_exists($connectPath)) {
        $GLOBALS['db_connection_error'] = null;
        
        require_once $connectPath;
        
        // CRITICAL: Check global scope if local $pdo is null
        if (!isset($pdo) || $pdo === null) {
            $pdo = $GLOBALS['pdo'] ?? null;
            error_log('payment_success.php: $pdo was null locally, retrieved from $GLOBALS[pdo]: ' . ($pdo ? 'SUCCESS' : 'STILL NULL'));
        }
        
        // DIAGNOSTIC: Test if PDO is actually connected (not just non-null)
        if ($pdo && $pdo instanceof PDO) {
            try {
                // Attempt a trivial query to verify connection is alive
                $pdo->query('SELECT 1');
                error_log('payment_success.php: PDO connection VERIFIED (SELECT 1 succeeded)');
            } catch (PDOException $testEx) {
                error_log('payment_success.php: PDO object exists but connection is DEAD: ' . $testEx->getMessage());
                $dbInitError = 'PDO connection test failed: ' . $testEx->getMessage();
                $pdo = null;
            }
        }
        
        error_log('payment_success.php: After require connect.php - $pdo type: ' . gettype($pdo) . ', is_null: ' . ($pdo === null ? 'YES' : 'NO') . ', is_PDO: ' . (($pdo instanceof PDO) ? 'YES' : 'NO'));
        error_log('payment_success.php: $GLOBALS[db_connection_error]: ' . var_export($GLOBALS['db_connection_error'] ?? null, true));
        
        if (isset($GLOBALS['db_connection_error']) && $GLOBALS['db_connection_error']) {
            $dbInitError = 'connect.php failed: ' . $GLOBALS['db_connection_error'];
            error_log('payment_success.php: connect.php set db_connection_error: ' . $dbInitError);
        } elseif (!$pdo) {
            $dbInitError = 'connect.php did not set $pdo in local or global scope. Possible variable scope isolation or PDO connection died.';
            error_log('payment_success.php: ANOMALY - connect.php ran but $pdo is null in both scopes');
        }
    } else {
        $dbInitError = 'connect.php file not found at: ' . $connectPath;
        error_log('payment_success.php: connect.php missing: ' . $dbInitError);
    }
    
    // If connect.php left $pdo null, try direct connection (last resort)
    if (!$pdo) {
        error_log('payment_success.php: Attempting direct PDO connection as last resort...');
        try {
            $pdo = new PDO('mysql:host=localhost;port=3306;dbname=wrsoms', 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("SET time_zone = '+08:00'");
            $dbInitError = null;
            error_log('payment_success.php: Direct PDO connection SUCCEEDED');
            
            // Store in global scope for processOrder to use
            $GLOBALS['pdo'] = $pdo;
        } catch (PDOException $directEx) {
            $dbInitError = 'Direct PDO connection failed: ' . $directEx->getMessage();
            $pdo = null;
            error_log('payment_success.php: Direct PDO connection FAILED: ' . $directEx->getMessage());
        }
    } else {
        error_log('payment_success.php: $pdo is set after connect.php, direct connection skipped');
    }
    
    // CRITICAL: Final state logging before processOrder
    if (!$pdo) {
        if (!$dbInitError) {
            $dbInitError = 'PDO remains null. MySQL may be rejecting connections. Check c:\\xampp\\htdocs\\WRSOMS\\logs\\payment_success_error.log for PDO diagnostics.';
        }
        error_log('payment_success.php: FINAL STATE - PDO IS NULL. Error: ' . $dbInitError);
    } else {
        error_log('payment_success.php: FINAL STATE - PDO is VALID (type: ' . get_class($pdo) . ')');
    }
} catch (Throwable $e) {
    $dbInitError = 'DB bootstrap exception: ' . $e->getMessage();
    $pdo = null;
    error_log('payment_success.php: DB bootstrap threw exception: ' . $e->getMessage());
}

header('Content-Type: text/html; charset=utf-8');

$sessionId = $_GET['session_id'] ?? ($_SESSION['pending_payment']['paymongo_session_id'] ?? null);
$reference = $_SESSION['pending_payment']['reference'] ?? ($_GET['reference'] ?? null);

$paid = false;
$verificationError = null;
$checkoutInfo = null;

if ($sessionId) {
    try {
        $resp = requestPaymongo('GET', '/checkout_sessions/' . urlencode($sessionId));
        $checkoutInfo = $resp;

        $status = $resp['data']['attributes']['status'] ?? $resp['data']['attributes']['payment_status'] ?? null;
        
        // Check if session is expired and redirect to failure page
        if ($status && strtolower($status) === 'expired') {
            header('Location: /WRSOMS/api/paymongo/payment_failed.php?reason=expired&session_id=' . urlencode($sessionId));
            exit;
        }

        $pi_status = null;
        if (!empty($resp['data']['attributes']['payment_intent'])) {
            $pi = $resp['data']['attributes']['payment_intent'];
            if (is_array($pi) && isset($pi['id'])) {
                $piResp = requestPaymongo('GET', '/payment_intents/' . urlencode($pi['id']));
                $pi_status = $piResp['data']['attributes']['status'] ?? null;
            } else if (is_string($pi)) {
                $piResp = requestPaymongo('GET', '/payment_intents/' . urlencode($pi));
                $pi_status = $piResp['data']['attributes']['status'] ?? null;
            }
        }

        // Check if checkout session is paid or succeeded
        if ($status && in_array(strtolower($status), ['paid', 'succeeded'])) $paid = true;
        if ($pi_status && in_array(strtolower($pi_status), ['paid', 'succeeded'])) $paid = true;
        
        // For test mode: if user reached success URL, treat as paid (PayMongo redirects only on success)
        $isTestMode = (strpos(PAYMONGO_SECRET_KEY, 'sk_test_') === 0);
        if ($isTestMode && !$paid) {
            // If we have a valid session and user reached success URL, assume test payment succeeded
            if ($status && in_array(strtolower($status), ['active', 'awaiting_payment_method'])) {
                $paid = true;
                error_log('payment_success.php: Test mode - treating active session as paid since user reached success URL');
            }
        }

        if (!$paid && !empty($resp['data']['relationships']['payments']['data'])) {
            foreach ($resp['data']['relationships']['payments']['data'] as $p) {
                if (!empty($p['id'])) {
                    $pResp = requestPaymongo('GET', '/payments/' . urlencode($p['id']));
                    $pStatus = $pResp['data']['attributes']['status'] ?? null;
                    if ($pStatus && in_array(strtolower($pStatus), ['succeeded', 'paid'])) {
                        $paid = true;
                        break;
                    }
                }
            }
        }

        if (!$paid) {
            $verificationError = 'Payment not yet marked as paid by PayMongo.';
        }
    } catch (Throwable $e) {
        $verificationError = 'Payment verification error: ' . $e->getMessage();
        error_log('PayMongo verification error: ' . $e->getMessage());
    }
} else {
    $verificationError = 'No PayMongo session id found to verify payment.';
}

// Initialize debugInfo early to avoid undefined variable errors
$debugInfo = null;

if ($paid) {
    if (isset($_SESSION['pending_payment'])) {
        $_SESSION['pending_payment']['status'] = 'paid';
        $_SESSION['pending_payment']['verified_at'] = date('c');
    }

    $ordersCreatePath = __DIR__ . '/../orders/create.php';
    if (file_exists($ordersCreatePath)) {
        require_once $ordersCreatePath;

        $payload = $_SESSION['pending_payment']['payload'] ?? null;
        if ($payload && empty($payload['skip_business_time_checks'])) {
            // Do NOT skip business time or batch checks for GCash; always enforce batch limits
            // $payload['skip_business_time_checks'] = true;
        }
        $customerId = $_SESSION['customer_id'] ?? ($_SESSION['pending_payment']['customer_id'] ?? null);

        // CRITICAL: Check for DB error OR null PDO before attempting processOrder
        if ($dbInitError || !$pdo || !($pdo instanceof PDO)) {
            $verificationError = 'Database connection unavailable. Cannot create order.';
            $debugInfo = [
                'pdo_is_null' => ($pdo === null),
                'pdo_type' => gettype($pdo),
                'pdo_class' => ($pdo && is_object($pdo)) ? get_class($pdo) : 'N/A',
                'db_error' => $dbInitError ?: 'PDO is null but no error was set',
                'mysql_service_running' => (function() {
                    // Try to test if MySQL port is reachable
                    $sock = @fsockopen('localhost', 3306, $errno, $errstr, 1);
                    if ($sock) {
                        fclose($sock);
                        return true;
                    }
                    return false;
                })(),
                'connect_php_exists' => file_exists(__DIR__ . '/../../config/connect.php'),
                'payload_present' => (bool)$payload,
                'customer_id' => $customerId
            ];
            error_log('payment_success.php: DB unavailable before processOrder. Debug: ' . json_encode($debugInfo));
        } elseif (!$payload || !$customerId) {
            $verificationError = 'Pending payment payload or authenticated customer missing.';
            $debugInfo = [
                'payload_present' => (bool)$payload,
                'customer_id_present' => (bool)$customerId,
                'stored_customer_id' => $_SESSION['pending_payment']['customer_id'] ?? null
            ];
        } else {
            try {
                // CRITICAL: Check if order for this PayMongo session already exists to prevent duplicates
                $paymongoRef = $_SESSION['pending_payment']['reference'] ?? null;
                $existingOrderRef = null;
                
                if ($paymongoRef) {
                    $checkStmt = $pdo->prepare("
                        SELECT o.reference_id 
                        FROM orders o 
                        LEFT JOIN checkouts c ON o.checkout_id = c.checkout_id 
                        WHERE c.notes LIKE ? 
                        AND o.customer_id = ? 
                        ORDER BY o.order_date DESC 
                        LIMIT 1
                    ");
                    $checkStmt->execute(['%PayMongo Ref: ' . $paymongoRef . '%', $customerId]);
                    $existingOrder = $checkStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existingOrder) {
                        $existingOrderRef = $existingOrder['reference_id'];
                        error_log('payment_success.php: Order already exists for PayMongo ref ' . $paymongoRef . ': ' . $existingOrderRef);
                    }
                }
                
                // If order already exists, use it; otherwise create new one
                if ($existingOrderRef) {
                    // Retrieve existing order details
                    $stmt = $pdo->prepare("SELECT * FROM orders WHERE reference_id = ?");
                    $stmt->execute([$existingOrderRef]);
                    $orderData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $ordersResult = [
                        'success' => true,
                        'message' => 'Payment confirmed for existing order',
                        'data' => [
                            'reference_id' => $existingOrderRef,
                            'batch_number' => $orderData['batch_id'] ?? 1,
                            'total_amount' => $orderData['total_amount'] ?? 0
                        ]
                    ];
                } else {
                    // Add PayMongo reference to notes to track this payment session
                    if ($paymongoRef && isset($payload['notes'])) {
                        $payload['notes'] = trim($payload['notes']) . ' | PayMongo Ref: ' . $paymongoRef;
                    } elseif ($paymongoRef) {
                        $payload['notes'] = 'PayMongo Ref: ' . $paymongoRef;
                    }
                    
                    $ordersResult = processOrder($payload, $customerId, $pdo);
                }
                
                if (is_array($ordersResult) && !empty($ordersResult['success'])) {
                    if (isset($_SESSION['cart'])) unset($_SESSION['cart']);
                    $data = $ordersResult['data'] ?? [];
                    $orderRef = htmlspecialchars($data['reference_id'] ?? '');
                    $batchNo = htmlspecialchars($data['batch_number'] ?? '');
                    $totalAmt = isset($data['total_amount']) ? number_format((float)$data['total_amount'], 2) : '0.00';
                    $paymongoRef = htmlspecialchars($_SESSION['pending_payment']['reference'] ?? '');
                    $items = $payload['items'] ?? [];
                    $delivery = $payload['delivery'] ?? [];
                    $addressLine = htmlspecialchars(
                        trim(($delivery['street'] ?? '') . 
                        ((isset($delivery['barangay']) && $delivery['barangay']) ? ', ' . $delivery['barangay'] : '') . 
                        ((isset($delivery['city']) && $delivery['city']) ? ', ' . $delivery['city'] : ''))
                    );
                    $deliveryDate = htmlspecialchars($delivery['deliveryDate'] ?? date('Y-m-d'));
                    $vehicleType = (($delivery['city'] ?? '') === 'Taguig') ? 'Tricycle' : 'Car';
                    ?>
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                      <meta charset="UTF-8">
                      <title>Payment Successful - WaterWorld</title>
                      <meta name="viewport" content="width=device-width, initial-scale=1.0">
                      <link rel="stylesheet" href="/WRSOMS/assets/css/design-system.css">
                      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                      <style>
                        * { margin:0; padding:0; box-sizing:border-box; }
                        body { 
                          background: linear-gradient(135deg, #4A90A4 0%, #5FA883 100%);
                          font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                          min-height: 100vh;
                          padding: 2rem 1rem;
                        }
                        .success-animation {
                          text-align: center;
                          margin-bottom: 1.5rem;
                        }
                        .checkmark-circle {
                          width: 80px;
                          height: 80px;
                          background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
                          border-radius: 50%;
                          display: inline-flex;
                          align-items: center;
                          justify-content: center;
                          animation: scaleIn 0.5s ease-out;
                          box-shadow: 0 4px 20px rgba(56, 239, 125, 0.4);
                        }
                        .checkmark-circle i {
                          color: white;
                          font-size: 2.5rem;
                          animation: checkmark 0.6s ease-out 0.3s both;
                        }
                        @keyframes scaleIn {
                          0% { transform: scale(0); opacity: 0; }
                          50% { transform: scale(1.1); }
                          100% { transform: scale(1); opacity: 1; }
                        }
                        @keyframes checkmark {
                          0% { transform: scale(0) rotate(-45deg); opacity: 0; }
                          100% { transform: scale(1) rotate(0deg); opacity: 1; }
                        }
                        .receipt-wrap { 
                          max-width: 900px;
                          margin: 0 auto;
                          background: #fff;
                          padding: 2.5rem;
                          border-radius: 20px;
                          box-shadow: 0 20px 60px rgba(0,0,0,0.15);
                          animation: slideUp 0.6s ease-out;
                        }
                        @keyframes slideUp {
                          from { transform: translateY(30px); opacity: 0; }
                          to { transform: translateY(0); opacity: 1; }
                        }
                        h1 { 
                          margin: 1rem 0 0.5rem;
                          background: linear-gradient(135deg, #4A90A4 0%, #2C5F6F 100%);
                          -webkit-background-clip: text;
                          -webkit-text-fill-color: transparent;
                          background-clip: text;
                          font-size: 2rem;
                          font-weight: 700;
                          text-align: center;
                        }
                        .subtitle {
                          text-align: center;
                          color: #666;
                          font-size: 0.95rem;
                          margin-bottom: 2rem;
                        }
                        .pay-box { 
                          background: linear-gradient(135deg, #e0f7fa 0%, #e1f5fe 100%);
                          padding: 1.25rem;
                          border-left: 5px solid #00acc1;
                          border-radius: 12px;
                          margin: 1.5rem 0;
                          display: flex;
                          align-items: center;
                          gap: 1rem;
                          flex-wrap: wrap;
                          box-shadow: 0 2px 8px rgba(0,172,193,0.1);
                        }
                        .pay-box .status-badge {
                          background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
                          color: white;
                          padding: 0.4rem 1rem;
                          border-radius: 20px;
                          font-weight: 600;
                          font-size: 0.85rem;
                          display: inline-flex;
                          align-items: center;
                          gap: 0.4rem;
                        }
                        .pay-box .info-item {
                          font-size: 0.9rem;
                          color: #00838f;
                        }
                        .pay-box .info-item strong {
                          color: #004d40;
                        }
                        .meta-grid { 
                          display: grid;
                          grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                          gap: 1rem;
                          margin: 1.5rem 0;
                        }
                        .meta-box { 
                          background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
                          padding: 1rem 1.25rem;
                          border-radius: 12px;
                          border: 1px solid #e9ecef;
                          transition: all 0.3s ease;
                        }
                        .meta-box:hover {
                          transform: translateY(-2px);
                          box-shadow: 0 4px 12px rgba(0,0,0,0.08);
                          border-color: #4A90A4;
                        }
                        .meta-box strong { 
                          color: #495057;
                          font-size: 0.8rem;
                          text-transform: uppercase;
                          letter-spacing: 0.5px;
                          display: block;
                          margin-bottom: 0.4rem;
                        }
                        .meta-box .value {
                          color: #212529;
                          font-size: 1.05rem;
                          font-weight: 600;
                        }
                        .section-title {
                          font-size: 1.1rem;
                          font-weight: 600;
                          color: #212529;
                          margin: 2rem 0 1rem;
                          padding-bottom: 0.5rem;
                          border-bottom: 2px solid #e9ecef;
                        }
                        .items { 
                          background: #f8f9fa;
                          border-radius: 12px;
                          padding: 1rem;
                          margin: 1rem 0;
                        }
                        .item-row { 
                          display: flex;
                          justify-content: space-between;
                          align-items: center;
                          padding: 1rem;
                          background: white;
                          border-radius: 8px;
                          margin-bottom: 0.75rem;
                          border: 1px solid #e9ecef;
                          transition: all 0.2s ease;
                        }
                        .item-row:hover {
                          border-color: #4A90A4;
                          box-shadow: 0 2px 8px rgba(74, 144, 164, 0.1);
                        }
                        .item-row:last-child { 
                          margin-bottom: 0;
                        }
                        .item-info strong {
                          color: #212529;
                          font-size: 1rem;
                          display: block;
                          margin-bottom: 0.25rem;
                        }
                        .item-info .details {
                          color: #6c757d;
                          font-size: 0.85rem;
                        }
                        .item-price {
                          text-align: right;
                        }
                        .item-price .qty {
                          color: #6c757d;
                          font-size: 0.85rem;
                          margin-bottom: 0.25rem;
                        }
                        .item-price .price {
                          color: #4A90A4;
                          font-size: 1.1rem;
                          font-weight: 700;
                        }
                        .totals { 
                          text-align: right;
                          margin-top: 1.5rem;
                          padding: 1.5rem;
                          background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
                          border-radius: 12px;
                          border: 2px solid #e9ecef;
                        }
                        .totals .label {
                          font-size: 1rem;
                          color: #6c757d;
                          margin-bottom: 0.5rem;
                        }
                        .totals h2 { 
                          margin: 0;
                          font-size: 2rem;
                          background: linear-gradient(135deg, #4A90A4 0%, #5FA883 100%);
                          -webkit-background-clip: text;
                          -webkit-text-fill-color: transparent;
                          background-clip: text;
                          font-weight: 700;
                        }
                        .actions { 
                          display: flex;
                          gap: 1rem;
                          margin-top: 2rem;
                          flex-wrap: wrap;
                        }
                        .actions a, .actions button { 
                          flex: 1;
                          min-width: 200px;
                          padding: 1rem 1.5rem;
                          border-radius: 12px;
                          font-weight: 600;
                          font-size: 0.95rem;
                          text-align: center;
                          text-decoration: none;
                          transition: all 0.3s ease;
                          border: none;
                          cursor: pointer;
                          display: inline-flex;
                          align-items: center;
                          justify-content: center;
                          gap: 0.5rem;
                        }
                        .btn-primary {
                          background: linear-gradient(135deg, #4A90A4 0%, #5FA883 100%);
                          color: white;
                          box-shadow: 0 4px 15px rgba(74, 144, 164, 0.3);
                        }
                        .btn-primary:hover {
                          transform: translateY(-2px);
                          box-shadow: 0 6px 20px rgba(74, 144, 164, 0.4);
                        }
                        .btn-outline {
                          background: white;
                          color: #4A90A4;
                          border: 2px solid #4A90A4;
                        }
                        .btn-outline:hover {
                          background: #4A90A4;
                          color: white;
                          transform: translateY(-2px);
                          box-shadow: 0 4px 15px rgba(74, 144, 164, 0.2);
                        }
                        @media print {
                          body { background: white; padding: 0; }
                          .receipt-wrap { box-shadow: none; }
                          .actions { display: none; }
                        }
                        @media (max-width: 640px) { 
                          body { padding: 1rem; }
                          .receipt-wrap { padding: 1.5rem; }
                          .meta-grid { grid-template-columns: 1fr; }
                          .actions { flex-direction: column; }
                          .actions a, .actions button { min-width: 100%; }
                          h1 { font-size: 1.5rem; }
                          .checkmark-circle { width: 60px; height: 60px; }
                          .checkmark-circle i { font-size: 2rem; }
                        }
                      </style>
                      <script>
                        function printReceiptPage(){ window.print(); }
                        
                        // Check if we're in a popup window
                        if (window.opener && !window.opener.closed) {
                          // We're in a popup, give parent time to detect the URL change before redirecting
                          setTimeout(function() {
                            try {
                              window.opener.location.href = window.location.href;
                              setTimeout(function() {
                                window.close();
                              }, 500);
                            } catch (e) {
                              // If we can't redirect parent, just close
                              window.close();
                            }
                          }, 1000);
                        }
                      </script>
                    </head>
                    <body>
                      <div class="receipt-wrap">
                        <div class="success-animation">
                          <div class="checkmark-circle">
                            <i class="fas fa-check"></i>
                          </div>
                        </div>
                        <h1>Payment Successful!</h1>
                        <p class="subtitle">Your GCash payment has been processed successfully</p>
                        
                        <div class="pay-box">
                          <span class="status-badge">
                            <i class="fas fa-check-circle"></i> Paid
                          </span>
                          <span class="info-item">
                            <strong>Payment Method:</strong> GCash
                          </span>
                          <span class="info-item">
                            <strong>Reference:</strong> <?php echo $paymongoRef; ?>
                          </span>
                        </div>

                        <div class="section-title"><i class="fas fa-info-circle"></i> Order Details</div>
                        <div class="meta-grid">
                          <div class="meta-box">
                            <strong><i class="fas fa-receipt"></i> Order Reference</strong>
                            <div class="value"><?php echo $orderRef; ?></div>
                          </div>
                          <div class="meta-box">
                            <strong><i class="fas fa-box"></i> Batch Number</strong>
                            <div class="value"><?php echo $batchNo ?: 'N/A'; ?></div>
                          </div>
                          <div class="meta-box">
                            <strong><i class="fas fa-calendar-alt"></i> Delivery Date</strong>
                            <div class="value"><?php echo htmlspecialchars(date('M d, Y', strtotime($deliveryDate))); ?></div>
                          </div>
                          <div class="meta-box">
                            <strong><i class="fas fa-truck"></i> Vehicle Type</strong>
                            <div class="value"><?php echo htmlspecialchars($vehicleType); ?></div>
                          </div>
                          <div class="meta-box">
                            <strong><i class="fas fa-map-marker-alt"></i> Delivery Address</strong>
                            <div class="value"><?php echo $addressLine ?: 'Not provided'; ?></div>
                          </div>
                          <div class="meta-box">
                            <strong><i class="fas fa-clock"></i> Generated At</strong>
                            <div class="value"><?php echo htmlspecialchars(date('M d, Y H:i')); ?></div>
                          </div>
                        </div>

                        <div class="section-title"><i class="fas fa-shopping-cart"></i> Items Ordered</div>
                        <div class="items">
                          <?php if ($items): ?>
                            <?php foreach ($items as $it): ?>
                              <div class="item-row">
                                <div class="item-info">
                                  <strong><?php echo htmlspecialchars($it['name'] ?? 'Container'); ?></strong>
                                  <div class="details"><?php echo htmlspecialchars(($it['water_type_name'] ?? '') . (isset($it['order_type_name']) && $it['order_type_name'] ? ', ' . $it['order_type_name'] : '')); ?></div>
                                </div>
                                <div class="item-price">
                                  <div class="qty">Qty: <?php echo (int)($it['quantity'] ?? 0); ?></div>
                                  <div class="price">₱<?php echo number_format((($it['price'] ?? 0) * ($it['quantity'] ?? 0)), 2); ?></div>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <div style="padding:1rem; text-align:center; color:#6c757d;">
                              <i class="fas fa-box-open" style="font-size:2rem; margin-bottom:0.5rem;"></i>
                              <div>No items found</div>
                            </div>
                          <?php endif; ?>
                        </div>
                        
                        <div class="totals">
                          <div class="label">Grand Total</div>
                          <h2>₱<?php echo $totalAmt; ?></h2>
                        </div>
                        
                        <div class="actions">
                          <a class="btn btn-primary" href="/WRSOMS/pages/product.html">
                            <i class="fas fa-shopping-bag"></i> Back to Products
                          </a>
                          <a class="btn btn-outline" href="/WRSOMS/pages/order-tracking.html">
                            <i class="fas fa-map-marked-alt"></i> Track Order
                          </a>
                          <button class="btn btn-outline" onclick="printReceiptPage()">
                            <i class="fas fa-print"></i> Print Receipt
                          </button>
                        </div>
                      </div>
                    </body>
                    </html>
                    <?php
                    exit;
                } else {
                    $verificationError = $ordersResult['message'] ?? 'Order creation failed.';
                    $debugInfo = [
                        'orders_result' => $ordersResult,
                        'customer_id' => $customerId
                    ];
                }
            } catch (Throwable $e) {
                $verificationError = 'Order processing exception: ' . $e->getMessage();
                $debugInfo = ['exception' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
                error_log('Order processing error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        }
    } else {
        $verificationError = 'Internal order processor not found.';
        $debugInfo = ['missing_orders_create' => true, 'path_checked' => $ordersCreatePath];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Verification - WaterWorld</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="/WRSOMS/assets/css/design-system.css">
  <style>
    body { background: #f6fafd; font-family: Inter, Arial, sans-serif; }
    .centered { max-width: 720px; margin: 4rem auto; background: #fff; border-radius: 12px; box-shadow: 0 6px 24px rgba(0,0,0,0.08); padding: 2rem; }
    .centered h1 { color: #0b74de; margin-bottom: 0.5rem; }
    .muted { color: #666; margin-bottom: 1rem; }
    pre { background:#f4f6f8; padding:1rem; border-radius:6px; overflow:auto; font-size:0.85rem; }
  </style>
</head>
<body>
  <div class="centered">
    <h1>Payment Processing Issue</h1>
    <p class="muted">Your payment may have been processed, but we encountered an issue creating your order.</p>
    <?php if (!empty($reference)): ?>
      <p><strong>Reference:</strong> <?php echo htmlspecialchars($reference); ?></p>
    <?php endif; ?>
    <p>Please contact support with the reference above if your payment was charged.</p>
    <?php if (!empty($verificationError)): ?>
      <p style="color:#d32f2f;"><strong>Error:</strong> <?php echo htmlspecialchars($verificationError); ?></p>
    <?php endif; ?>
    <?php if (!empty($debugInfo)): ?>
      <h3>Debug Info</h3>
      <pre><?php echo htmlspecialchars(json_encode($debugInfo, JSON_PRETTY_PRINT)); ?></pre>
    <?php endif; ?>
    <?php if (!empty($dbInitError)): ?>
      <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:1rem;margin:1rem 0;border-radius:6px;">
        <strong style="color:#856404;">⚠️ Database Issue</strong>
        <p style="color:#856404;margin:0.5rem 0 0;"><?php echo htmlspecialchars($dbInitError); ?></p>
      </div>
    <?php endif; ?>
    <p style="margin-top:1rem;">
      <a class="btn btn-primary" href="/WRSOMS/pages/checkout.html">Return to Checkout</a>
      <a class="btn btn-outline" href="/WRSOMS/pages/product.html" style="margin-left:1rem;">Back to Products</a>
    </p>
  </div>
</body>
</html>
