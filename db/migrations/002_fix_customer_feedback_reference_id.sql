-- Migration: Fix customer_feedback table to allow general feedback without order reference
-- Date: 2025-11-03
-- Description: Drop foreign key constraint and allow reference_id to be NULL for general feedback

-- Drop the foreign key constraint
ALTER TABLE `customer_feedback` 
DROP FOREIGN KEY `customer_feedback_ibfk_1`;

-- Make reference_id nullable (if not already)
ALTER TABLE `customer_feedback` 
MODIFY COLUMN `reference_id` VARCHAR(6) NULL;

-- Add back the foreign key constraint with ON DELETE SET NULL instead of CASCADE
-- This allows feedback to remain even if the order is deleted
ALTER TABLE `customer_feedback`
ADD CONSTRAINT `customer_feedback_ibfk_1` 
FOREIGN KEY (`reference_id`) 
REFERENCES `orders` (`reference_id`) 
ON DELETE SET NULL;

-- Verify the change
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    IS_NULLABLE,
    COLUMN_TYPE
FROM 
    INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = 'wrsoms' 
    AND TABLE_NAME = 'customer_feedback'
    AND COLUMN_NAME = 'reference_id';
