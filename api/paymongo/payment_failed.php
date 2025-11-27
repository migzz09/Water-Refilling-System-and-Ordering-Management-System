<?php
session_start();

// Get failure reason if available
$reason = $_GET['reason'] ?? 'unknown';
$sessionId = $_GET['session_id'] ?? null;

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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { 
      background: linear-gradient(135deg, #4A90A4 0%, #5FA883 100%);
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
    }
    .error-animation {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .error-circle {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #f44336 0%, #e91e63 100%);
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      animation: shake 0.6s ease-out;
      box-shadow: 0 4px 20px rgba(244, 67, 54, 0.4);
    }
    .error-circle i {
      color: white;
      font-size: 2.5rem;
      animation: iconPop 0.6s ease-out 0.3s both;
    }
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
      20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    @keyframes iconPop {
      0% { transform: scale(0); opacity: 0; }
      50% { transform: scale(1.2); }
      100% { transform: scale(1); opacity: 1; }
    }
    .error-wrap { 
      max-width: 550px;
      width: 100%;
      background: #fff;
      padding: 2.5rem;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.15);
      animation: slideUp 0.6s ease-out;
      text-align: center;
    }
    @keyframes slideUp {
      from { transform: translateY(30px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    h1 { 
      margin: 1rem 0 0.5rem;
      background: linear-gradient(135deg, #f44336 0%, #e91e63 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      font-size: 1.8rem;
      font-weight: 700;
    }
    .subtitle {
      color: #666;
      font-size: 0.95rem;
      margin-bottom: 2rem;
      line-height: 1.6;
    }
    .info-box {
      background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
      padding: 1.25rem;
      border-left: 5px solid #ff9800;
      border-radius: 12px;
      margin: 1.5rem 0;
      text-align: left;
    }
    .info-box .title {
      font-weight: 600;
      color: #e65100;
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .info-box .details {
      color: #f57c00;
      font-size: 0.9rem;
      line-height: 1.6;
    }
    .info-box ul {
      margin: 0.75rem 0 0 1.25rem;
      list-style: none;
    }
    .info-box ul li {
      margin-bottom: 0.5rem;
      position: relative;
      padding-left: 1.25rem;
    }
    .info-box ul li:before {
      content: "•";
      position: absolute;
      left: 0;
      color: #ff9800;
      font-weight: bold;
    }
    .actions { 
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
      flex-wrap: wrap;
    }
    .actions a, .actions button { 
      flex: 1;
      min-width: 180px;
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
    @media (max-width: 640px) {
      body { padding: 1rem; }
      .error-wrap { padding: 2rem 1.5rem; }
      .actions { flex-direction: column; }
      .actions a, .actions button { min-width: 100%; }
      h1 { font-size: 1.5rem; }
      .error-circle { width: 60px; height: 60px; }
      .error-circle i { font-size: 2rem; }
    }
  </style>
  <script>
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
  <div class="error-wrap">
    <div class="error-animation">
      <div class="error-circle">
        <i class="fas fa-times"></i>
      </div>
    </div>
    <h1>Payment Not Completed</h1>
    <p class="subtitle">
      <?php if ($reason === 'expired'): ?>
        Your payment session has expired. This usually happens when the payment page is left open too long.
      <?php else: ?>
        Your payment was not completed or was cancelled.
      <?php endif; ?>
    </p>
    
    <div class="info-box">
      <div class="title">
        <i class="fas fa-info-circle"></i>
        What happened?
      </div>
      <div class="details">
        <ul>
          <li>Your order was <strong>not created</strong></li>
          <li>No charges were made to your account</li>
          <li>Your cart items are still saved</li>
          <li>You can try again or choose a different payment method</li>
        </ul>
      </div>
    </div>
    
    <div class="actions">
      <a class="btn btn-primary" href="/WRSOMS/pages/checkout.html">
        <i class="fas fa-redo"></i> Try Again
      </a>
      <a class="btn btn-outline" href="/WRSOMS/pages/product.html">
        <i class="fas fa-shopping-bag"></i> Back to Products
      </a>
    </div>
  </div>
</body>
</html>
