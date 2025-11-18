<?php
session_start();
// clear pending payment so user can retry
if (isset($_SESSION['pending_payment'])) {
    unset($_SESSION['pending_payment']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Failed - WaterWorld</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="/WRSOMS/assets/css/design-system.css">
  <link rel="stylesheet" href="/WRSOMS/assets/css/checkout.css">
  <style>
    body { background: #f6fafd; }
    .centered { max-width: 420px; margin: 6rem auto 0; background: #fff; border-radius: 12px; box-shadow: 0 6px 24px rgba(0,0,0,0.08); padding: 2.5rem 2rem; text-align: center; }
    .centered h1 { color: #d32f2f; margin-bottom: 1rem; }
    .centered p { color: #333; margin-bottom: 2rem; }
    .btn { font-size: 1.1rem; }
  </style>
</head>
<body>
  <div class="centered">
    <h1>Payment Failed / Cancelled</h1>
    <p>Your payment was not completed or was cancelled.<br>Please try again or choose another payment method.</p>
    <a href="/WRSOMS/pages/checkout.html" class="btn btn-outline">Return to Checkout</a>
  </div>
</body>
</html>
