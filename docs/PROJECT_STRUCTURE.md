# WRSOMS Project Structure

## 📁 Directory Organization

This document describes the organized structure of the WaterWorld Refilling Station Order Management System.

---

## Root Directory (`/`)

```
WRSOMS/
├── index.html              # Main landing page
├── composer.json           # PHP dependencies
├── README.md              # Project documentation
├── LICENSE                # Project license
└── CONTRIBUTING.md        # Contribution guidelines
```

---

## API Directory (`/api/`)

All backend API endpoints are organized by feature/domain:

### Authentication (`/api/auth/`)
```
api/auth/
├── login.php              # User login endpoint
├── logout.php             # User logout endpoint
├── register.php           # User registration endpoint
├── verify-otp.php         # OTP verification endpoint
├── resend-otp.php         # Resend OTP endpoint
├── session.php            # Session check endpoint
└── profile.php            # User profile endpoint
```

### Orders (`/api/orders/`)
```
api/orders/
├── create.php             # Create new order
├── get_cart.php           # Get shopping cart
├── update_cart.php        # Update cart items
├── track.php              # Track order status
└── transaction_history.php # Get user transaction history
```
**Note:** `transaction_history.php` was previously `usertransaction_history.php` in root directory

### Admin (`/api/admin/`)
```
api/admin/
├── dashboard.php          # Admin dashboard data
├── manage_orders.php      # Order management
├── manage_orders_debug.php # Order debugging
├── order_action.php       # Order actions (approve, reject, etc.)
├── batch_action.php       # Batch operations
├── assign_next_batch.php  # Batch assignment
├── daily_report.php       # Daily reports
└── inventory.php          # Inventory management (NEW)
```
**Note:** `inventory.php` is a new endpoint that fetches from database instead of localStorage

### Common/Shared (`/api/common/`)
```
api/common/
├── containers.php         # Get container types
├── water_types.php        # Get water types
├── order_types.php        # Get order types
├── cities.php             # Get cities list
├── addresses.php          # Get user addresses
├── add_address.php        # Add new address
├── update_address.php     # Update address
├── delete_address.php     # Delete address
└── submit_feedback.php    # Submit customer feedback
```

### Profile Management (`/api/profile/`)
```
api/profile/
├── update.php             # Update profile details
├── upload-photo.php       # Upload profile photo
└── delete-photo.php       # Delete profile photo
```

### Customer (`/api/customer/`)
```
api/customer/
└── update-details.php     # Update customer details
```

### Password Management (`/api/password/`)
```
api/password/
├── send-otp.php           # Send password reset OTP
└── change.php             # Change password
```

---

## Pages Directory (`/pages/`)

All HTML pages organized by user type:

### Customer Pages
```
pages/
├── login.html                      # Login page
├── register.html                   # Registration page
├── verify-otp.html                 # OTP verification page
├── product.html                    # Product selection page
├── checkout.html                   # Checkout page
├── order-tracking.html             # Order tracking page
└── usertransaction-history.html    # Transaction history page
```

### Admin Pages (`/pages/admin/`)
```
pages/admin/
├── admin.html                # Admin portal landing
├── admin-dashboard.html      # Main admin dashboard
├── manage-orders.html        # Order management interface
├── status.html               # Order status management
├── daily-report.html         # Daily reports view
└── transaction-history.html  # Admin transaction view
```

---

## Assets Directory (`/assets/`)

### CSS (`/assets/css/`)
```
assets/css/
├── index.css                     # Homepage styles
├── forms.css                     # Form styles
├── header.css                    # Header/navigation styles
├── register.css                  # Registration page styles
├── product.css                   # Product page styles
├── checkout.css                  # Checkout page styles
├── tracking.css                  # Order tracking styles
├── usertransaction_history.css   # Transaction history styles
├── admin.css                     # Admin portal styles
├── admin_dashboard.css           # Admin dashboard styles
├── manage_orders.css             # Order management styles
├── status.css                    # Status page styles
├── daily_report.css              # Daily report styles
├── inventory.css                 # Inventory page styles (NEW)
└── design-system.css             # Global design system
```

