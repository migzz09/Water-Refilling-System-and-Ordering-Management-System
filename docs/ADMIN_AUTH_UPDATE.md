# Admin Authentication & Daily Reports Update

## Summary of Changes

This update adds admin authentication, fixes the navigation logo visibility, and implements a functional daily reports feature.

## Changes Made

### 1. Admin Authentication System

#### Database Migration (`db/migrations/003_add_admin_role.sql`)
- Added `is_admin` TINYINT column to `accounts` table
- Increased `password` column length from VARCHAR(50) to VARCHAR(255) for secure bcrypt hashes
- Created default admin account:
  - **Username:** `admin`
  - **Password:** `admin123`
  - **Role:** Admin (is_admin = 1)

**IMPORTANT:** Run this migration before using the admin features:
```bash
mysql -u root -p wrsoms < db/migrations/003_add_admin_role.sql
```

Or manually execute the SQL in phpMyAdmin.

#### Updated API Files
- **`api/auth/login.php`**
  - Now retrieves `is_admin` flag from database
  - Sets `$_SESSION['is_admin']` on successful login
  - Returns `is_admin` status in response

- **`api/auth/session.php`**
  - Updated to include `is_admin` in user session data
  - Allows admin users without customer_id to authenticate

#### Updated Frontend Files
- **`pages/admin/admin-dashboard.html`**
  - Added authentication check on page load
  - Redirects non-admin users to login page
  - Displays admin username in sidebar

- **`assets/js/login.js`**
  - Detects admin users and redirects to admin dashboard
  - Supports `?redirect=admin` URL parameter for forced admin login

### 2. Navigation Logo Fix

#### Updated `assets/css/admin.css`
- Applied CSS filter to make logo visible on dark background
- Added `filter: brightness(0) invert(1)` to convert logo to white
- Added `opacity: 0.95` for subtle transparency

### 3. Daily Reports Feature

#### New API Endpoint (`api/admin/daily_report.php`)
- Returns comprehensive daily sales statistics
- Supports date parameter: `?date=YYYY-MM-DD`
- Requires admin authentication
- Provides data for:
  - Daily revenue and order counts
  - New customer registrations
  - Completed payments
  - 7-day revenue/order trends (for charts)
  - Payment methods breakdown (30 days)
  - Recent orders list
  - Top-selling containers (30 days)
  - Available report dates

#### Updated `pages/admin/admin-dashboard.html`
- Completely rewrote `showDailyReport()` function
- Now fetches data from API instead of loading static HTML
- Dynamically renders report with:
  - Navigation between dates (Previous/Today/Next)
  - Statistics cards
  - Revenue and Orders charts (Chart.js)
  - Recent orders table
  - Top products table
- Added `renderDailyReportCharts()` helper function

## How to Use

### For Administrators

1. **First Time Setup:**
   ```bash
   # Run the database migration
   mysql -u root -p wrsoms < db/migrations/003_add_admin_role.sql
   ```

2. **Login as Admin:**
   - Go to: `http://localhost/WRSOMS/pages/login.html`
   - Username: `admin`
   - Password: `admin123`
   - You'll be automatically redirected to the admin dashboard

3. **Change Default Password:**
   - After first login, update the admin password in the database
   - Use bcrypt to hash new password:
   ```php
   echo password_hash('your_new_password', PASSWORD_DEFAULT);
   ```

### Admin Dashboard Features

- **Dashboard:** Overview of orders, revenue, and metrics
- **Manage Orders:** Batch management and order assignment
- **Order History:** Complete searchable order history
- **Inventory:** Vehicle capacity and container stock management
- **Daily Report:** 
  - View sales statistics for any date
  - Navigate between dates
  - See 7-day revenue trends
  - Track top-selling products

## Security Notes

1. **Change Default Password:** The default admin password (`admin123`) should be changed immediately after first login
2. **Admin Access Only:** All admin pages check authentication and require `is_admin = 1`
3. **Session-Based:** Uses PHP sessions for authentication
4. **Password Hashing:** Uses bcrypt (PASSWORD_DEFAULT) for secure password storage

## Rollback Instructions

If you need to revert the admin authentication:

```sql
-- Remove default admin account
DELETE FROM `accounts` WHERE `username` = 'admin' AND `is_admin` = 1;

-- Remove admin column
ALTER TABLE `accounts` DROP COLUMN `is_admin`;

-- Revert password column length (optional, not recommended)
ALTER TABLE `accounts` MODIFY COLUMN `password` VARCHAR(50) NOT NULL;
```

## Testing Checklist

- [ ] Database migration executed successfully
- [ ] Admin login works with default credentials
- [ ] Non-admin users cannot access admin dashboard
- [ ] Logo is visible in admin sidebar
- [ ] Daily report loads and displays data
- [ ] Date navigation works in daily report
- [ ] Charts render correctly in daily report
- [ ] Admin logout functionality works

## Future Enhancements

- Add admin user management interface
- Implement password change functionality
- Add more granular role permissions
- Create admin activity logs
- Add export functionality for daily reports
