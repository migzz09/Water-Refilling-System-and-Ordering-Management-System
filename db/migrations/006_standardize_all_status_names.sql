-- Standardize all status tables to use consistent naming
-- This makes the system more intuitive and easier to maintain

-- UPDATE ORDER_STATUS
-- Before: 1=Pending, 2=Dispatched, 3=Delivered, 4=Failed
-- After:  1=Pending, 2=In Progress, 3=Completed, 4=Failed
UPDATE `order_status` SET `status_name` = 'Pending' WHERE `status_id` = 1;
UPDATE `order_status` SET `status_name` = 'In Progress' WHERE `status_id` = 2;
UPDATE `order_status` SET `status_name` = 'Completed' WHERE `status_id` = 3;
UPDATE `order_status` SET `status_name` = 'Failed' WHERE `status_id` = 4;

-- UPDATE BATCH_STATUS
-- Before: 1=Pending, 2=Dispatched, 3=Failed
-- After:  1=Pending, 2=In Progress, 3=Completed, 4=Failed
UPDATE `batch_status` SET `status_name` = 'Pending' WHERE `batch_status_id` = 1;
UPDATE `batch_status` SET `status_name` = 'In Progress' WHERE `batch_status_id` = 2;

-- Add Completed status to batch_status if it doesn't exist
INSERT INTO `batch_status` (`batch_status_id`, `status_name`) 
VALUES (3, 'Completed')
ON DUPLICATE KEY UPDATE `status_name` = 'Completed';

-- Add Failed status to batch_status if it doesn't exist (was previously status 3)
INSERT INTO `batch_status` (`batch_status_id`, `status_name`) 
VALUES (4, 'Failed')
ON DUPLICATE KEY UPDATE `status_name` = 'Failed';

-- DELIVERY_STATUS is already correct from previous migration
-- 1=Pending, 2=In Progress, 3=Completed, 4=Failed

-- Summary of standardized statuses:
-- All three tables now use:
-- 1 = Pending      (Initial state)
-- 2 = In Progress  (Work started)
-- 3 = Completed    (Successfully finished)
-- 4 = Failed       (Error state)

SELECT 'All status tables standardized successfully!' as message;
