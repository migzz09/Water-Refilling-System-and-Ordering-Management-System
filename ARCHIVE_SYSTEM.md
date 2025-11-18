# Archive System for Completed Orders

## Overview
This system automatically archives completed orders and batches to keep the main database clean while preserving historical data for reports and analytics.

## Features
✅ **Archive Tables** - Separate tables for historical data  
✅ **Manual Archive Button** - Archive completed orders with one click  
✅ **Automatic Archive** - Run via cron/scheduled task  
✅ **Archive Log** - Track all archive operations  
✅ **Data Preservation** - All order history kept for reports  

## Database Tables Created

### Main Archive Tables:
- `archived_orders` - Completed orders
- `archived_order_items` - Order items history
- `archived_deliveries` - Delivery records
- `archived_batches` - Completed batches
- `archive_log` - Archive operation tracking

## Usage

### 1. Manual Archive (via UI)
1. Go to **Manage Orders** page
2. Click **"Archive Completed"** button (yellow button with archive icon)
3. Confirm the action
4. All completed orders for today will be archived

### 2. Automatic Archive (Scheduled)

#### Windows Task Scheduler:
```batch
# Create a batch file: archive_daily.bat
@echo off
cd C:\xampp\htdocs\WRSOMS
C:\xampp\php\php.exe api\admin\auto_archive.php >> logs\archive.log 2>&1
```

**Schedule it:**
1. Open Task Scheduler
2. Create Basic Task
3. Name: "Daily Order Archive"
4. Trigger: Daily at 11:59 PM
5. Action: Start a program
6. Program: `C:\xampp\htdocs\WRSOMS\archive_daily.bat`

#### Linux Cron Job:
```bash
# Edit crontab
crontab -e

# Add this line (runs daily at 11:59 PM)
59 23 * * * /usr/bin/php /path/to/WRSOMS/api/admin/auto_archive.php >> /path/to/logs/archive.log 2>&1
```

## What Gets Archived?

### Criteria:
- Orders with `delivery_status_id = 3` (Completed)
- Delivery date matches the specified date
- Associated batches (if all orders in batch are completed)

### Process:
1. Copy completed orders to `archived_orders`
2. Copy order items to `archived_order_items`
3. Copy delivery records to `archived_deliveries`
4. If batch is fully completed, archive to `archived_batches`
5. Delete from main tables
6. Log the operation in `archive_log`

## Benefits

### Performance
- Main tables stay small and fast
- Queries run faster with less data
- Database maintenance is easier

### Data Retention
- All historical data preserved
- Can generate reports from archives
- Customer order history available
- Audit trail maintained

### Compliance
- Meets data retention requirements
- Track who archived what and when
- Can restore if needed

## Viewing Archived Data

### SQL Queries:
```sql
-- View all archived orders
SELECT * FROM archived_orders ORDER BY archived_at DESC LIMIT 100;

-- View customer order history (including archived)
SELECT * FROM orders WHERE user_id = 123
UNION ALL
SELECT * FROM archived_orders WHERE user_id = 123
ORDER BY order_date DESC;

-- Archive statistics
SELECT 
    DATE(archived_at) as archive_date,
    COUNT(*) as orders_count,
    SUM(total_amount) as total_revenue
FROM archived_orders
GROUP BY DATE(archived_at)
ORDER BY archive_date DESC;

-- View archive log
SELECT * FROM archive_log ORDER BY archived_at DESC;
```

### Future Enhancement Ideas:
1. **Archive Viewer Page** - Admin UI to view/search archives
2. **Export Archives** - Download as CSV/Excel
3. **Restore Feature** - Unarchive if needed
4. **Archive Reports** - Monthly/yearly summaries
5. **Automatic Cleanup** - Delete archives older than X years

## Files Created

### Database:
- `db/migrations/007_add_archive_tables.sql` - Migration file

### API Endpoints:
- `api/admin/archive_completed.php` - Manual archive endpoint
- `api/admin/auto_archive.php` - Automatic archive script

### UI:
- "Archive Completed" button in `pages/admin/manage-orders.html`

## Testing

### Test Manual Archive:
1. Complete some orders (mark as delivered)
2. Go to Manage Orders
3. Click "Archive Completed"
4. Verify orders moved to archive tables
5. Check `archive_log` for the operation record

### Test Automatic Archive:
```bash
# Run manually to test
php api/admin/auto_archive.php
```

## Troubleshooting

### Archive button not showing?
- Hard refresh browser (Ctrl + Shift + R)
- Check if Font Awesome icons are loaded

### Archive failing?
- Check `archive_log` table for error details
- Verify all archive tables exist
- Check database permissions
- Look at PHP error logs

### Orders not archiving?
- Verify `delivery_status_id = 3` (Completed)
- Check the date filter matches delivery_date
- Ensure orders have delivery records

## Maintenance

### Recommended Schedule:
- **Daily**: Automatic archive at end of day
- **Weekly**: Check archive_log for issues
- **Monthly**: Generate archive statistics report
- **Yearly**: Consider archiving old archives to long-term storage

### Database Cleanup:
```sql
-- Optional: Delete very old archives (e.g., older than 5 years)
-- CAUTION: This permanently deletes data!
DELETE FROM archived_orders 
WHERE archived_at < DATE_SUB(NOW(), INTERVAL 5 YEAR);
```

## Security Notes
- Archive endpoints require admin authentication
- Archive log tracks who performed manual archives
- Automatic archives logged with NULL user_id
- Consider backing up archive tables separately

---
**Created:** November 10, 2025  
**Version:** 1.0  
**Status:** Ready for production use