### JavaScript (`/assets/js/`)
```
assets/js/
├── index.js                      # Homepage scripts
├── auth.js                       # Authentication utilities
├── login.js                      # Login page logic
├── register.js                   # Registration logic
├── product.js                    # Product selection logic
├── checkout.js                   # Checkout process
├── usertransaction_history.js    # Transaction history logic
├── api-helper.js                 # API helper functions
├── admin_dashboard.js            # Admin dashboard logic
├── daily_report.js               # Daily report logic
├── inventory.js                  # Inventory management (NEW)
└── status.js                     # Status management
```

### Images (`/assets/images/`)
All image assets for the application

---

## Configuration (`/config/`)

```
config/
├── connect.php           # Database connection (PDO)
├── config.php            # Application configuration
└── config.php.example    # Configuration template
```

---

## Database (`/db/`)

```
db/
├── wrsoms.sql           # Full database schema & data
└── migrations/          # Database migration files
    ├── README.md        # Migration instructions
    ├── 001_add_checkouts.sql
    ├── 002_fix_customer_feedback_reference_id.sql
    └── 003_fix_admin_authentication.sql
```

---

## Tools (`/tools/`)

Development and diagnostic tools:

```
tools/
├── check-admin.php      # Admin diagnostic tool
└── headless-check.js    # Headless browser testing
```
**Note:** `check-admin.php` was previously in root directory

---

## References (`/references/`)

Reference/backup implementations:

```
references/
├── admin_dashboard_reference.php
├── checkout_reference.php
├── get_cart_reference.php
├── index_reference.php
├── product_reference.php
├── register_reference.php
├── update_address_reference.php
├── update_cart_reference.php
├── usertransactionhistory_reference.php
└── verify_otp_reference.php
```

---

## Documentation (`/docs/`)

```
docs/
├── API_ARCHITECTURE.md   # API documentation
├── PROJECT_STATUS.md     # Project status & roadmap
└── UI_UX_DESIGN.md      # UI/UX design guidelines
```

---

## Other Directories

### Vendor (`/vendor/`)
Composer dependencies (auto-generated)

### Uploads (`/uploads/`)
User-uploaded files (profile photos, etc.)

---

## Recent Reorganizations

### Files Moved:

1. **`usertransaction_history.php`**
   - **From:** Root directory
   - **To:** `api/orders/transaction_history.php`
   - **Reason:** Better organization, follows API structure pattern
   - **Updated:** All references in JS and HTML files

2. **`check-admin.php`**
   - **From:** Root directory
   - **To:** `tools/check-admin.php`
   - **Reason:** Diagnostic tool, not part of main app
   - **Access:** `http://localhost/WRSOMS/tools/check-admin.php`

### New Files Created:

1. **`api/admin/inventory.php`**
   - Inventory management API endpoint
   - Fetches from `inventory` table in database
   - Replaces localStorage-based inventory system

---

## Best Practices

### API Endpoints
- All API endpoints return JSON
- Include proper HTTP status codes
- Session-based authentication required
- Use consistent response format: `{success: bool, message: string, data: object}`

### File Naming
- API files: lowercase with underscores (`transaction_history.php`)
- HTML pages: lowercase with hyphens (`usertransaction-history.html`)
- CSS/JS: match their corresponding HTML page names

### Directory Structure
- Group by feature/domain (auth, orders, admin)
- Keep related files together
- Separate public pages from admin pages
- Tools and references in separate directories

---

## Quick Access URLs

### Customer Pages
- Homepage: `http://localhost/WRSOMS/`
- Login: `http://localhost/WRSOMS/pages/login.html`
- Register: `http://localhost/WRSOMS/pages/register.html`
- Products: `http://localhost/WRSOMS/pages/product.html`
- Transaction History: `http://localhost/WRSOMS/pages/usertransaction-history.html`

### Admin Pages
- Admin Dashboard: `http://localhost/WRSOMS/pages/admin/admin-dashboard.html`
- Manage Orders: `http://localhost/WRSOMS/pages/admin/manage-orders.html`

### Tools
- Admin Diagnostic: `http://localhost/WRSOMS/tools/check-admin.php`

---

**Last Updated:** November 5, 2025
**Project:** WaterWorld Refilling Station Order Management System (WRSOMS)
