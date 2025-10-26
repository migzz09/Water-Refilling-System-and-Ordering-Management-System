<?php
session_start();
require_once 'connect.php';
require_once 'phpmailer-master/src/Exception.php';
require_once 'phpmailer-master/src/PHPMailer.php';
require_once 'phpmailer-master/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$login_errors = [];
$is_logged_in = isset($_SESSION['customer_id']) && isset($_SESSION['username']);

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    error_log("Login attempt: username=$username");
    if (empty($username) || empty($password)) {
        $login_errors[] = "Username and password are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT a.customer_id, a.username, a.password, a.is_verified
                FROM accounts a
                WHERE a.username = ? AND a.is_verified = 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Login query result: username=$username, found=" . ($user ? 'yes' : 'no') . ", is_verified=" . ($user['is_verified'] ?? 'N/A') . ", stored_password=" . ($user['password'] ?? 'N/A'));
            if ($user && $password === $user['password']) {
                $_SESSION['customer_id'] = $user['customer_id'];
                $_SESSION['username'] = $user['username'];
                session_write_close();
                error_log("Login successful: username=$username, customer_id={$user['customer_id']}");
                header('Location: index.php');
                exit();
            } else {
                $login_errors[] = "Invalid username, password, or account not verified.";
                error_log("Login failed for username: $username, is_verified: " . ($user['is_verified'] ?? 'not found') . ", password_match: " . ($user && $password === $user['password'] ? 'yes' : 'no'));
            }
        } catch (PDOException $e) {
            $login_errors[] = "Login failed: " . $e->getMessage();
            error_log("PDO Exception in login: " . $e->getMessage());
        }
    }
}

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
    .welcome {
      color: #008CBA;
      font-size: 1.0rem;
      font-weight: 400;
      margin-left: 1rem;
      position: relative;
      cursor: default;
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
    .welcome:hover .dropdown {
      display: block;
    }
    .dropdown a {
      display: flex;
      align-items: center;
      padding: 20px 30px;
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
    .hero {
      height: 90vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      background: linear-gradient(rgba(0, 140, 186, 0.6), rgba(0, 140, 186, 0.6)),
                  url('images/clear_blue_water.png') no-repeat center/cover;
      color: white;
      padding: 0 5%;
      animation: fadeIn 2s ease-in-out;
      position: relative;
    }
    .hero h1 {
      font-size: 3.5rem;
      margin-bottom: 1rem;
      animation: slideDown 1.5s ease forwards;
      position: relative;
      z-index: 10;
    }
    .hero p {
      font-size: 1.2rem;
      margin-bottom: 2rem;
      animation: slideUp 2s ease forwards;
      position: relative;
      z-index: 10;
    }
    .auth-section {
      background: rgba(255, 255, 255, 0.9);
      padding: 2rem;
      border-radius: 15px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      max-width: 500px;
      width: 100%;
      margin: 1rem auto;
      display: none;
      max-height: 70vh;
      overflow-y: auto;
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      z-index: 20;
    }
    .auth-section.active {
      display: block;
    }
    .auth-section h2 {
      color: #008CBA;
      margin-bottom: 1.5rem;
      font-size: 1.8rem;
    }
    .auth-section .close-btn {
      position: absolute;
      top: 10px;
      right: 10px;
      font-size: 1.5rem;
      color: #d32f2f;
      cursor: pointer;
      transition: color 0.3s;
    }
    .auth-section .close-btn:hover {
      color: #b71c1c;
    }
    .auth-section .form-group {
      margin-bottom: 1.2rem;
    }
    .auth-section label {
      display: block;
      margin-bottom: 0.5rem;
      color: #555;
      font-weight: 500;
    }
    .auth-section input[type="text"],
    .auth-section input[type="password"],
    .auth-section input[type="text"] {
      width: 100%;
      padding: 0.8rem;
      border: 2px solid #ddd;
      border-radius: 8px;
      font-size: 1rem;
      transition: border-color 0.3s, box-shadow 0.3s;
    }
    .auth-section input[type="text"]:focus,
    .auth-section input[type="password"]:focus,
    .auth-section input[type="text"]:focus {
      border-color: #008CBA;
      box-shadow: 0 0 5px rgba(0, 140, 186, 0.3);
      outline: none;
    }
    .password-wrapper {
      position: relative;
    }
    .password-wrapper input[type="password"],
    .password-wrapper input[type="text"] {
      padding-right: 2.5rem;
    }
    .password-toggle {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      width: 20px;
      height: 20px;
      fill: #555;
    }
    .auth-section input[type="submit"] {
      width: 100%;
      padding: 0.9rem;
      background: linear-gradient(90deg, #008CBA, #00aaff);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-weight: bold;
      font-size: 1.1rem;
      cursor: pointer;
      transition: transform 0.3s, background 0.3s;
    }
    .auth-section input[type="submit"]:hover {
      transform: translateY(-2px);
      background: linear-gradient(90deg, #0077b3, #0099e6);
    }
    .auth-section .error {
      color: #d32f2f;
      background: #ffebee;
      padding: 0.8rem;
      border-radius: 5px;
      margin-bottom: 1rem;
      text-align: center;
    }
    .auth-section .success {
      color: #2e7d32;
      background: #e8f5e9;
      padding: 0.8rem;
      border-radius: 5px;
      margin-bottom: 1rem;
      text-align: center;
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
<header style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 5%;">
  <div class="logo" style="display: flex; align-items: center;">
    <img src="images/ww_logo.png" alt="WaterWorld Logo" style="height: 2.5rem; margin-right: 0.75rem;">
    WaterWorld
  </div>
  <nav style="display: flex; align-items: center;">
    <ul style="display: flex; list-style: none; gap: 1.5rem; align-items: center; margin: 0; padding: 0;">
      <li><a href="index.php" style="text-decoration: none; color: #333; font-weight: 500;">Home</a></li>
      <?php if ($is_logged_in): ?>
        <li><a href="order_placement.php" style="text-decoration: none; color: #333; font-weight: 500;">Order</a></li>
        <li><a href="order_tracking.php" style="text-decoration: none; color: #333; font-weight: 500;">Track</a></li>
        <li class="profile" onclick="toggleDropdown(this)" style="position: relative;">
          <div class="profile-icon" style="display: flex; align-items: center; cursor: pointer;">
            <img src="images/profile_pic.png" alt="Profile Icon" style="height: 2.5rem; width: 2.5rem;">
          </div>
          <div class="dropdown" style="display: none;">
            <div class="welcome" style="padding: 12px 15px; cursor: default;">Welcome <?php echo htmlspecialchars($_SESSION['username']); ?>!</div> 
            <a href="user_settings.php" style="display: flex; align-items: center;">
              <img src="images/user_settings.png" alt="User Settings Icon">
              User Settings
            </a>
            <a href="usertransaction_history.php" style="display: flex; align-items: center;">
              <img src="images/usertransaction_history.png" alt="Transaction History Icon">
              Transaction History
            </a>
            <a href="logout.php" style="display: flex; align-items: center;">
              <img src="images/logout.png" alt="Logout Icon">
              Logout
            </a>
          </div>
        </li>
      <?php else: ?>
        <li><a href="#" onclick="showForm('login-form'); return false;" style="text-decoration: none; color: #333; font-weight: 500;">Login</a></li>
        <li><a href="register.php" style="text-decoration: none; color: #333; font-weight: 500;">Register</a></li>
        <li><a href="order_tracking.php" style="text-decoration: none; color: #333; font-weight: 500;">Track</a></li>
      <?php endif; ?>
    </ul>
  </nav>
</header>

<div class="debug">
  <p>Current directory: <?php echo htmlspecialchars($current_directory); ?></p>
  <p>Base URL: <?php echo htmlspecialchars($base_url); ?></p>
  <p>Database name: wrsoms</p>
</div>

<section class="hero">
  <div>
    <h1>Fresh. Clean. Pure.</h1>
    <p>Your trusted water refilling station for everyday health and hydration.</p>
    <?php if (!$is_logged_in): ?>
      <div id="login-form" class="auth-section">
        <span class="close-btn" onclick="hideForm('login-form')">&times;</span>
        <h2>Login</h2>
        <?php if (isset($_GET['success'])): ?>
          <div class="success">
            <?php echo htmlspecialchars($_GET['success']); ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($login_errors)): ?>
          <div class="error">
            <ul>
              <?php foreach ($login_errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        <form method="POST" action="">
          <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>
          </div>
          <div class="form-group">
            <label for="password">Password</label>
            <div class="password-wrapper">
              <input type="password" id="password" name="password" required>
              <svg class="password-toggle" onclick="togglePassword()" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </div>
          </div>
          <div class="form-group">
            <input type="submit" name="login_submit" value="Login">
          </div>
        </form>
      </div>
    <?php else: ?>
      <a href="order_placement.php" class="btn">Order Now</a>
    <?php endif; ?>
  </div>
</section>

<section class="about">
  <h2>About Us</h2>
  <p>Your one stop shop! Water Station, Laundry and car Wash</p>
  <p>At Water World, we are dedicated to providing safe, clean, and refreshing drinking water. 
    With modern facilities and reliable service, we make hydration simple and convenient for every household.</p>
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
  <div class="socials" style="display: flex; gap: 20px; justify-content: center;">
    <a href="https://web.facebook.com/yourwaterworld" aria-label="Facebook" target="_blank">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
        <path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/>
      </svg>
    </a>
    <a href="https://www.google.com/maps/place/Water+World/@14.5602872,121.0613366,17z/data=!3m1!4b1!4m6!3m5!1s0x3397c863ae316b73:0xada9b9c65212f757!8m2!3d14.560282!4d121.0639115!16s%2Fg%2F11hcw7cmvx?entry=ttu&g_ep=EgoyMDI1MTAxNC4wIKXMDSoASAFQAw%3D%3D" aria-label="Google Maps" target="_blank">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
        <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25-.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
      </svg>
    </a>
    <a href="https://mail.google.com/mail/?view=cm&fs=1&to=yourwaterworld@gmail.com&su=Contact%20WaterWorld" aria-label="Email WaterWorld" target="_blank">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
        <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
      </svg>
    </a>
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

  function showForm(formId) {
    console.log("Showing form: " + formId);
    document.getElementById(formId).classList.add('active');
  }

  function hideForm(formId) {
    console.log("Hiding form: " + formId);
    document.getElementById(formId).classList.remove('active');
  }

  function toggleDropdown(element) {
    const dropdown = element.querySelector('.dropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
  }

  function togglePassword() {
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.querySelector('.password-toggle');
    if (passwordInput.type === 'password') {
      passwordInput.type = 'text';
      passwordToggle.innerHTML = `
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
        <circle cx="12" cy="12" r="3"/>
        <line x1="3" y1="3" x2="21" y2="21" stroke="currentColor" stroke-width="2"/>
      `;
    } else {
      passwordInput.type = 'password';
      passwordToggle.innerHTML = `
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
        <circle cx="12" cy="12" r="3"/>
      `;
    }
  }

  window.addEventListener("scroll", revealOnScroll);
  revealOnScroll();

  // Show login form if success message is present
  <?php if (isset($_GET['success'])): ?>
    window.addEventListener('load', function() {
      console.log("Showing login form due to success message");
      showForm('login-form');
    });
  <?php endif; ?>
</script>
</body>
</html>