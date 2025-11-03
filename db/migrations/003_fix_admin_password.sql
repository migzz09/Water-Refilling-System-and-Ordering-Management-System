-- Quick fix for existing admin account
-- Run this if you already ran the migration and need to fix the password

-- Option 1: Update existing admin account to use plain text password
UPDATE `accounts` 
SET `password` = 'admin123' 
WHERE `username` = 'admin' AND `is_admin` = 1;

-- Or if the admin account doesn't exist yet, create it:
-- INSERT INTO `accounts` (`customer_id`, `username`, `password`, `otp`, `otp_expires`, `is_verified`, `is_admin`) 
-- VALUES (NULL, 'admin', 'admin123', NULL, CURRENT_TIMESTAMP, 1, 1);
