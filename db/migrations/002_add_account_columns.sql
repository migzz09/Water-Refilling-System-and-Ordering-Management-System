<<<<<<< HEAD
-- Migration: Add profile and deletion columns to accounts table
-- Date: November 3, 2025
-- Description: Adds profile_photo, deletion_token, and deletion_expires columns to support
--              user profile pictures and secure account deletion functionality.

-- Add useful columns to accounts table (without redundant phone_number)
=======

>>>>>>> 47603ff9b60986a5fdcfb44ba2f200d0ba062749
ALTER TABLE `accounts` 
ADD COLUMN IF NOT EXISTS `profile_photo` varchar(255) DEFAULT NULL AFTER `is_verified`,
ADD COLUMN IF NOT EXISTS `deletion_token` varchar(255) DEFAULT NULL AFTER `profile_photo`,
ADD COLUMN IF NOT EXISTS `deletion_expires` datetime DEFAULT NULL AFTER `deletion_token`;

-- Verify the changes
SELECT 'Migration 002: Account columns added successfully!' AS Status;
