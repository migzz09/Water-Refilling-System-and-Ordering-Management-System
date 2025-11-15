-- Ultra-Simplified Archive System
-- Store complete order snapshot as JSON, keep only essential columns for searching

CREATE TABLE IF NOT EXISTS `archived_orders` (
    `archive_id` INT(11) NOT NULL AUTO_INCREMENT,
    `reference_id` VARCHAR(50) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `delivery_date` DATE NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `order_data` JSON NOT NULL COMMENT 'Complete order snapshot with all details',
    `archived_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `archived_by` INT(11) DEFAULT NULL,
    PRIMARY KEY (`archive_id`),
    KEY `idx_reference` (`reference_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_delivery_date` (`delivery_date`),
    KEY `idx_archived_at` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Archived orders with complete data in JSON';

-- Archive log table (tracks what was archived and when)
CREATE TABLE IF NOT EXISTS `archive_log` (
    `log_id` INT(11) NOT NULL AUTO_INCREMENT,
    `archive_type` ENUM('manual','automatic','end_of_day') NOT NULL,
    `orders_archived` INT(11) DEFAULT 0,
    `archived_by` INT(11) DEFAULT NULL,
    `archived_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `date_filter` DATE DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    PRIMARY KEY (`log_id`),
    KEY `idx_archive_date` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for archive operations';
