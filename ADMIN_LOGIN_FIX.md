# Admin Login Issue - Fix Guide
**Date:** November 4, 2025  
**Issue:** Users not routing correctly - regular users sent to admin, admins blocked from admin dashboard

---

## Root Causes Identified

### 1. **Login Routing Logic Flaw** ✅ FIXED
**Problem:** The login.js was redirecting users to admin dashboard based on URL parameter `?redirect=admin` instead of actual `is_admin` permission.

**Before (Buggy):**
```javascript
if (isAdmin || redirect === 'admin') {
    redirectUrl = '/WRSOMS/pages/admin/admin-dashboard.html';
}
```

This meant **anyone** could access admin by adding `?redirect=admin` to login URL!

**After (Fixed):**
```javascript
if (isAdmin) {
    redirectUrl = '/WRSOMS/pages/admin/admin-dashboard.html';
} else {
    redirectUrl = '/WRSOMS/index.html';
}
```

Now routing is based ONLY on actual `is_admin` value from database.

---

### 2. **Database `is_admin` Column Missing or Incorrect**
**Problem:** Your database may not have the `is_admin` column, or it's not set correctly for admin users.

---

## How to Fix

### Step 1: Use the Debug Tool

Open this page in your browser:
```
http://localhost/WRSOMS/debug-admin.html
```

This tool will help you:
1. **Check Current Session** - See if you're logged in and what `is_admin` value you have
2. **List Admin Users** - Show SQL query to check all users
3. **Test Login** - Test login with any credentials and see the response
4. **Database Fix Tools** - Get SQL queries to fix admin access

---

### Step 2: Fix the Database

Open phpMyAdmin: `http://localhost/phpmyadmin`

#### Option A: Make Existing User Admin

If you have a user that should be admin:

```sql
UPDATE accounts 
SET is_admin = 1 
WHERE username = 'your_admin_username';
```

Replace `your_admin_username` with the actual username.

#### Option B: Create New Admin User

If you need to create a fresh admin account:

```sql
INSERT INTO accounts (
    username, 
    email, 
    password, 
    first_name, 
    last_name, 
    is_admin, 
    is_verified, 
    created_at
) VALUES (
    'admin',
    'admin@waterworld.ph',
    'admin123',
    'System',
    'Administrator',
    1,
    1,
    NOW()
);
```

**⚠️ IMPORTANT:** This creates an admin account with password `admin123`. Change it after first login!

#### Option C: Fix Missing Column

If the `is_admin` column doesn't exist:

```sql
ALTER TABLE accounts 
ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER is_verified;
```

---

### Step 3: Verify the Fix

1. **Check the database:**
```sql
SELECT customer_id, username, email, is_admin, is_verified 
FROM accounts 
ORDER BY is_admin DESC;
```

You should see:
- At least one user with `is_admin = 1`
- Other users with `is_admin = 0`

2. **Test Login:**
   - Login with admin credentials
   - Check browser console for debug logs:
     ```
     is_admin value: 1
     isAdmin check result: true
     User IS admin - redirecting to admin dashboard
     ```
   - You should be redirected to `/WRSOMS/pages/admin/admin-dashboard.html`

3. **Test Regular User:**
   - Login with regular user credentials
   - Check console:
     ```
     is_admin value: 0
     User is NOT admin - redirecting to main page
     ```
   - You should be redirected to `/WRSOMS/index.html`

---

## Quick Fix Checklist

- [x] ✅ Fixed login.js routing logic (removed URL parameter bypass)
- [ ] ⏳ Check if `is_admin` column exists in `accounts` table
- [ ] ⏳ Set at least one user to `is_admin = 1`
- [ ] ⏳ Test admin login - should go to admin dashboard
- [ ] ⏳ Test regular user login - should go to main page
- [ ] ⏳ Verify admin dashboard loads (not "Admin access required" error)

---

## Files Modified

1. **assets/js/login.js** (Line 53-68)
   - Removed `redirect` URL parameter check
   - Changed routing to use ONLY `is_admin` value
   - Fixed redirect paths to use absolute paths

2. **Created: debug-admin.html**
   - Interactive debug tool
   - Shows session data
   - Tests login flow
   - Provides SQL fix queries

3. **Created: db/migrations/003_fix_admin_authentication.sql**
   - SQL migration file
   - Adds `is_admin` column if missing
   - Sets admin user
   - Verification queries

---

## Testing Scenarios

### Scenario 1: Admin Login
```
Username: admin
Password: admin123
Expected: Redirect to /WRSOMS/pages/admin/admin-dashboard.html
Console: "User IS admin - redirecting to admin dashboard"
```

### Scenario 2: Regular User Login
```
Username: customer1
Password: password123
Expected: Redirect to /WRSOMS/index.html
Console: "User is NOT admin - redirecting to main page"
```

### Scenario 3: Invalid Credentials
```
Username: fake
Password: wrong
Expected: Error message "Invalid username, password, or account not verified."
```

---

## Common Issues & Solutions

### Issue: "Admin access required" when logging in as admin

**Cause:** Session `is_admin` not being saved properly

**Solution:**
1. Check api/auth/login.php line 89:
   ```php
   $_SESSION['is_admin'] = (int)$user['is_admin'];
   ```
2. Verify database has `is_admin = 1` for that user
3. Clear browser cookies and try again

---

### Issue: Regular user can access admin dashboard

**Cause:** Database has wrong `is_admin` value

**Solution:**
```sql
UPDATE accounts 
SET is_admin = 0 
WHERE username = 'regular_user';
```

---

### Issue: No users have admin access

**Solution:** Run this to make 'admin' user an admin:
```sql
UPDATE accounts SET is_admin = 1 WHERE username = 'admin';
```

Or create new admin user (see Step 2, Option B above)

---

## Migration Files

Apply migrations in order:

1. **001_add_checkouts.sql** - Checkouts table (already applied)
2. **002_fix_customer_feedback_reference_id.sql** - Feedback foreign key fix
3. **003_fix_admin_authentication.sql** - Admin column and user setup ⚠️ **Apply this now**

---

## Next Steps

1. Open `http://localhost/WRSOMS/debug-admin.html`
2. Click "Check Session" to see current login status
3. Run the SQL queries from "Show Fix SQL Queries"
4. Test login with admin and regular users
5. Verify routing works correctly

---

## Support

If issues persist:
1. Check browser console for errors
2. Check PHP error logs in XAMPP
3. Use debug-admin.html to diagnose
4. Verify database structure matches expected schema

---

**Status:** Ready to test after applying database fixes!
