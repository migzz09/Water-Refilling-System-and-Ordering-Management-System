-- Quick check if admin setup is correct
-- Run this in phpMyAdmin to verify your database structure

-- 1. Check if is_admin column exists
DESCRIBE accounts;

-- 2. Check if admin account exists
SELECT account_id, username, password, is_verified, is_admin 
FROM accounts 
WHERE username = 'admin';

-- 3. If admin account doesn't exist or is_admin is 0, run this:
-- UPDATE accounts SET is_admin = 1 WHERE username = 'admin';

-- 4. If is_admin column doesn't exist, you need to run the migration:
-- ALTER TABLE accounts ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER is_verified;
-- ALTER TABLE accounts MODIFY COLUMN password VARCHAR(255) NOT NULL;

-- 5. If no admin account exists at all:
-- INSERT INTO accounts (customer_id, username, password, otp, otp_expires, is_verified, is_admin) 
-- VALUES (NULL, 'admin', 'admin123', NULL, CURRENT_TIMESTAMP, 1, 1);
