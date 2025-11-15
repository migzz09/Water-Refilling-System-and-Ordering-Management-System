
ALTER TABLE `accounts` 
ADD COLUMN IF NOT EXISTS `profile_photo` varchar(255) DEFAULT NULL AFTER `is_verified`,
ADD COLUMN IF NOT EXISTS `deletion_token` varchar(255) DEFAULT NULL AFTER `profile_photo`,
ADD COLUMN IF NOT EXISTS `deletion_expires` datetime DEFAULT NULL AFTER `deletion_token`;

-- Verify the changes
SELECT 'Migration 002: Account columns added successfully!' AS Status;
