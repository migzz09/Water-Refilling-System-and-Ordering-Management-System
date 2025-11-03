<?php
/**
 * Quick Admin Check Tool
 * This file helps diagnose admin login issues
 */
session_start();
require_once __DIR__ . '/config/connect.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Check Tool</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        .info { color: #9cdcfe; }
        pre { background: #2d2d2d; padding: 15px; border-radius: 5px; overflow-x: auto; }
        h2 { color: #4ec9b0; border-bottom: 2px solid #4ec9b0; padding-bottom: 10px; }
        .section { margin: 20px 0; padding: 15px; background: #252526; border-left: 4px solid #4ec9b0; }
    </style>
</head>
<body>
    <h1>🔧 WRSOMS Admin Diagnostic Tool</h1>
    
    <div class="section">
        <h2>1. Current Session Status</h2>
        <?php
        echo "<pre>";
        echo "Session ID: " . session_id() . "\n";
        echo "Session Data:\n";
        print_r($_SESSION);
        echo "</pre>";
        
        if (isset($_SESSION['customer_id'])) {
            echo "<p class='success'>✅ User is logged in</p>";
            echo "<p>Customer ID: " . $_SESSION['customer_id'] . "</p>";
            echo "<p>Username: " . ($_SESSION['username'] ?? 'NOT SET') . "</p>";
            echo "<p>is_admin: " . ($_SESSION['is_admin'] ?? 'NOT SET') . " (type: " . gettype($_SESSION['is_admin'] ?? null) . ")</p>";
            
            if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
                echo "<p class='success'>✅ Session shows ADMIN privileges</p>";
            } else {
                echo "<p class='error'>❌ Session shows NO admin privileges</p>";
            }
        } else {
            echo "<p class='warning'>⚠️ No active session (not logged in)</p>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>2. Database Check - All Users</h2>
        <?php
        try {
            $stmt = $pdo->query("SELECT customer_id, username, is_admin, is_verified FROM accounts ORDER BY is_admin DESC, username");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<p class='info'>Found " . count($users) . " users in database</p>";
            echo "<table style='width:100%; border-collapse: collapse; margin-top: 10px;'>";
            echo "<tr style='background: #333; font-weight: bold;'>";
            echo "<th style='padding: 10px; border: 1px solid #555;'>ID</th>";
            echo "<th style='padding: 10px; border: 1px solid #555;'>Username</th>";
            echo "<th style='padding: 10px; border: 1px solid #555;'>is_admin</th>";
            echo "<th style='padding: 10px; border: 1px solid #555;'>is_verified</th>";
            echo "</tr>";
            
            foreach ($users as $user) {
                $adminClass = $user['is_admin'] == 1 ? 'success' : '';
                $verifiedClass = $user['is_verified'] == 1 ? 'success' : 'error';
                $customerIdDisplay = $user['customer_id'] ?? 'NULL';
                $customerIdClass = empty($user['customer_id']) ? 'error' : '';
                
                echo "<tr>";
                echo "<td class='$customerIdClass' style='padding: 10px; border: 1px solid #555;'>" . 
                     ($customerIdDisplay === 'NULL' || $customerIdDisplay === '' ? '❌ NULL/EMPTY' : $customerIdDisplay) . "</td>";
                echo "<td style='padding: 10px; border: 1px solid #555;'>" . htmlspecialchars($user['username']) . "</td>";
                echo "<td class='$adminClass' style='padding: 10px; border: 1px solid #555; font-weight: bold;'>" . 
                     ($user['is_admin'] == 1 ? '✅ YES (1)' : '❌ NO (0)') . "</td>";
                echo "<td class='$verifiedClass' style='padding: 10px; border: 1px solid #555;'>" . 
                     ($user['is_verified'] == 1 ? '✅ YES' : '❌ NO') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Check for empty customer_ids
            $emptyIdCount = array_filter($users, function($u) { return empty($u['customer_id']); });
            if (count($emptyIdCount) > 0) {
                echo "<p class='error'>❌ WARNING: " . count($emptyIdCount) . " user(s) have NULL or empty customer_id!</p>";
                echo "<p class='warning'>This will cause login issues. Check your database structure.</p>";
            }
            
            $adminCount = array_filter($users, function($u) { return $u['is_admin'] == 1; });
            if (count($adminCount) == 0) {
                echo "<p class='error'>❌ NO ADMIN USERS FOUND! You need to set at least one user as admin.</p>";
                echo "<p class='warning'>Run this SQL in phpMyAdmin:</p>";
                echo "<pre>UPDATE accounts SET is_admin = 1 WHERE username = 'your_admin_username';</pre>";
            } else {
                echo "<p class='success'>✅ Found " . count($adminCount) . " admin user(s)</p>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>3. Database Structure Check</h2>
        <?php
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM accounts");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $hasIsAdmin = false;
            foreach ($columns as $col) {
                if ($col['Field'] == 'is_admin') {
                    $hasIsAdmin = true;
                    echo "<p class='success'>✅ is_admin column EXISTS</p>";
                    echo "<pre>";
                    echo "Field: " . $col['Field'] . "\n";
                    echo "Type: " . $col['Type'] . "\n";
                    echo "Null: " . $col['Null'] . "\n";
                    echo "Default: " . ($col['Default'] ?? 'NULL') . "\n";
                    echo "</pre>";
                    break;
                }
            }
            
            if (!$hasIsAdmin) {
                echo "<p class='error'>❌ is_admin column DOES NOT EXIST!</p>";
                echo "<p class='warning'>Run this SQL in phpMyAdmin:</p>";
                echo "<pre>ALTER TABLE accounts ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER is_verified;</pre>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>4. Quick Actions</h2>
        <p class='info'>Copy these SQL queries and run them in phpMyAdmin:</p>
        
        <h3 class='warning'>Make a user admin (replace 'username'):</h3>
        <pre>UPDATE accounts SET is_admin = 1 WHERE username = 'admin';</pre>
        
        <h3 class='warning'>Create new admin user:</h3>
        <pre>INSERT INTO accounts (username, email, password, first_name, last_name, is_admin, is_verified, created_at)
VALUES ('admin', 'admin@waterworld.ph', 'admin123', 'System', 'Administrator', 1, 1, NOW());</pre>
        
        <h3 class='warning'>Remove admin from user:</h3>
        <pre>UPDATE accounts SET is_admin = 0 WHERE username = 'username';</pre>
    </div>
    
    <div class="section">
        <h2>5. Test Login API</h2>
        <p>Test the login directly:</p>
        <form method="POST" action="api/auth/login.php" target="_blank" style="margin: 10px 0;">
            <input type="text" name="username" placeholder="Username" value="admin" style="padding: 8px; margin: 5px; background: #2d2d2d; color: #d4d4d4; border: 1px solid #555;">
            <input type="password" name="password" placeholder="Password" value="admin123" style="padding: 8px; margin: 5px; background: #2d2d2d; color: #d4d4d4; border: 1px solid #555;">
            <button type="button" onclick="testLogin()" style="padding: 8px 16px; background: #4ec9b0; border: none; color: #1e1e1e; cursor: pointer; border-radius: 3px;">Test Login</button>
        </form>
        <div id="loginResult"></div>
        
        <script>
        async function testLogin() {
            const username = document.querySelector('input[name="username"]').value;
            const password = document.querySelector('input[name="password"]').value;
            const resultDiv = document.getElementById('loginResult');
            
            resultDiv.innerHTML = '<p style="color: #dcdcaa;">Testing login...</p>';
            
            try {
                const response = await fetch('/WRSOMS/api/auth/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ username, password })
                });
                
                const data = await response.json();
                
                let html = '<h3 style="color: #4ec9b0;">Login Response:</h3>';
                html += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                
                if (data.success && data.data && data.data.is_admin == 1) {
                    html += '<p style="color: #4ec9b0;">✅ Login successful as ADMIN</p>';
                    html += '<p style="color: #9cdcfe;">Refresh this page to see updated session.</p>';
                } else if (data.success) {
                    html += '<p style="color: #dcdcaa;">⚠️ Login successful as REGULAR USER</p>';
                } else {
                    html += '<p style="color: #f48771;">❌ Login failed: ' + data.message + '</p>';
                }
                
                resultDiv.innerHTML = html;
                
                // Reload page after 2 seconds if login successful
                if (data.success) {
                    setTimeout(() => location.reload(), 2000);
                }
            } catch (error) {
                resultDiv.innerHTML = '<p style="color: #f48771;">Error: ' + error.message + '</p>';
            }
        }
        </script>
    </div>
    
    <p style="margin-top: 40px; color: #666; text-align: center;">
        Check XAMPP error logs for detailed session debugging info
    </p>
</body>
</html>
