<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once 'connect.php';

// Optional: Check login
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['customer_id'];

// Fetch username
$stmt = $pdo->prepare("SELECT username FROM accounts WHERE customer_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$username = $user['username'] ?? 'User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Policies | Water World</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #007bff;
            --success: #28a745;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --info: #17a2b8;
            --warning: #ffc107;
        }
        body {
            background: linear-gradient(to bottom, #e3f2fd, #bbdefb);
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            padding: 20px 0;
        }
        .container { max-width: 1000px; }
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 25px;
        }
        .card-header {
            background: var(--primary);
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            padding: 15px 20px;
        }
        .card-body { padding: 30px; }
        .policy-item {
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 1px dashed #dee2e6;
        }
        .policy-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .policy-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .policy-title i {
            color: var(--primary);
            font-size: 1.4rem;
        }
        .policy-content {
            color: #444;
            line-height: 1.7;
            font-size: 0.95rem;
        }
        .policy-content ul {
            padding-left: 20px;
            margin: 10px 0;
        }
        .policy-content li {
            margin-bottom: 6px;
        }
        .policy-content strong {
            color: var(--dark);
        }
        .highlight-box {
            background: #e7f3ff;
            border-left: 4px solid var(--primary);
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin: 15px 0;
            font-style: italic;
        }
        .back-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover { text-decoration: underline; }
        .header-title {
            font-size: 2rem;
            color: var(--primary);
            font-weight: 700;
        }
        .last-updated {
            font-size: 0.85rem;
            color: #777;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="text-center mb-5">
            <h1 class="header-title">
                <i class="fas fa-file-contract"></i> Policies
            </h1>
            <p class="text-muted">Hi <strong><?= htmlspecialchars($username) ?></strong>! Please review our policies below.</p>
        </div>

        <!-- Policies List -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-gavel"></i> Terms & Policies
            </div>
            <div class="card-body">

                <!-- Policy 1 -->
                <div class="policy-item">
                    <div class="policy-title">
                        <i class="fas fa-truck"></i> Delivery Policy
                    </div>
                    <div class="policy-content">
                        <p>We deliver <strong>within Metro Manila only</strong>. Orders are processed and dispatched within <strong>24–48 hours</strong> from confirmation.</p>
                        <ul>
                            <li>Delivery schedule: <strong>Monday to Saturday, 8 AM – 6 PM</strong></li>
                            <li>No deliveries on Sundays and holidays</li>
                            <li>Delivery fee: <strong>₱50</strong> (free for orders above ₱500)</li>
                            <li>Exact delivery time will be sent via SMS</li>
                        </ul>
                        <div class="highlight-box">
                            <i class="fas fa-info-circle"></i> <strong>Note:</strong> Please ensure someone is present to receive the order. Failed deliveries may incur a redelivery fee.
                        </div>
                    </div>
                </div>

                <!-- Policy 2 -->
                <div class="policy-item">
                    <div class="policy-title">
                        <i class="fas fa-sync-alt"></i> Refill & Exchange Policy
                    </div>
                    <div class="policy-content">
                        <p>All refill orders require <strong>container exchange</strong>.</p>
                        <ul>
                            <li>Return your <strong>empty, clean</strong> 5-gallon container to the rider</li>
                            <li>Damaged or dirty containers will be rejected</li>
                            <li>New customers can purchase a container for <strong>₱250</strong></li>
                            <li>Container deposit is refundable upon return</li>
                        </ul>
                    </div>
                </div>

                <!-- Policy 3 -->
                <div class="policy-item">
                    <div class="policy-title">
                        <i class="fas fa-ban"></i> Cancellation & Modification Policy
                    </div>
                    <div class="policy-content">
                        <p>Orders can be modified or canceled <strong>only if status is "Pending"</strong>.</p>
                        <ul>
                            <li>Cancellation after processing: <strong>₱30 fee</strong></li>
                            <li>No changes allowed once "Out for Delivery"</li>
                            <li>Go to <strong>My Orders</strong> to cancel</li>
                        </ul>
                    </div>
                </div>

                <!-- Policy 4 -->
                <div class="policy-item">
                    <div class="policy-title">
                        <i class="fas fa-shield-alt"></i> Water Quality & Safety
                    </div>
                    <div class="policy-content">
                        <p>Our water undergoes <strong>8-stage purification</strong> and is certified safe by DOH.</p>
                        <ul>
                            <li>Reverse Osmosis + UV Treatment</li>
                            <li>Regular third-party testing</li>
                            <li>Sealed caps to prevent contamination</li>
                            <li><strong>100% refund</strong> if proven unsafe (with lab report)</li>
                        </ul>
                    </div>
                </div>

                <!-- Policy 5 -->
                <div class="policy-item">
                    <div class="policy-title">
                        <i class="fas fa-credit-card"></i> Payment Policy
                    </div>
                    <div class="policy-content">
                        <p>We currently accept <strong>Cash on Delivery (COD)</strong> only.</p>
                        <ul>
                            <li>Exact change appreciated</li>
                            <li>Online payments (GCash, Maya, Card) coming soon</li>
                            <li>No postpaid or credit terms</li>
                        </ul>
                    </div>
                </div>

                <!-- Policy 6 -->
                <div class="policy-item">
                    <div class="policy-title">
                        <i class="fas fa-undo"></i> Refund & Return Policy
                    </div>
                    <div class="policy-content">
                        <p>Refunds are processed within <strong>3–5 business days</strong>.</p>
                        <ul>
                            <li>Full refund for undelivered or defective products</li>
                            <li>No refund for opened/sealed containers</li>
                            <li>Return damaged items within <strong>24 hours</strong> of delivery</li>
                            <li>Contact support with photos</li>
                        </ul>
                    </div>
                </div>

                <!-- Policy 7 -->
                <div class="policy-item">
                    <div class="policy-title">
                        <i class="fas fa-lock"></i> Privacy Policy
                    </div>
                    <div class="policy-content">
                        <p>Your data is <strong>never shared</strong> with third parties.</p>
                        <ul>
                            <li>Used only for order processing and delivery</li>
                            <li>Encrypted storage and transmission</li>
                            <li>You can request data deletion anytime</li>
                            <li>Cookies used for session management only</li>
                        </ul>
                    </div>
                </div>

                <!-- Policy 8 -->
                <div class="policy-item">
                    <div class="policy-title">
                        <i class="fas fa-gift"></i> Promo Code Policy
                    </div>
                    <div class="policy-content">
                        <p>Promo codes have specific terms.</p>
                        <ul>
                            <li>One code per order</li>
                            <li>Expires as stated</li>
                            <li>Not applicable with other offers</li>
                            <li>Minimum order may apply</li>
                        </ul>
                    </div>
                </div>

                <!-- Policy 9 -->
                <div class="policy-item">
                    <div class="policy-title">
                        <i class="fas fa-exclamation-triangle"></i> Liability Disclaimer
                    </div>
                    <div class="policy-content">
                        <p>We are not liable for:</p>
                        <ul>
                            <li>Delays due to traffic, weather, or force majeure</li>
                            <li>Health issues from improper storage after delivery</li>
                            <li>Loss due to incorrect address provided</li>
                        </ul>
                    </div>
                </div>

                <!-- Policy 10 -->
                <div class="policy-item">
                    <div class="policy-title">
                        <i class="fas fa-balance-scale"></i> Governing Law
                    </div>
                    <div class="policy-content">
                        <p>These policies are governed by the laws of the <strong>Republic of the Philippines</strong>. Any disputes will be resolved in <strong>Metro Manila courts</strong>.</p>
                    </div>
                </div>

            </div>
        </div>

        <!-- Last Updated -->
        <p class="last-updated">
            <i class="fas fa-calendar-alt"></i> Last updated: October 30, 2025
        </p>

        <!-- Back to Settings -->
        <div class="text-center mt-4">
            <a href="user_settings.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Settings
            </a>
        </div>
    </div>
</body>
</html>
