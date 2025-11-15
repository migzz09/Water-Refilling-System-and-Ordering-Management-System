UPDATE `accounts` 
SET `password` = 'admin123' 
WHERE `username` = 'admin' AND `is_admin` = 1;