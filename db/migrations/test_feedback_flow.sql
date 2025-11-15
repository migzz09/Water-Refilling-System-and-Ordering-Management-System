-- Test script to verify feedback category flow
-- Run this AFTER running 003_add_feedback_category_table.sql

-- 1. Verify feedback_category table has correct categories
SELECT 'Checking feedback_category table:' as test_step;
SELECT category_id, category_name FROM feedback_category ORDER BY category_id;

-- 2. Check if category_id column exists in customer_feedback
SELECT 'Checking customer_feedback structure:' as test_step;
DESCRIBE customer_feedback;

-- 3. Test category lookup (simulating what submit_feedback.php does)
SELECT 'Testing category lookup for "service":' as test_step;
SELECT category_id, category_name 
FROM feedback_category 
WHERE LOWER(category_name) = LOWER('service');

SELECT 'Testing category lookup for "product":' as test_step;
SELECT category_id, category_name 
FROM feedback_category 
WHERE LOWER(category_name) = LOWER('product');

SELECT 'Testing category lookup for "website":' as test_step;
SELECT category_id, category_name 
FROM feedback_category 
WHERE LOWER(category_name) = LOWER('website');

SELECT 'Testing category lookup for "other":' as test_step;
SELECT category_id, category_name 
FROM feedback_category 
WHERE LOWER(category_name) = LOWER('other');

-- 4. Check existing feedback data migration
SELECT 'Checking migrated feedback (if any exists):' as test_step;
SELECT 
    cf.feedback_id,
    cf.category_id,
    fc.category_name,
    SUBSTRING(cf.feedback_text, 1, 50) as feedback_preview
FROM customer_feedback cf
LEFT JOIN feedback_category fc ON cf.category_id = fc.category_id
ORDER BY cf.feedback_date DESC
LIMIT 10;

-- 5. Test the admin query (simulating what feedback.php does)
SELECT 'Testing admin feedback query:' as test_step;
SELECT 
    cf.feedback_id,
    cf.customer_id,
    cf.category_id,
    cf.rating,
    fc.category_name,
    c.first_name,
    c.last_name,
    a.username,
    SUBSTRING(cf.feedback_text, 1, 100) as feedback_preview
FROM customer_feedback cf
LEFT JOIN customers c ON cf.customer_id = c.customer_id
LEFT JOIN accounts a ON a.customer_id = c.customer_id
LEFT JOIN feedback_category fc ON cf.category_id = fc.category_id
ORDER BY cf.feedback_date DESC
LIMIT 5;

-- 6. Check for any feedback without category_id (should be handled by fallback)
SELECT 'Checking feedback without category_id:' as test_step;
SELECT COUNT(*) as uncategorized_count
FROM customer_feedback
WHERE category_id IS NULL;
