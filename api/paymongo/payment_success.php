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

        if ($status && in_array(strtolower($status), ['paid', 'succeeded'])) $paid = true;
        if ($pi_status && in_array(strtolower($pi_status), ['paid', 'succeeded'])) $paid = true;

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
            $payload['skip_business_time_checks'] = true;
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
                $ordersResult = processOrder($payload, $customerId, $pdo);
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
                      <style>
                        body { background:#f6fafd; font-family:Inter, Arial, sans-serif; }
                        .receipt-wrap { max-width:860px; margin:3rem auto; background:#fff; padding:2rem 2.25rem; border-radius:14px; box-shadow:0 8px 32px rgba(0,0,0,0.08); }
                        h1 { margin:0 0 1rem; color:#0b74de; font-size:1.9rem; }
                        .meta-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:0.75rem; margin:1rem 0 1.25rem; }
                        .meta-box { background:#f9fbfc; padding:0.75rem 0.9rem; border-radius:8px; font-size:0.9rem; }
                        .items { border-top:2px solid #e5e5e5; border-bottom:2px solid #e5e5e5; margin:1.25rem 0; }
                        .item-row { display:flex; justify-content:space-between; padding:6px 4px; border-bottom:1px solid #eee; font-size:0.9rem; }
                        .item-row:last-child { border-bottom:none; }
                        .totals { text-align:right; margin-top:0.75rem; }
                        .totals h2 { margin:0.4rem 0 0; font-size:1.3rem; color:#0b4f9a; }
                        .pay-box { background:#eaf6ff; padding:0.9rem 1rem; border-left:4px solid #0b74de; border-radius:8px; margin:1rem 0 1.25rem; font-size:0.9rem; }
                        .actions { display:flex; gap:0.75rem; margin-top:1.25rem; flex-wrap:wrap; }
                        .actions a, .actions button { flex:1 1 180px; }
                        @media (max-width:640px){ .meta-grid { grid-template-columns:1fr 1fr; } .receipt-wrap { padding:1.5rem 1.25rem; } }
                      </style>
                      <script>
                        function printReceiptPage(){ window.print(); }
                      </script>
                    </head>
                    <body>
                      <div class="receipt-wrap">
                        <h1>GCash Payment Receipt</h1>
                        <div class="pay-box">
                          <strong>Status:</strong> Paid &nbsp; | &nbsp; <strong>Payment Method:</strong> GCash &nbsp; | &nbsp; <strong>PayMongo Ref:</strong> <?php echo $paymongoRef; ?>
                        </div>
                        <div class="meta-grid">
                          <div class="meta-box"><strong>Order Reference:</strong><br><?php echo $orderRef; ?></div>
                          <div class="meta-box"><strong>Batch #:</strong><br><?php echo $batchNo ?: 'N/A'; ?></div>
                          <div class="meta-box"><strong>Delivery Date:</strong><br><?php echo htmlspecialchars(date('M d, Y', strtotime($deliveryDate))); ?></div>
                          <div class="meta-box"><strong>Vehicle:</strong><br><?php echo htmlspecialchars($vehicleType); ?></div>
                          <div class="meta-box"><strong>Address:</strong><br><?php echo $addressLine ?: 'Not provided'; ?></div>
                          <div class="meta-box"><strong>Generated At:</strong><br><?php echo htmlspecialchars(date('M d, Y H:i')); ?></div>
                        </div>
                        <div class="items">
                          <?php if ($items): ?>
                            <?php foreach ($items as $it): ?>
                              <div class="item-row">
                                <div>
                                  <strong><?php echo htmlspecialchars($it['name'] ?? 'Container'); ?></strong>
                                  <div style="color:#666;"><?php echo htmlspecialchars(($it['water_type_name'] ?? '') . (isset($it['order_type_name']) && $it['order_type_name'] ? ', ' . $it['order_type_name'] : '')); ?></div>
                                </div>
                                <div style="text-align:right;">
                                  Qty: <?php echo (int)($it['quantity'] ?? 0); ?><br>
                                  ₱<?php echo number_format((($it['price'] ?? 0) * ($it['quantity'] ?? 0)), 2); ?>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <div style="padding:0.75rem;">No items found.</div>
                          <?php endif; ?>
                        </div>
                        <div class="totals">
                          <div style="font-size:0.95rem;color:#444;">Grand Total</div>
                          <h2>₱<?php echo $totalAmt; ?></h2>
                        </div>
                        <div class="actions">
                          <a class="btn btn-primary" href="/WRSOMS/pages/product.html">Back to Products</a>
                          <a class="btn btn-outline" href="/WRSOMS/pages/order-tracking.html">Track Order</a>
                          <button class="btn btn-outline" onclick="printReceiptPage()">Print</button>
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
