-- Migration: Add profile_photo column to accounts table
-- Date: 2025-11-03

ALTER TABLE `accounts` 
ADD COLUMN IF NOT EXISTS `profile_photo` VARCHAR(255) DEFAULT NULL 
AFTER `password`;
