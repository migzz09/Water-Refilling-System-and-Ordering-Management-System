-- Migration: Add business_hours table
-- This migration creates a table to store business operating hours for each day of the week

CREATE TABLE IF NOT EXISTS `business_hours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `day_of_week` varchar(10) NOT NULL,
  `is_open` tinyint(1) NOT NULL DEFAULT 1,
  `open_time` time NOT NULL DEFAULT '08:00:00',
  `close_time` time NOT NULL DEFAULT '17:00:00',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_day` (`day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default business hours (Monday to Sunday)
INSERT INTO `business_hours` (`day_of_week`, `is_open`, `open_time`, `close_time`) VALUES
('Monday', 1, '08:00:00', '17:00:00'),
('Tuesday', 1, '08:00:00', '17:00:00'),
('Wednesday', 1, '08:00:00', '17:00:00'),
('Thursday', 1, '08:00:00', '17:00:00'),
('Friday', 1, '08:00:00', '17:00:00'),
('Saturday', 1, '09:00:00', '17:00:00'),
('Sunday', 0, '09:00:00', '17:00:00')
ON DUPLICATE KEY UPDATE 
  day_of_week = VALUES(day_of_week);
