-- Update delivery_status names to be more descriptive and match workflow stages
-- This makes it clearer what each status represents in the pickup/delivery process

-- Before: 1=Pending, 2=Dispatched, 3=Delivered, 4=Failed
-- After:  1=Pending, 2=In Progress, 3=Completed, 4=Failed

UPDATE `delivery_status` SET `status_name` = 'Pending' WHERE `delivery_status_id` = 1;
UPDATE `delivery_status` SET `status_name` = 'In Progress' WHERE `delivery_status_id` = 2;
UPDATE `delivery_status` SET `status_name` = 'Completed' WHERE `delivery_status_id` = 3;
UPDATE `delivery_status` SET `status_name` = 'Failed' WHERE `delivery_status_id` = 4;

-- Mapping explanation:
-- For PICKUP rows:
--   Pending (1) -> Shows "Start Pickup" button
--   In Progress (2) -> Shows "Complete Pickup" button
--   Completed (3) -> Pickup done, shows "Start Delivery" button
--
-- For DELIVERY rows:
--   Pending (1) -> Shows "Start Delivery" button (after pickup is completed)
--   In Progress (2) -> Shows "Complete Delivery" button
--   Completed (3) -> Everything done, shows "✓ Completed" badge

SELECT 'Delivery status names updated successfully!' as message;
