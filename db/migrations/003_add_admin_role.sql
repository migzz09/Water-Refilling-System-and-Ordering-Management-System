-- Migration: Add admin role support to accounts
-- Date: 2025-11-03
-- Description: Adds is_admin flag and increases password field length for secure hashing

-- Step 1: Add is_admin column to accounts table
ALTER TABLE `accounts` 
ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_verified`;

-- Step 2: Increase password field length for bcrypt hashes (60 chars minimum)
ALTER TABLE `accounts` 
MODIFY COLUMN `password` VARCHAR(255) NOT NULL;

-- Step 3: Create default admin account
-- Username: admin
-- Password: admin123 (plain text for now, will be validated by login.php)
-- Note: The login.php has fallback for plain text passwords
INSERT INTO `accounts` (`customer_id`, `username`, `password`, `otp`, `otp_expires`, `is_verified`, `is_admin`) 
VALUES (
    NULL, 
    'admin', 
    'admin123', 
    NULL, 
    CURRENT_TIMESTAMP, 
    1, 
    1
);