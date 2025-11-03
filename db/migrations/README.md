# Database Migrations

This folder contains SQL migration scripts for the WRSOMS database.

## How to Apply Migrations

### Using phpMyAdmin (Recommended for Development)

1. Open **phpMyAdmin** in your browser: `http://localhost/phpmyadmin`
2. Select the **wrsoms** database from the left sidebar
3. Click on the **SQL** tab at the top
4. Open the migration file you want to run (e.g., `002_fix_customer_feedback_reference_id.sql`)
5. Copy the SQL commands from the migration file
6. Paste them into the SQL query box in phpMyAdmin
7. Click **Go** to execute the migration

### Using Command Line (Alternative)

If you have MySQL command line tools installed:

```bash
mysql -u root -p wrsoms < db/migrations/002_fix_customer_feedback_reference_id.sql
```

## Migration Files

### 001_add_checkouts.sql
- **Date:** Initial migration
- **Description:** Creates the checkouts table for order checkout functionality
- **Status:** ✅ Applied (already in production)

### 002_fix_customer_feedback_reference_id.sql
- **Date:** 2025-11-03
- **Description:** Fixes customer_feedback table to allow general feedback without order reference
- **Changes:**
  - Drops strict foreign key constraint on `reference_id`
  - Makes `reference_id` nullable (allows NULL values)
  - Re-adds foreign key with `ON DELETE SET NULL` instead of `CASCADE`
- **Why:** Allows customers to submit general feedback not related to a specific order
- **Status:** ⚠️ **Needs to be applied**

## Important Notes

- Always backup your database before applying migrations
- Migrations should be applied in numerical order (001, 002, 003, etc.)
- Once applied, mark the migration as completed in this README
- Test migrations on a development database first before applying to production

## Troubleshooting

### Foreign Key Constraint Errors
If you get a foreign key constraint error when submitting feedback:
```
SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row
```

**Solution:** Apply migration `002_fix_customer_feedback_reference_id.sql`

### Migration Already Applied
If you get an error saying the foreign key doesn't exist:
- The migration may have already been applied
- Check your database structure using `SHOW CREATE TABLE customer_feedback;`

## Verification

After applying migration 002, verify it worked:

```sql
-- Check that reference_id is now nullable
SELECT 
    COLUMN_NAME,
    IS_NULLABLE,
    COLUMN_TYPE
FROM 
    INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = 'wrsoms' 
    AND TABLE_NAME = 'customer_feedback'
    AND COLUMN_NAME = 'reference_id';

-- Should return: IS_NULLABLE = 'YES'
```
