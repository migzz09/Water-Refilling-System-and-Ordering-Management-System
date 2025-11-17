-- Add password_changed_at column to accounts table
-- This tracks when the user last changed their password

ALTER TABLE `accounts` 
ADD COLUMN IF NOT EXISTS `password_changed_at` DATETIME DEFAULT NULL AFTER `password`;

-- Set current timestamp for existing accounts
UPDATE `accounts` 
SET `password_changed_at` = NOW() 
WHERE `password_changed_at` IS NULL;
