# Database Migrations

This directory contains SQL migration scripts for the WRSOMS (Water Refilling Station Order Management System).

## How to Apply Migrations

### Using phpMyAdmin:
1. Open phpMyAdmin in your browser (usually `http://localhost/phpmyadmin`)
2. Select the `wrsoms` database from the left sidebar
3. Click on the "SQL" tab at the top
4. Copy the contents of the migration file
5. Paste into the SQL query box
6. Click "Go" to execute

### Using MySQL Command Line:
```bash
mysql -u root -p wrsoms < migrations/002_add_inventory_columns.sql
```

## Migration Files

### 002_add_inventory_columns.sql
**Date**: 2025-11-03  
**Description**: Adds inventory management features
- Adds `size` column to containers table
- Adds `stock_quantity` column to containers table  
- Creates `vehicle_capacity` table for managing Tricycle/Car capacities
- Creates `inventory_log` table for tracking stock changes
- Initializes default data for existing containers

**Changes**:
- Containers table: `size` VARCHAR(50), `stock_quantity` INT(11)
- New table: `vehicle_capacity` with default Tricycle=5, Car=10
- New table: `inventory_log` for audit trail
- Updates existing Round containers to "5 Gallons"
- Updates existing Slim containers to "3 Gallons"
- Sets initial stock to 50 units for all containers

**Notes**:
- This migration is safe to run multiple times (uses IF NOT EXISTS and ON DUPLICATE KEY UPDATE)
- Existing data will not be lost
- Stock quantities will only be set if currently NULL

## Rollback

To rollback this migration (if needed):

```sql
-- Remove added columns
ALTER TABLE `containers` 
DROP COLUMN `size`,
DROP COLUMN `stock_quantity`,
DROP INDEX `idx_stock_quantity`;

-- Drop new tables
DROP TABLE IF EXISTS `inventory_log`;
DROP TABLE IF EXISTS `vehicle_capacity`;
```

## After Migration

After running the migration:
1. The Inventory Management page will now store data in the database instead of localStorage
2. Vehicle capacity settings will persist across sessions
3. Stock levels will be tracked with audit logging
4. You can view inventory history through the `inventory_log` table

## Future Migrations

New migrations should be numbered sequentially (003, 004, etc.) and follow this format:
- Include date and description in comments
- Use IF NOT EXISTS for new tables
- Use ALTER TABLE with error handling for column additions
- Include rollback instructions
- Update this README with the new migration details
