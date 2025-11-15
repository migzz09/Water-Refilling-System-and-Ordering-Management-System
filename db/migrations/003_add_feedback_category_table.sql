-- Migration: Add feedback_category table and normalize customer_feedback table
-- This migration creates a separate table for feedback categories (3NF normalization)

-- Step 1: Create feedback_category table
CREATE TABLE IF NOT EXISTS `feedback_category` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `unique_category` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Step 2: Insert default categories
INSERT INTO `feedback_category` (`category_name`) VALUES
('Product'),
('Service'),
('Website'),
('Other')
ON DUPLICATE KEY UPDATE category_name = category_name;

-- Step 3: Add category_id column to customer_feedback table
ALTER TABLE `customer_feedback` 
ADD COLUMN `category_id` int(11) NULL AFTER `customer_id`;

-- Step 4: Add foreign key constraint
ALTER TABLE `customer_feedback`
ADD CONSTRAINT `fk_feedback_category`
FOREIGN KEY (`category_id`) REFERENCES `feedback_category` (`category_id`)
ON DELETE SET NULL
ON UPDATE CASCADE;

-- Step 5: Migrate existing data - Extract category from feedback_text and link to category_id
-- This parses the "Category: xxx" format in existing feedback_text
UPDATE `customer_feedback` cf
SET cf.category_id = (
    SELECT fc.category_id 
    FROM `feedback_category` fc
    WHERE LOWER(fc.category_name) = LOWER(
        TRIM(
            SUBSTRING_INDEX(
                SUBSTRING_INDEX(cf.feedback_text, 'Category:', -1),
                '\n',
                1
            )
        )
    )
    LIMIT 1
)
WHERE cf.feedback_text LIKE 'Category:%';

-- Step 5b: Map old "delivery" and "delivery service" categories to "Service"
UPDATE `customer_feedback` cf
SET cf.category_id = (
    SELECT fc.category_id 
    FROM `feedback_category` fc
    WHERE fc.category_name = 'Service'
    LIMIT 1
)
WHERE cf.feedback_text REGEXP 'Category:[[:space:]]*(delivery|delivery service|Delivery|Delivery Service)'
AND cf.category_id IS NULL;

-- Step 6: Create index for better query performance
CREATE INDEX `idx_feedback_category` ON `customer_feedback` (`category_id`);

-- Note: The feedback_text column still contains the full text with "Category: xxx" format
-- for backward compatibility. New feedback should use category_id directly.
