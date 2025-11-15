ALTER TABLE `accounts` 
ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_verified`;


UPDATE `accounts` 
SET `is_admin` = 1 
WHERE `username` = 'admin' AND `is_verified` = 1;

SELECT 
    customer_id,
    username,
    email,
    is_admin,
    is_verified
FROM `accounts`
WHERE `is_admin` = 1;