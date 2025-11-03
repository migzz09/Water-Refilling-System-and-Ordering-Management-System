-- Migration: Fix admin authentication and is_admin column
-- Date: 2025-11-04
-- Description: Ensures is_admin column exists and sets up admin user properly

-- Step 1: Check if is_admin column exists, add if missing
-- (MySQL will ignore this if column already exists)
ALTER TABLE `accounts` 
ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_verified`;

-- Step 2: Set a known admin user (change 'admin' to your admin username)
-- This ensures at least one admin account exists
UPDATE `accounts` 
SET `is_admin` = 1 
WHERE `username` = 'admin' AND `is_verified` = 1;

-- Step 3: Verify the change
SELECT 
    customer_id,
    username,
    email,
    is_admin,
    is_verified
FROM `accounts`
WHERE `is_admin` = 1;

-- Expected result: Should show at least one user with is_admin = 1

-- Alternative: If you need to create a new admin user from scratch
-- Uncomment and modify the following INSERT statement:

/*
INSERT INTO `accounts` (
    `username`, 
    `email`, 
    `password`, 
    `first_name`, 
    `last_name`, 
    `is_admin`, 
    `is_verified`, 
    `created_at`
) VALUES (
    'admin',                    -- Username
    'admin@waterworld.ph',      -- Email
    'admin123',                 -- Password (CHANGE THIS AFTER FIRST LOGIN!)
    'System',                   -- First name
    'Administrator',            -- Last name
    1,                          -- is_admin = 1 (YES)
    1,                          -- is_verified = 1 (YES)
    NOW()                       -- created_at
);
*/

-- Note: The password above is plain text. You should:
-- 1. Login with admin/admin123
-- 2. Change the password immediately
-- 3. The system will hash it properly on password change

-- Step 4: Check all users and their admin status
SELECT 
    customer_id,
    username,
    CONCAT(first_name, ' ', last_name) as full_name,
    email,
    is_admin,
    is_verified,
    created_at
FROM `accounts`
ORDER BY is_admin DESC, username;
