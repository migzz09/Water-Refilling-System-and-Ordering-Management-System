-- Migration: Add cutoff time setting
-- This migration creates a table to store the daily order cutoff time

CREATE TABLE IF NOT EXISTS `cutoff_time_setting` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `cutoff_time` time NOT NULL DEFAULT '16:00:00',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default cutoff time (4:00 PM / 16:00)
INSERT INTO `cutoff_time_setting` (`is_enabled`, `cutoff_time`) VALUES
(1, '16:00:00')
ON DUPLICATE KEY UPDATE 
  cutoff_time = VALUES(cutoff_time);
