<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once 'connect.php';
require_once 'phpmailer-master/src/Exception.php';
require_once 'phpmailer-master/src/PHPMailer.php';
require_once 'phpmailer-master/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!file_exists('../config/config.php')) die("Config missing.");
$config = require '../config/config.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php"); exit;
}
$user_id = $_SESSION['customer_id'];

// Fetch user
$stmt = $pdo->prepare("
    SELECT a.username, a.phone_number, a.profile_photo, 
           c.email, c.street, c.barangay, c.city, c.date_created
    FROM accounts a
    LEFT JOIN customers c ON a.customer_id = c.customer_id
    WHERE a.customer_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header("Location: logout.php"); exit; }

$errors = []; $success = null;

// === POST HANDLING ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update Profile
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone_number']);
        if (empty($username) || empty($email) || empty($phone)) {
            $errors[] = "All fields required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email.";
        } else {
            $pdo->prepare("UPDATE accounts SET username = ?, phone_number = ? WHERE customer_id = ?")
                ->execute([$username, $phone, $user_id]);
            $pdo->prepare("UPDATE customers SET email = ? WHERE customer_id = ?")
                ->execute([$email, $user_id]);
            $success = "Profile updated!";
            $user['username'] = $username;
            $user['email'] = $email;
            $user['phone_number'] = $phone;
        }
    }

    // Change Password
    elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        $stmt = $pdo->prepare("SELECT password FROM accounts WHERE customer_id = ?");
        $stmt->execute([$user_id]);
        $acc = $stmt->fetch();
        if ($current !== $acc['password']) {
            $errors[] = "Current password incorrect.";
        } elseif ($new !== $confirm) {
            $errors[] = "Passwords don't match.";
        } elseif (strlen($new) < 6) {
            $errors[] = "Password too short.";
        } else {
            $pdo->prepare("UPDATE accounts SET password = ? WHERE customer_id = ?")
                ->execute([$new, $user_id]);
            $success = "Password changed!";
        }
    }

    // Update Photo
    elseif (isset($_POST['update_photo']) && $_FILES['profile_photo']['error'] === 0) {
        $file = $_FILES['profile_photo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($ext, $allowed)) {
            $errors[] = "Invalid image type.";
        } elseif ($file['size'] > 2*1024*1024) {
            $errors[] = "Image too large (max 2MB).";
        } else {
            $dir = 'uploads/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $path = $dir . 'profile_' . $user_id . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $path)) {
                if ($user['profile_photo'] && file_exists($user['profile_photo'])) unlink($user['profile_photo']);
                $pdo->prepare("UPDATE accounts SET profile_photo = ? WHERE customer_id = ?")
                    ->execute([$path, $user_id]);
                $user['profile_photo'] = $path;
                $success = "Photo updated!";
            }
        }
    }

    // Update Address
    elseif (isset($_POST['update_address'])) {
        $street = trim($_POST['street']);
        $barangay = trim($_POST['barangay']);
        $city = trim($_POST['city']);
        if (empty($street) || empty($barangay) || empty($city)) {
            $errors[] = "Address fields required.";
        } else {
            $pdo->prepare("UPDATE customers SET street = ?, barangay = ?, city = ? WHERE customer_id = ?")
                ->execute([$street, $barangay, $city, $user_id]);
            $success = "Address saved!";
            $user['street'] = $street;
            $user['barangay'] = $barangay;
            $user['city'] = $city;
        }
    }

    // Request Deletion
    elseif (isset($_POST['request_deletion'])) {
        try {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $stmt = $pdo->prepare("UPDATE accounts SET deletion_token = ?, deletion_expires = ? WHERE customer_id = ?");
            $stmt->execute([$token, $expires, $user_id]);

            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
            $delete_link = rtrim($base_url, '/') . '/deletion_verify.php?token=' . $token;

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $config['gmail_username'];
            $mail->Password = $config['gmail_app_password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
            if (!$mail->smtpConnect()) { $mail->Port = 465; $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; }

            $mail->setFrom($config['gmail_username'], 'WaterWorld Admin');
            $mail->addAddress($user['email']);
            $mail->isHTML(true);
            $mail->Subject = 'Confirm Account Deletion';
            $mail->Body = "
                <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px;border:1px solid #ddd;border-radius:10px;'>
                    <h2 style='color:#dc3545;'>Delete Your Account</h2>
                    <p>Hi <strong>{$user['username']}</strong>,</p>
                    <p>You requested to delete your account. Click below to confirm:</p>
                    <p style='text-align:center;'>
                        <a href='{$delete_link}' style='background:#dc3545;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;font-weight:bold;'>
                           Delete My Account
                        </a>
                    </p>
                    <p><small>This link expires in <strong>1 hour</strong>. Ignore if not requested.</small></p>
                    <hr><p style='font-size:12px;color:#666;'>WaterWorld &copy; 2025</p>
                </div>
            ";
            $mail->send();
            $success = "Verification email sent! Check your inbox.";
        } catch (Exception $e) {
            $errors[] = "Email failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Settings | WaterWorld</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
    body { background-color: #f9fbfc; color: #333; line-height: 1.6; overflow-x: hidden; }
    header {
      background: #ffffffcc; backdrop-filter: blur(10px); padding: 1rem 5%; display: flex; justify-content: space-between; align-items: center;
      border-bottom: 1px solid #e5e5e5; position: sticky; top: 0; z-index: 1000;
    }
    .logo { font-size: 1.5rem; font-weight: bold; color: #008CBA; text-transform: uppercase; letter-spacing: 2px; display: flex; align-items: center; }
    .logo img { height: 2.5rem; margin-right: 0.75rem; object-fit: contain; }
    nav ul { list-style: none; display: flex; gap: 1.5rem; align-items: center; }
    nav ul li a {
      text-decoration: none; color: #333; font-weight: 500; position: relative; padding-bottom: 4px; transition: color 0.3s;
    }
    nav ul li a::after { content: ""; position: absolute; width: 0; height: 2px; bottom: 0; left: 0; background: #008CBA; transition: width 0.3s; }
    nav ul li a:hover { color: #008CBA; }
    nav ul li a:hover::after { width: 100%; }
    .profile { position: relative; cursor: pointer; }
    .profile-icon img { height: 2.5rem; width: 2.5rem; object-fit: contain; border-radius: 50%; }
    .profile:hover .dropdown { display: block; }
    .dropdown {
      display: none; position: absolute; top: 100%; right: 0; background: white; border: 1px solid #e5e5e5; border-radius: 5px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1); min-width: 220px; z-index: 1000; margin-top: 5px;
    }
    .dropdown a, .dropdown .welcome { display: flex; align-items: center; padding: 12px 20px; text-decoration: none; color: #333; font-size: 0.9rem; transition: background 0.3s; }
    .dropdown a:hover { background: #f0f0f0; }
    .dropdown a img { height: 1.8rem; width: 1.8rem; margin-right: 8px; object-fit: contain; }
    .welcome { color: #008CBA; font-weight: 500; }

    .hero {
      min-height: 40vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;
      background: linear-gradient(rgba(0, 140, 186, 0.6), rgba(0, 140, 186, 0.6)), url('images/clear_blue_water.png') center/cover;
      color: white; padding: 4rem 5%; animation: fadeIn 2s ease-in-out;
    }
    .hero h1 { font-size: 2.8rem; margin-bottom: 1rem; animation: slideDown 1.5s ease forwards; }
    .hero p { font-size: 1.1rem; animation: slideUp 2s ease forwards; }

    .container { max-width: 1000px; margin: 3rem auto; padding: 0 5%; }
    .section-title { text-align: center; margin-bottom: 2.5rem; color: #008CBA; font-size: 2.2rem; }
    .card {
      background: white; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 2rem; margin-bottom: 2rem;
      transition: transform 0.4s, box-shadow 0.4s; opacity: 0; transform: translateY(30px);
    }
    .card.show { opacity: 1; transform: translateY(0); }
    .card:hover { transform: translateY(-8px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
    .card-header {
      font-size: 1.3rem; font-weight: bold; color: #008CBA; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;
    }
    .form-group { margin-bottom: 1.2rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; color: #555; font-weight: 500; }
    .form-control {
      width: 100%; padding: 0.8rem; border: 2px solid #ddd; border-radius: 8px; font-size: 1rem;
      transition: border-color 0.3s, box-shadow 0.3s;
    }
    .form-control:focus { border-color: #008CBA; box-shadow: 0 0 5px rgba(0,140,186,0.3); outline: none; }
    .btn {
      background: #008CBA; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 30px; font-weight: bold;
      cursor: pointer; transition: 0.3s; display: inline-block;
    }
    .btn:hover { background: #0077b3; transform: scale(1.05); }
    .btn-danger { background: #dc3545; }
    .btn-danger:hover { background: #c82333; }
    .btn-success { background: #28a745; }
    .btn-success:hover { background: #218838; }

    .profile-img {
      width: 120px; height: 120px; object-fit: cover; border-radius: 50%; border: 4px solid white;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2); margin: 0 auto; display: block;
    }
    .photo-upload { text-align: center; margin-top: 1rem; }
    .photo-upload input { margin-top: 0.5rem; }

    .toast {
      position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; border-radius: 12px;
      padding: 1rem 1.5rem; color: white; box-shadow: 0 8px 20px rgba(0,0,0,0.15); animation: slideInRight 0.5s;
    }
    .toast.success { background: #28a745; }
    .toast.error { background: #dc3545; }

    .back-link {
      display: block; text-align: center; margin-top: 2rem; color: #008CBA; font-weight: 600; text-decoration: none;
    }
    .back-link:hover { text-decoration: underline; }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    @keyframes slideInRight { from { transform: translateX(100%); } to { transform: translateX(0); } }

    @media (max-width: 768px) {
      .hero h1 { font-size: 2.2rem; }
      .card { padding: 1.5rem; }
    }
  </style>
</head>
<body>

<!-- HEADER (SAME AS INDEX) -->
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
      <li class="profile" onclick="toggleDropdown(this)">
        <div class="profile-icon">
          <img src="<?= $user['profile_photo'] && file_exists($user['profile_photo']) ? $user['profile_photo'] : 'images/profile_pic.png' ?>" alt="Profile">
        </div>
        <div class="dropdown">
          <div class="welcome">Welcome <?= htmlspecialchars($user['username']) ?>!</div>
          <a href="user_settings.php"><img src="images/user_settings.png" alt=""> User Settings</a>
          <a href="usertransaction_history.php"><img src="images/usertransaction_history.png" alt=""> Transaction History</a>
          <a href="logout.php"><img src="images/logout.png" alt=""> Logout</a>
        </div>
      </li>
    </ul>
  </nav>
</header>

<!-- HERO -->
<section class="hero">
  <h1>User Settings</h1>
  <p>Manage your profile, address, and account preferences</p>
</section>

<div class="container">

  <!-- Toast Alerts -->
  <?php if ($success): ?>
    <div class="toast success" id="toast"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="toast error" id="toast">
      <i class="fas fa-exclamation-triangle"></i>
      <ul style="margin: 0; padding-left: 1.2rem;">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <!-- PROFILE CARD -->
  <div class="card">
    <div class="card-header"><i class="fas fa-user"></i> My Profile</div>
    <div class="row">
      <div class="col-md-3 text-center">
        <form method="POST" enctype="multipart/form-data" id="photoForm">
          <img src="<?= $user['profile_photo'] && file_exists($user['profile_photo']) ? $user['profile_photo'] : 'images/profile_pic.png' ?>" alt="Profile" class="profile-img">
          <div class="photo-upload">
            <input type="file" name="profile_photo" class="form-control" accept="image/*" onchange="this.form.submit()">
            <input type="hidden" name="update_photo" value="1">
          </div>
        </form>
      </div>
      <div class="col-md-9">
        <form method="POST">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-group">
                <label><i class="fas fa-user-tag"></i> Username</label>
                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label><i class="fas fa-phone"></i> Phone</label>
                <input type="tel" name="phone_number" class="form-control" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>" placeholder="+639123456789" required>
              </div>
            </div>
            <div class="col-12">
              <button type="submit" name="update_profile" class="btn"><i class="fas fa-save"></i> Save Profile</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ADDRESS CARD -->
  <div class="card">
    <div class="card-header"><i class="fas fa-map-marker-alt"></i> Delivery Address</div>
    <form method="POST">
      <div class="row g-3">
        <div class="col-md-6">
          <div class="form-group">
            <label>Street</label>
            <input type="text" name="street" class="form-control" value="<?= htmlspecialchars($user['street'] ?? '') ?>" required>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>Barangay</label>
            <input type="text" name="barangay" class="form-control" value="<?= htmlspecialchars($user['barangay'] ?? '') ?>" required>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>City</label>
            <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($user['city'] ?? '') ?>" required>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label>Province</label>
            <input type="text" class="form-control" value="Metro Manila" readonly>
          </div>
        </div>
        <div class="col-12">
          <button type="submit" name="update_address" class="btn btn-success"><i class="fas fa-check"></i> Save Address</button>
        </div>
      </div>
    </form>
  </div>

  <!-- PASSWORD CARD -->
  <div class="card">
    <div class="card-header"><i class="fas fa-lock"></i> Change Password</div>
    <form method="POST">
      <div class="row g-3">
        <div class="col-md-4">
          <div class="form-group">
            <input type="password" name="current_password" class="form-control" placeholder="Current Password" required>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <input type="password" name="new_password" class="form-control" placeholder="New Password" required>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm New" required>
          </div>
        </div>
        <div class="col-12">
          <button type="submit" name="change_password" class="btn"><i class="fas fa-key"></i> Update Password</button>
        </div>
      </div>
    </form>
  </div>

  <!-- SUPPORT & DELETE -->
  <div class="row">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header"><i class="fas fa-life-ring"></i> Support</div>
        <div class="text-center">
          <p><a href="help.php" class="btn" style="width:100%; margin-bottom:0.5rem;">Help Center</a></p>
          <p><a href="policy.php" class="btn" style="width:100%; margin-bottom:0.5rem; background:#17a2b8; color:white;">Policies</a></p>
          <p><a href="feedback.php" class="btn btn-success" style="width:100%;">Rate Us</a></p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card" style="border: 2px solid #dc3545;">
        <div class="card-header" style="background:#dc3545; color:white;"><i class="fas fa-trash-alt"></i> Delete Account</div>
        <div class="text-center">
          <p class="text-danger"><strong>This will permanently delete your account and all data.</strong></p>
          <form method="POST" onsubmit="return confirm('A verification email will be sent. Continue?');">
            <button type="submit" name="request_deletion" class="btn btn-danger"><i class="fas fa-exclamation-triangle"></i> Request Deletion</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<script>
  // Reveal on scroll
  const cards = document.querySelectorAll('.card');
  const reveal = () => {
    const trigger = window.innerHeight * 0.85;
    cards.forEach(card => {
      if (card.getBoundingClientRect().top < trigger) card.classList.add('show');
    });
  };
  window.addEventListener('scroll', reveal); reveal();

  // Dropdown
  function toggleDropdown(el) {
    const dropdown = el.querySelector('.dropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
  }

  // Toast auto-hide
  setTimeout(() => {
    const toast = document.getElementById('toast');
    if (toast) toast.style.opacity = '0'; setTimeout(() => toast.remove(), 500);
  }, 4000);
</script>
</body>
</html>
