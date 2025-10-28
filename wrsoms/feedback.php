<?php
session_start();
require_once 'connect.php';

// Redirect to login if not logged in
$is_logged_in = isset($_SESSION['customer_id']) && isset($_SESSION['username']);
if (!$is_logged_in) {
    header('Location: index.php#login');
    exit;
}

// Handle feedback submission
$feedback_errors = [];
$feedback_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_submit'])) {
    $feedback_text = trim($_POST['feedback_text'] ?? '');
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
    $reference_id = trim($_POST['reference_id'] ?? '');

    if (empty($feedback_text)) {
        $feedback_errors[] = "Feedback cannot be empty.";
    } elseif ($rating < 1 || $rating > 5) {
        $feedback_errors[] = "Rating must be between 1 and 5.";
    } elseif (empty($reference_id)) {
        $feedback_errors[] = "Please select an order.";
    } else {
        try {
            // Verify the reference_id belongs to the customer
            $stmt = $pdo->prepare("SELECT reference_id FROM orders WHERE reference_id = ? AND customer_id = ?");
            $stmt->execute([$reference_id, $_SESSION['customer_id']]);
            if (!$stmt->fetch()) {
                $feedback_errors[] = "Invalid order selected.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO customer_feedback (reference_id, customer_id, rating, feedback_text, feedback_date)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$reference_id, $_SESSION['customer_id'], $rating, $feedback_text]);
                $feedback_success = "Thank you for your feedback!";
            }
        } catch (PDOException $e) {
            $feedback_errors[] = "Error submitting feedback: " . $e->getMessage();
            error_log("Error submitting feedback: " . $e->getMessage());
        }
    }
}

