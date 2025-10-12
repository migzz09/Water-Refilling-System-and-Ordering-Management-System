<?php
session_start();
require_once 'connect.php';

// Diagnostic checks
$error_message = [];
$files_to_check = [
    'order_placement.php' => 'Place an Order',
    'order_tracking.php' => 'Track an Order',
    'connect.php' => 'Database Connection',
    'login.php' => 'Customer Login',
    'logout.php' => 'Customer Logout',
];

foreach ($files_to_check as $file => $description) {
    if (!file_exists($file)) {
        $error_message[] = "Error: $file (required for '$description') not found in the current directory.";
    }
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['customer_id']) && isset($_SESSION['username']);

// Get current directory and server details for debugging
$current_directory = __DIR__;
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WaterWorld Water Station</title>
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
      overflow-x: hidden;
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
    }

    nav ul {
      list-style: none;
      display: flex;
      gap: 1.5rem;
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

    .hero {
      height: 90vh;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      background: linear-gradient(rgba(0, 140, 186, 0.6), rgba(0, 140, 186, 0.6)),
                  url('clear_blue_water.png') no-repeat center/cover;
      color: white;
      padding: 0 5%;
      animation: fadeIn 2s ease-in-out;
    }

    .hero h1 {
      font-size: 3.5rem;
      margin-bottom: 1rem;
      animation: slideDown 1.5s ease forwards;
    }

    .hero p {
      font-size: 1.2rem;
      margin-bottom: 2rem;
      animation: slideUp 2s ease forwards;
    }

    .btn {
      background: #ffffff;
      color: #008CBA;
      padding: 0.75rem 1.5rem;
      border-radius: 30px;
      font-weight: bold;
      text-decoration: none;
      transition: 0.3s;
      display: inline-block;
    }

    .btn:hover {
      background: #008CBA;
      color: white;
      transform: scale(1.05);
    }

    .error {
      color: red;
      text-align: center;
      margin: 10px 0;
      font-size: 14px;
    }

    .debug {
      color: #555;
      text-align: center;
      margin: 10px 0;
      font-size: 12px;
    }

    .welcome {
      color: #008CBA;
      font-size: 1rem;
      font-weight: 500;
      margin-left: 1rem;
    }

    section {
      opacity: 0;
      transform: translateY(30px);
      transition: all 1s ease;
    }

    section.show {
      opacity: 1;
      transform: translateY(0);
    }

    .about {
      padding: 4rem 5%;
      text-align: center;
    }

    .about h2 {
      font-size: 2.2rem;
      margin-bottom: 1rem;
      color: #008CBA;
    }

    .about p {
      max-width: 700px;
      margin: 0 auto;
      font-size: 1.1rem;
      color: #555;
    }

    .services {
      padding: 4rem 5%;
      background: #f0f8fb;
      text-align: center;
    }

    .services h2 {
      color: #008CBA;
      margin-bottom: 2rem;
    }

    .service-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 2rem;
    }

    .card {
      background: white;
      padding: 2rem;
      border-radius: 15px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      transition: transform 0.4s, box-shadow 0.4s;
      cursor: pointer;
    }

    .card:hover {
      transform: translateY(-8px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }

    .card h3 {
      margin-bottom: 1rem;
      color: #008CBA;
    }

    .floating-btn {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: #008CBA;
      color: white;
      padding: 1rem 1.5rem;
      border-radius: 50px;
      text-decoration: none;
      font-weight: bold;
      box-shadow: 0 5px 15px rgba(0,0,0,0.2);
      transition: 0.3s;
      animation: pulse 2s infinite;
    }

    .floating-btn:hover {
      background: #005f80;
      transform: scale(1.05);
    }

    footer {
      background: #008CBA;
      color: white;
      text-align: center;
      padding: 2rem 5%;
      margin-top: 2rem;
    }

    footer .socials {
      margin: 1rem 0;
    }

    footer .socials a {
      margin: 0 10px;
      color: white;
      text-decoration: none;
      font-size: 1.2rem;
      transition: color 0.3s;
    }

    footer .socials a:hover {
      color: #cceeff;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    @keyframes slideDown {
      from { transform: translateY(-50px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    @keyframes slideUp {
      from { transform: translateY(50px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }
  </style>
</head>
<body>

  <header>
    <div class="logo">WaterWorld</div>
    <nav>
      <ul>
        <li><a href="index.php">Home</a></li>
        <?php if ($is_logged_in): ?>
          <li><a href="order_placement.php">Order</a></li>
          <li><a href="order_tracking.php">Track</a></li>
          <li><a href="transaction_history.php">History</a></li>
          <li><a href="logout.php">Logout</a></li>
          <li class="welcome">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</li>
        <?php else: ?>
          <li><a href="login.php">Login</a></li>
          <li><a href="order_tracking.php">Track</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </header>

  <?php if (!empty($error_message)): ?>
    <div class="error">
      <ul>
        <?php foreach ($error_message as $error): ?>
          <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="debug">
    <p>Current directory: <?php echo htmlspecialchars($current_directory); ?></p>
    <p>Base URL: <?php echo htmlspecialchars($base_url); ?></p>
    <p>Database name: wrsoms</p>
  </div>

  <section class="hero">
    <div>
      <h1>Fresh. Clean. Pure.</h1>
      <p>Your trusted water refilling station for everyday health and hydration.</p>
      <a href="order_placement.php" class="btn">Order Now</a>
    </div>
  </section>

  <section class="about">
    <h2>About Us</h2>
    <p>
      At WaterWorld, we are dedicated to providing safe, clean, and refreshing drinking water. 
      With modern facilities and reliable service, we make hydration simple and convenient for every household.
    </p>
  </section>

  <section class="services">
    <h2>Our Services</h2>
    <div class="service-grid">
      <div class="card">
        <h3>Mineral Water Refills</h3>
        <p>Affordable and safe refills for gallons and containers, delivered right to your door.</p>
      </div>
      <div class="card">
        <h3>Fast Delivery</h3>
        <p>Track your orders in real-time and enjoy same-day delivery from our riders.</p>
      </div>
      <div class="card">
        <h3>Customer Care</h3>
        <p>24/7 support and feedback system to ensure top-quality service every time.</p>
      </div>
    </div>
  </section>

  <a href="order_placement.php" class="floating-btn">Quick Order</a>

  <footer>
    <p>&copy; 2025 WaterWorld Water Station. All rights reserved.</p>
    <div class="socials">
      <a href="#">Facebook</a>
      <a href="#">Twitter</a>
      <a href="#">Instagram</a>
    </div>
  </footer>

  <script>
    const sections = document.querySelectorAll("section");

    const revealOnScroll = () => {
      const triggerBottom = window.innerHeight * 0.85;

      sections.forEach(section => {
        const sectionTop = section.getBoundingClientRect().top;

        if (sectionTop < triggerBottom) {
          section.classList.add("show");
        }
      });
    };

    window.addEventListener("scroll", revealOnScroll);
    revealOnScroll();
  </script>

</body>
</html>
