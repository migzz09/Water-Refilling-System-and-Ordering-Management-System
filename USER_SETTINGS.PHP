<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['customer_id'];

$stmt = $pdo->prepare("SELECT a.username, a.phone_number, a.profile_photo, c.email, c.street, c.barangay, c.city, c.province, c.date_created 
                       FROM accounts a 
                       LEFT JOIN customers c ON a.customer_id = c.customer_id 
                       WHERE a.customer_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: logout.php");
    exit;
}

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // === UPDATE PROFILE ===
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone_number = trim($_POST['phone_number']);

        if (empty($username) || empty($email) || empty($phone_number)) {
            $errors[] = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email.";
        } else {
            try {
                $pdo->prepare("UPDATE accounts SET username = ?, phone_number = ? WHERE customer_id = ?")
                    ->execute([$username, $phone_number, $user_id]);
                $pdo->prepare("UPDATE customers SET email = ? WHERE customer_id = ?")
                    ->execute([$email, $user_id]);
                $success = "Profile updated!";
                $user['username'] = $username;
                $user['phone_number'] = $phone_number;
                $user['email'] = $email;
            } catch (Exception $e) {
                $errors[] = "Update failed.";
            }
        }
    }

    // === CHANGE PASSWORD ===
    elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        $stmt = $pdo->prepare("SELECT password FROM accounts WHERE customer_id = ?");
        $stmt->execute([$user_id]);
        $acc = $stmt->fetch();

        if ($current !== $acc['password']) {
            $errors[] = "Current password wrong.";
        } elseif ($new !== $confirm) {
            $errors[] = "New passwords don't match.";
        } elseif (strlen($new) < 6) {
            $errors[] = "Password too short.";
        } else {
            $pdo->prepare("UPDATE accounts SET password = ? WHERE customer_id = ?")
                ->execute([$new, $user_id]);
            $success = "Password changed!";
        }
    }

    // === UPDATE PHOTO ===
    elseif (isset($_POST['update_photo']) && $_FILES['profile_photo']['error'] === 0) {
        $file = $_FILES['profile_photo'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array(strtolower($ext), $allowed)) {
            $errors[] = "Invalid image type.";
        } elseif ($file['size'] > 2*1024*1024) {
            $errors[] = "Image too large.";
        } else {
            $dir = 'uploads/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $path = $dir . 'profile_' . $user_id . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $path)) {
                if ($user['profile_photo'] && file_exists($user['profile_photo'])) {
                    unlink($user['profile_photo']);
                }
                $pdo->prepare("UPDATE accounts SET profile_photo = ? WHERE customer_id = ?")
                    ->execute([$path, $user_id]);
                $user['profile_photo'] = $path;
                $success = "Photo updated!";
            }
        }
    }

    // === UPDATE ADDRESS ===
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

    // === REQUEST DELETION ===
    elseif (isset($_POST['request_deletion'])) {
        $pdo->prepare("UPDATE accounts SET status = 'pending_deletion' WHERE customer_id = ?")
            ->execute([$user_id]);
        $success = "Deletion request sent. Check email.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Water World</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #007bff;
            --success: #28a745;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
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
        .card-body { padding: 25px; }
        .profile-img {
            width: 120px; height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .btn-primary {
            background: var(--primary);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
        }
        .btn-primary:hover { background: #0056b3; }
        .btn-success { background: var(--success); border: none; }
        .btn-danger { background: var(--danger); border: none; }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px;
            border: 1px solid #ced4da;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .alert {
            border-radius: 12px;
            font-weight: 500;
            margin-bottom: 20px;
        }
        .toast {
            position: fixed;
            top: 20px; right: 20px;
            z-index: 9999;
            min-width: 300px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .back-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover { text-decoration: underline; }
        .section-title {
            font-size: 1.3rem;
            color: var(--dark);
            margin: 25px 0 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title i { color: var(--primary); }
    </style>
</head>
<body>
    <div class="container">

        <!-- Toast Alerts -->
        <?php if ($success): ?>
            <div class="toast bg-success text-white p-3" id="toast">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="toast bg-danger text-white p-3" id="toast">
                <i class="fas fa-exclamation-triangle"></i> <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="text-center mb-4">
            <h1 class="display-5 text-primary fw-bold">
                <i class="fas fa-cog"></i> Settings
            </h1>
            <p class="text-muted">Welcome, <strong><?= htmlspecialchars($user['username']) ?></strong> | <a href="logout.php" class="text-danger">Logout</a></p>
        </div>

        <!-- Profile Card -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user"></i> My Profile
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <form method="POST" enctype="multipart/form-data" id="photoForm">
                            <?php if ($user['profile_photo'] && file_exists($user['profile_photo'])): ?>
                                <img src="<?= htmlspecialchars($user['profile_photo']) ?>" alt="Profile" class="profile-img">
                            <?php else: ?>
                                <div class="bg-light border-dashed border rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width:120px;height:120px;">
                                    <i class="fas fa-user fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="profile_photo" class="form-control mt-3" accept="image/*" onchange="this.form.submit()">
                            <input type="hidden" name="update_photo" value="1">
                        </form>
                    </div>
                    <div class="col-md-9">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fas fa-user-tag"></i> Username</label>
                                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fas fa-envelope"></i> Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label"><i class="fas fa-phone"></i> Phone</label>
                                    <input type="tel" name="phone_number" class="form-control" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>" placeholder="+639123456789" required>
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Profile
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Address Card -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-map-marker-alt"></i> Delivery Address
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Street</label>
                            <input type="text" name="street" class="form-control" value="<?= htmlspecialchars($user['street'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Barangay</label>
                            <input type="text" name="barangay" class="form-control" value="<?= htmlspecialchars($user['barangay'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($user['city'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Province</label>
                            <input type="text" class="form-control" value="Metro Manila" readonly>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="update_address" class="btn btn-success">
                                <i class="fas fa-check"></i> Save Address
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Password Card -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-lock"></i> Change Password
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="password" name="current_password" class="form-control" placeholder="Current Password" required>
                        </div>
                        <div class="col-md-4">
                            <input type="password" name="new_password" class="form-control" placeholder="New Password" required>
                        </div>
                        <div class="col-md-4">
                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm New" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-key"></i> Update Password
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Support & Info -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-life-ring"></i> Support</div>
                    <div class="card-body text-center">
                        <p><a href="help.php" class="btn btn-outline-primary w-100 mb-2">Help Center</a></p>
                        <p><a href="policies.php" class="btn btn-outline-info w-100 mb-2">Policies</a></p>
                        <p><a href="rate_us.php" class="btn btn-outline-success w-100">Rate Us</a></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-trash-alt"></i> Delete Account</div>
                    <div class="card-body text-center">
                        <form method="POST" onsubmit="return confirm('Sure to delete?')">
                            <button type="submit" name="request_deletion" class="btn btn-danger">
                                <i class="fas fa-exclamation-triangle"></i> Request Deletion
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Back -->
        <div class="text-center mt-4">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Toast Script -->
    <script>
        setTimeout(() => {
            const toast = document.getElementById('toast');
            if (toast) toast.style.display = 'none';
        }, 4000);
    </script>
</body>
</html>