// Fetch customer's past orders for feedback form
$customer_orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT reference_id, order_date 
        FROM orders 
        WHERE customer_id = ? 
        ORDER BY order_date DESC
    ");
    $stmt->execute([$_SESSION['customer_id']]);
    $customer_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching customer orders: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Feedback - WaterWorld Water Station</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }
    body {
      background-color: #f9fbfc;
      color: #333;
      line-height: 1.6;
    }
    header {
      background: #ffffffcc;
      backdrop-filter: blur(10px);
      padding: 1rem 5%;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #e5e5e5;
      position: sticky;
      top: 0;
      z-index: 1000;
    }
    .logo {
      font-size: 1.5rem;
      font-weight: bold;
      color: #008CBA;
      text-transform: uppercase;
      letter-spacing: 2px;
      display: flex;
      align-items: center;
    }
    .logo img {
      height: 2.5rem;
      margin-right: 0.75rem;
    }
    nav ul {
      list-style: none;
      display: flex;
      gap: 1.5rem;
      align-items: center;
    }
    nav ul li a {
      text-decoration: none;
      color: #333;
      font-weight: 500;
      position: relative;
      padding-bottom: 4px;
      transition: color 0.3s;
    }
    nav ul li a::after {
      content: "";
      position: absolute;
      width: 0;
      height: 2px;
      bottom: 0;
      left: 0;
      background: #008CBA;
      transition: width 0.3s;
    }
    nav ul li a:hover {
      color: #008CBA;
    }
    nav ul li a:hover::after {
      width: 100%;
    }
    .dropdown {
      display: none;
      position: absolute;
      top: 100%;
      right: 0;
      background: white;
      border: 1px solid #e5e5e5;
      border-radius: 5px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      min-width: 220px;
      z-index: 1000;
      margin-top: 5px;
    }
    .profile:hover .dropdown {
      display: block;
    }
    .dropdown a, .dropdown .welcome {
      display: flex;
      align-items: center;
      padding: 12px 20px;
      text-decoration: none;
      color: #333;
      font-size: 0.9rem;
      font-weight: 400;
      transition: background 0.3s;
    }
    .dropdown a:hover {
      background: #f0f0f0;
    }
    .dropdown a img {
      height: 1.8rem;
      width: 1.8rem;
      margin-right: 8px;
    }
    .welcome {
      color: #008CBA;
      font-weight: 500;
    }
    .feedback-container {
      max-width: 600px;
      margin: 3rem auto;
      padding: 2rem;
      background: white;
      border-radius: 15px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .feedback-container h1 {
      font-size: 2rem;
      color: #008CBA;
      margin-bottom: 1rem;
      text-align: center;
    }
    .feedback-form {
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }
    .feedback-form select,
    .feedback-form textarea {
      padding: 0.8rem;
      border: 2px solid #e5e5e5;
      border-radius: 8px;
      font-size: 1rem;
      width: 100%;
    }
    .feedback-form textarea {
      resize: vertical;
      min-height: 120px;
    }
    .feedback-form select:focus,
    .feedback-form textarea:focus {
      border-color: #008CBA;
      outline: none;
    }
    .rating-stars {
      display: flex;
      justify-content: center;
      gap: 0.5rem;
      margin-bottom: 1rem;
    }
    .rating-star {
      font-size: 2rem;
      color: #ccc;
      cursor: pointer;
      transition: color 0.2s;
    }
    .rating-star.selected,
    .rating-star:hover,
    .rating-star:hover ~ .rating-star {
      color: #f5c518;
    }
    .feedback-message {
      text-align: center;
      margin-bottom: 1rem;
    }
    .feedback-message.success {
      color: #4CAF50;
    }
    .feedback-message.error {
      color: #d32f2f;
    }
    .submit-btn {
      background: linear-gradient(90deg, #008CBA, #00aaff);
      color: white;
      border: none;
      padding: 1rem;
      border-radius: 8px;
      font-weight: bold;
      font-size: 1.1rem;
      cursor: pointer;
      transition: transform 0.3s;
    }
    .submit-btn:hover {
      transform: translateY(-2px);
    }
    footer {
      background: #008CBA;
      color: white;
      text-align: center;
      padding: 2rem 5%;
      margin-top: 3rem;
    }
    @media (max-width: 768px) {
      .feedback-container {
        margin: 2rem 1rem;
        padding: 1.5rem;
      }
    }
  </style>
</head>
<body>
<header>
  <div class="logo">
    <img src="images/ww_logo.png" alt="WaterWorld Logo">
    WaterWorld
  </div>
  <nav>
    <ul>
      <li><a href="index.php">Home</a></li>
      <li><a href="product.php">Products</a></li>
      <li><a href="order_tracking.php">Track</a></li>
      <li><a href="feedback.php">Feedback</a></li>
      <li class="profile" style="position: relative;">
        <div style="display: flex; align-items: center; cursor: pointer;" onclick="toggleDropdown(this)">
          <img src="images/profile_pic.png" alt="Profile" style="height: 2.5rem; width: 2.5rem;">
        </div>
        <div class="dropdown">
          <div class="welcome">Welcome <?php echo htmlspecialchars($_SESSION['username']); ?>!</div>
          <a href="user_settings.php">
            <img src="images/user_settings.png" alt="Settings">
            User Settings
          </a>
          <a href="usertransaction_history.php">
            <img src="images/usertransaction_history.png" alt="History">
            Transaction History
          </a>
          <a href="logout.php">
            <img src="images/logout.png" alt="Logout">
            Logout
          </a>
        </div>
      </li>
    </ul>
  </nav>
</header>

<div class="feedback-container">
  <h1>Submit Feedback</h1>
  <form class="feedback-form" method="POST">
    <?php if (!empty($feedback_success)): ?>
      <div class="feedback-message success"><?php echo htmlspecialchars($feedback_success); ?></div>
    <?php endif; ?>
    <?php if (!empty($feedback_errors)): ?>
      <div class="feedback-message error">
        <?php foreach ($feedback_errors as $error): ?>
          <p><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (empty($customer_orders)): ?>
      <div class="feedback-message error">No orders found. Please place an order to submit feedback.</div>
    <?php else: ?>
      <label for="reference_id">Select Order</label>
      <select name="reference_id" id="reference_id" required>
        <option value="">Select an order</option>
        <?php foreach ($customer_orders as $order): ?>
          <option value="<?php echo htmlspecialchars($order['reference_id']); ?>">
            Order #<?php echo htmlspecialchars($order['reference_id']); ?> (<?php echo date('M d, Y', strtotime($order['order_date'])); ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <label for="feedback_text">Your Feedback</label>
      <textarea name="feedback_text" id="feedback_text" placeholder="Enter your feedback here" required></textarea>
      <label>Rating</label>
      <div class="rating-stars" id="ratingStars">
        <span class="rating-star" data-value="1">★</span>
        <span class="rating-star" data-value="2">★</span>
        <span class="rating-star" data-value="3">★</span>
        <span class="rating-star" data-value="4">★</span>
        <span class="rating-star" data-value="5">★</span>
      </div>
      <input type="hidden" name="rating" id="ratingInput" value="0">
      <input type="hidden" name="feedback_submit" value="1">
      <button type="submit" class="submit-btn">Submit Feedback</button>
    <?php endif; ?>
  </form>
</div>

<footer>
  <p>&copy; 2025 WaterWorld Water Station. All rights reserved.</p>
</footer>

<script>
function toggleDropdown(element) {
  const dropdown = element.querySelector('.dropdown');
  dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

document.addEventListener('DOMContentLoaded', function() {
  // Rating star functionality
  const stars = document.querySelectorAll('.rating-star');
  const ratingInput = document.getElementById('ratingInput');
  
  stars.forEach(star => {
    star.addEventListener('click', function() {
      const value = parseInt(this.getAttribute('data-value'));
      ratingInput.value = value;
      stars.forEach(s => {
        s.classList.remove('selected');
        if (parseInt(s.getAttribute('data-value')) <= value) {
          s.classList.add('selected');
        }
      });
    });
  });
});
</script>
</body>
</html>
