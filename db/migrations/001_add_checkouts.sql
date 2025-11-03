-- Migration: add checkouts table and link orders to checkouts
-- Run this in your database (e.g., via phpMyAdmin or mysql client) in the wrsoms database.

START TRANSACTION;

-- Create checkouts table
CREATE TABLE IF NOT EXISTS `checkouts` (
  `checkout_id` INT(11) NOT NULL AUTO_INCREMENT,
  `customer_id` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`checkout_id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add nullable checkout_id to orders so orders can be grouped into a checkout
ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `checkout_id` INT(11) DEFAULT NULL AFTER `customer_id`;

-- Add foreign key constraint (optional) - ensure referenced table exists and matches your privileges
ALTER TABLE `orders` ADD CONSTRAINT IF NOT EXISTS `orders_ibfk_checkout` FOREIGN KEY (`checkout_id`) REFERENCES `checkouts`(`checkout_id`) ON DELETE SET NULL;

COMMIT;

-- Note:
-- 1) After adding this, update your order creation logic (`api/orders/create.php`) to create a checkout row and set orders.checkout_id when multiple items are placed in one cart.
-- 2) You may need to backfill checkouts for existing grouped orders depending on how your app stores cart/transaction state.
