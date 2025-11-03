-- Migration: Add inventory management columns
-- Date: 2025-11-03
-- Description: Add size and stock_quantity columns to containers table for inventory management

-- Add size column to containers table
ALTER TABLE `containers` 
ADD COLUMN `size` VARCHAR(50) DEFAULT NULL AFTER `container_type`;

-- Add stock_quantity column to containers table
ALTER TABLE `containers` 
ADD COLUMN `stock_quantity` INT(11) DEFAULT 0 AFTER `price`;

-- Update existing containers with size information
UPDATE `containers` SET `size` = '5 Gallons' WHERE `container_type` = 'Round';
UPDATE `containers` SET `size` = '3 Gallons' WHERE `container_type` = 'Slim';

-- Set initial stock quantities (default to 50 units each)
UPDATE `containers` SET `stock_quantity` = 50;

-- Create vehicle_capacity table for managing vehicle capacity settings
CREATE TABLE IF NOT EXISTS `vehicle_capacity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_type` enum('Tricycle','Car') NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 5,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicle_type` (`vehicle_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default vehicle capacities
INSERT INTO `vehicle_capacity` (`vehicle_type`, `capacity`) VALUES
('Tricycle', 5),
('Car', 10)
ON DUPLICATE KEY UPDATE capacity=capacity;

-- Add index for faster queries
ALTER TABLE `containers` ADD INDEX `idx_stock_quantity` (`stock_quantity`);

-- Create inventory_log table for tracking stock changes
CREATE TABLE IF NOT EXISTS `inventory_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `container_id` int(11) NOT NULL,
  `change_type` enum('ADD','REMOVE','ADJUST') NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `previous_quantity` int(11) NOT NULL,
  `new_quantity` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `container_id` (`container_id`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_inventory_container` FOREIGN KEY (`container_id`) REFERENCES `containers` (`container_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add comment to containers table
ALTER TABLE `containers` COMMENT = 'Container types with pricing and inventory tracking';
