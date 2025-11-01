-- Fix password column to support password hashing
-- The current VARCHAR(50) is too small for password_hash() which generates 60+ character strings
-- This migration increases the column size to VARCHAR(255) to accommodate hashed passwords

USE wrsoms;

-- Increase password column size
ALTER TABLE `accounts` 
MODIFY COLUMN `password` VARCHAR(255) NOT NULL;

-- Optional: Show the updated structure
DESCRIBE `accounts`;
