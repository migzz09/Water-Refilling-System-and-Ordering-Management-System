# 💧 WaterWorld Water Station - Ordering Management System

A modern, full-stack water refilling station ordering and management system built with HTML5, CSS3, JavaScript, PHP, and MySQL.

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

## ✨ Features

- 🔐 **User Authentication** - Secure login/registration with OTP email verification
- 🛒 **Order Management** - Place, track, and manage water delivery orders
- 📦 **Batch Processing** - Organize deliveries into batches for efficient routing
- 👥 **Customer Management** - Track customer information and order history
- 📊 **Admin Dashboard** - Comprehensive analytics and order management
- 📱 **Responsive Design** - Works seamlessly on desktop and mobile devices
- 🎨 **Modern UI/UX** - Clean, water-themed design with smooth animations

## 🚀 Quick Start

### Prerequisites

- PHP 8.0 or higher
- MySQL 5.7 or higher
- Composer
- Web server (Apache/Nginx)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/migzz09/WRSOMS.git
   cd WRSOMS
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure database**
   - Create a MySQL database named `wrsoms`
   - Import the schema: `mysql -u root -p wrsoms < db/wrsoms.sql`
   - Update database credentials in `config/connect.php`

4. **Configure email (for OTP)**
   - Copy `config/config.php.example` to `config/config.php`
   - Add your Gmail credentials for OTP functionality

5. **Start your web server**
   - Access the application at `http://localhost/WRSOMS`

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
│   ├── product.html             # Product catalog and ordering page
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