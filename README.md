# 💧 WaterWorld Water Station - Ordering Management System

A modern, full-stack water refilling station ordering and management system built with HTML5, CSS3, JavaScript, PHP, and MySQL.


## Design System

- **Framework**: Modern HTML5/CSS3/JavaScript
- **Icons**: Font Awesome 4.7.0
- **Colors**: Darker water-themed palette
- **Typography**: Inter (body), Poppins (headings)
- **Theme**: Professional teal and aqua tones

## 📁 Project Structure

```
WRSOMS/
├── 📄 index.html                   # Homepage entry point
├── 📄 README.md                    # This file
│
├── 📁 api/                         # Backend API endpoints (PHP)
│   ├── 📁 auth/                   # Authentication
│   │   ├── login.php             # User login API
│   │   ├── register.php          # User registration API
│   │   ├── verify-otp.php        # OTP verification API
│   │   ├── logout.php            # Logout API
│   │   └── session.php           # Session check API
│   ├── 📁 admin/                  # Admin operations
│   │   └── dashboard.php         # Dashboard stats API
│   ├── 📁 common/                 # Shared utilities
│   │   └── cities.php            # NCR cities & barangays
│   └── 📁 orders/                 # Order management
│       ├── create.php            # Create order API
│       └── track.php             # Track order API
│
├── 📁 assets/                      # Frontend static assets
│   ├── 📁 css/                    # Stylesheets (14 files)
│   │   ├── design-system.css     # Global design tokens & variables
│   │   ├── index.css             # Homepage styles
│   │   ├── admin.css             # Admin panel styles
│   │   ├── register.css          # Registration page styles
│   │   └── ...                   # Other page-specific styles
│   ├── 📁 js/                     # JavaScript files (10 files)
│   │   ├── api-helper.js         # API utility functions
│   │   ├── login.js              # Login page logic
│   │   ├── register.js           # Registration logic
│   │   ├── index.js              # Homepage interactions
│   │   └── ...                   # Other page-specific scripts
│   └── 📁 images/                 # Image assets
│       ├── Water World Facade.jpg # Storefront background
│       └── ww_logo.png           # Company logo
│
├── 📁 config/                      # Configuration files
│   └── connect.php               # Database connection settings
│
├── 📁 docs/                        # Documentation
│   ├── API_ARCHITECTURE.md       # Complete API reference
│   ├── UI_UX_DESIGN.md           # Design system guide
│   ├── CLEANUP_SUMMARY.md        # Cleanup report
│   └── PROJECT_STATUS.md         # Project status & checklist
│
├── 📁 pages/                       # HTML pages (Customer)
│   ├── login.html                # User login page
│   ├── register.html             # User registration page
│   ├── verify-otp.html           # OTP verification page
│   ├── order-placement.html      # Place new order page
│   ├── order-tracking.html       # Track order status page
│   ├── usertransaction-history.html # Order history page
│   └── 📁 admin/                  # Admin pages
│       ├── admin.html            # Admin login
│       ├── admin-dashboard.html  # Admin dashboard
│       ├── daily-report.html     # Daily reports
│       ├── manage-orders.html    # Order management
│       ├── status.html           # Status management
│       └── transaction-history.html # All transactions
│
├── 📁 db/                          # Database files
├── 📁 vendor/                      # Third-party dependencies
└── 📁 php-legacy/                  # Legacy PHP pages (for reference)

## Contributing

This is a private project for WaterWorld Water Station.