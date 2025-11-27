-- Clean all transactional data and reset auto-increment values
-- This keeps the structure and configuration data (staff, water_types, order_types, etc.)
-- But removes all customer orders, batches, and related transactional records

SET FOREIGN_KEY_CHECKS = 0;

-- Delete transactional data
DELETE FROM archived_orders;
DELETE FROM archive_log;

DELETE FROM batches;
DELETE FROM customers;
DELETE FROM customer_feedback;
DELETE FROM deliveries;
DELETE FROM orders;
DELETE FROM order_details;
DELETE FROM payments;
DELETE FROM accounts WHERE is_admin = 0 OR is_admin IS NULL;  -- Keep admin accounts
DELETE FROM checkouts;
DELETE FROM notifications;
DELETE FROM staff;

-- Clean containers and inventory (they should be synchronized)
DELETE FROM inventory;
DELETE FROM containers;
DELETE FROM staff;

-- Reset auto-increment counters

ALTER TABLE batches AUTO_INCREMENT = 1;
ALTER TABLE customers AUTO_INCREMENT = 1;
ALTER TABLE customer_feedback AUTO_INCREMENT = 1;
ALTER TABLE deliveries AUTO_INCREMENT = 1;
ALTER TABLE orders AUTO_INCREMENT = 1;
ALTER TABLE order_details AUTO_INCREMENT = 1;
ALTER TABLE payments AUTO_INCREMENT = 1;
ALTER TABLE accounts AUTO_INCREMENT = 1;
ALTER TABLE checkouts AUTO_INCREMENT = 1;
ALTER TABLE containers AUTO_INCREMENT = 1;
ALTER TABLE inventory AUTO_INCREMENT = 1;
ALTER TABLE notifications AUTO_INCREMENT = 1;
ALTER TABLE staff AUTO_INCREMENT = 1;
ALTER TABLE archived_orders AUTO_INCREMENT = 1;
ALTER TABLE archive_log AUTO_INCREMENT = 1;
ALTER TABLE staff AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;
