# Water Refilling Station Ordering Management System (WRSOMS)

A web-based ordering and management system for water refilling stations.

## About This Project

This is a school project for our Web Development course. It allows customers to order water online and helps the admin manage orders and deliveries.

## Features

- Customer registration and login
- Email verification (OTP)
- Browse and order water containers
- Track order status
- Admin dashboard for managing orders
- Delivery scheduling and batch management

## Technologies Used

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP
- **Database:** MySQL
- **Email:** PHPMailer library

## Setup Instructions

### Requirements

- XAMPP (includes Apache, PHP, and MySQL)
- Web browser

### Installation

1. **Download and install XAMPP**
   - Get it from [https://www.apachefriends.org](https://www.apachefriends.org)

2. **Copy project to htdocs**
   - Place the WRSOMS folder in `C:\xampp\htdocs\`

3. **Import the database**
   - Open phpMyAdmin at `http://localhost/phpmyadmin`
   - Create a new database named `wrsoms`
   - Import the file `db/wrsoms.sql`

4. **Configure database connection**
   - Open `config/connect.php`
   - Update the database credentials if needed (default is root with no password)

5. **Start XAMPP**
   - Start Apache and MySQL
   - Open browser and go to `http://localhost/WRSOMS`


## Project Structure

```
WRSOMS/
├── index.html          # Main homepage
├── api/                # PHP backend files
│   ├── auth/          # Login, register, OTP verification
│   ├── admin/         # Admin operations
│   ├── common/        # Shared utilities
│   └── orders/        # Order management
├── assets/            # CSS, JavaScript, and images
│   ├── css/          # Stylesheets
│   ├── js/           # JavaScript files
│   └── images/       # Images and logos
├── config/            # Database configuration
├── db/                # Database SQL file
├── pages/             # HTML pages
│   ├── login.html
│   ├── register.html
│   ├── product.html
│   └── admin/        # Admin pages
├── uploads/           # User uploaded files
├── vendor/            # PHPMailer library
└── README.md          # This file
```

## Usage

### Customer Side
1. Register an account
2. Verify email with OTP code
3. Login and browse products
4. Add items to cart and checkout
5. Track your order status

### Admin Side
1. Login at `/pages/admin/admin.html`
2. View dashboard statistics
3. Manage customer orders
4. Create delivery batches
5. Generate daily reports

## Credits

Developed by: [Your Name/Group Name]  
Course: Web Development  
School Year: 2024-2025