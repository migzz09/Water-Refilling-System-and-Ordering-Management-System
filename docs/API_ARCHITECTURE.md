# WRSOMS - API-Based Architecture

## 🏗️ New Architecture Overview

Converting from **server-side rendering** to **client-side application with REST API**.

### Before (Server-Side Rendering)
```
PHP Files → Generate HTML → Send to Browser
- PHP handles both logic AND presentation
- Page refreshes on every action
- Mixed PHP/HTML code
```

### After (API-Based Architecture)
```
HTML Pages → JavaScript → API Calls → PHP API → Database
- HTML for structure
- CSS for styling
- JavaScript for interactivity
- PHP only for API endpoints (JSON responses)
```

---

## 📁 New Directory Structure

```
WRSOMS/
├── index.html ⭐ (HTML files - frontend)
├── pages/
│   ├── login.html ⭐
│   ├── register.html ⭐
│   ├── product.html ⭐
│   └── ... (all HTML)
│
├── api/ ⭐ NEW - PHP endpoints
│   ├── auth/
│   │   ├── login.php (POST)
│   │   ├── register.php (POST)
│   │   ├── verify-otp.php (POST)
│   │   └── logout.php (POST)
│   ├── orders/
│   │   ├── create.php (POST)
│   │   ├── track.php (GET)
│   │   ├── list.php (GET)
│   │   └── update.php (PUT)
│   ├── admin/
│   │   ├── dashboard.php (GET)
│   │   ├── reports.php (GET)
│   │   └── inventory.php (GET/POST)
│   └── common/
│       ├── session.php (GET - check session)
│       └── cities.php (GET - get NCR cities)
│
├── assets/
│   ├── css/ (stylesheets)
│   ├── js/ (JavaScript - now with API calls)
│   └── images/
│
├── config/ (database & settings)
└── vendor/ (PHPMailer)
```

---

## 🔄 Conversion Process

### 1. HTML Pages (Frontend)
- **Pure HTML** structure
- Load CSS and JavaScript
- No PHP code in HTML files
- JavaScript handles all dynamic content

### 2. PHP API Endpoints (Backend)
- **RESTful API** design
- Return JSON responses
- Handle authentication
- Database operations
- Business logic

### 3. JavaScript (Glue Layer)
- Fetch data from API endpoints
- Update DOM dynamically
- Handle form submissions
- Manage state

---

## 🌐 API Endpoint Structure

### Response Format
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation successful",
  "errors": []
}
```

### Common HTTP Methods
- **GET** - Retrieve data
- **POST** - Create new data
- **PUT/PATCH** - Update existing data
- **DELETE** - Remove data

### Authentication
- Session-based authentication
- Check session on protected endpoints
- Return 401 for unauthorized access

---

## 📋 API Endpoints List

### Authentication (`/api/auth/`)
| Endpoint | Method | Purpose | Auth Required |
|----------|--------|---------|---------------|
| `/api/auth/login.php` | POST | User login | No |
| `/api/auth/register.php` | POST | User registration | No |
| `/api/auth/verify-otp.php` | POST | Verify OTP | No |
| `/api/auth/logout.php` | POST | User logout | Yes |
| `/api/auth/session.php` | GET | Check session status | No |

### Orders (`/api/orders/`)
| Endpoint | Method | Purpose | Auth Required |
|----------|--------|---------|---------------|
| `/api/orders/create.php` | POST | Place new order | Yes |
| `/api/orders/track.php` | GET | Track order by reference | No |
| `/api/orders/list.php` | GET | Get user's orders | Yes |
| `/api/orders/update.php` | PUT | Update order status | Yes (Admin) |

### Admin (`/api/admin/`)
| Endpoint | Method | Purpose | Auth Required |
|----------|--------|---------|---------------|
| `/api/admin/dashboard.php` | GET | Dashboard stats | Yes (Admin) |
| `/api/admin/reports.php` | GET | Daily reports | Yes (Admin) |
| `/api/admin/inventory.php` | GET/POST | Inventory management | Yes (Admin) |
| `/api/admin/orders.php` | GET | Manage all orders | Yes (Admin) |

### Utilities (`/api/common/`)
| Endpoint | Method | Purpose | Auth Required |
|----------|--------|---------|---------------|
| `/api/common/cities.php` | GET | Get NCR cities/barangays | No |
| `/api/common/containers.php` | GET | Get container types | No |

---

## 🔧 Implementation Example

### Old Way (order_placement.php)
```php
<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT ...");
// ... database logic ...
?>
<!DOCTYPE html>
<html>
  <!-- HTML mixed with PHP -->
</html>
```

### New Way

**product.html** (Frontend)
```html
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../assets/css/order_placement.css">
</head>
<body>
    <div id="order-form">
        <!-- Pure HTML -->
    </div>
    <script src="../assets/js/order_placement.js"></script>
</body>
</html>
```

**assets/js/order_placement.js** (Frontend Logic)
```javascript
// Check authentication
fetch('/WRSOMS/api/auth/session.php')
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            window.location.href = 'login.html';
        }
    });

// Submit order
async function placeOrder(orderData) {
    const response = await fetch('/WRSOMS/api/orders/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(orderData)
    });
    const result = await response.json();
    return result;
}
```

**api/orders/create.php** (Backend API)
```php
<?php
session_start();
require_once '../../config/connect.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['customer_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    http_response_code(401);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate and process
try {
    // Database operations
    $stmt = $pdo->prepare("INSERT INTO orders ...");
    
    echo json_encode([
        'success' => true,
        'data' => ['order_id' => $orderId],
        'message' => 'Order placed successfully'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    http_response_code(500);
}
```

---

## ✅ Benefits of API Architecture

### 1. Separation of Concerns
- ✅ Frontend (HTML/CSS/JS) separate from backend (PHP)
- ✅ Easier to maintain and debug
- ✅ Clear responsibilities

### 2. Reusability
- ✅ APIs can be used by multiple frontends (web, mobile app)
- ✅ Easy to build mobile app later
- ✅ Third-party integrations possible

### 3. Performance
- ✅ Faster page loads (static HTML)
- ✅ Only load data when needed
- ✅ Better caching strategies

### 4. Scalability
- ✅ Frontend and backend can scale independently
- ✅ Can add CDN for static files
- ✅ API can be deployed separately

### 5. Modern Development
- ✅ Industry-standard approach
- ✅ Better for team collaboration
- ✅ Easier testing (APIs can be tested independently)

---

## 🚀 Migration Plan

### Phase 1: Setup ✅
- [x] Create api/ directory
- [ ] Create API base structure
- [ ] Create common utilities

### Phase 2: Authentication APIs
- [ ] Convert login.php → login.html + api/auth/login.php
- [ ] Convert register.php → register.html + api/auth/register.php
- [ ] Convert verify_otp.php → verify-otp.html + api/auth/verify-otp.php
- [ ] Create session check API

### Phase 3: Order APIs
- [x] Convert order_placement.php → product.html + api
- [ ] Convert order_tracking.php → order-tracking.html + api
- [ ] Create order management APIs

### Phase 4: Admin APIs
- [ ] Convert admin_dashboard.php → admin-dashboard.html + api
- [ ] Convert inventory.php → inventory.html + api
- [ ] Create admin management APIs

### Phase 5: Update JavaScript
- [ ] Add API helper functions
- [ ] Update all JS files to use fetch/axios
- [ ] Add error handling
- [ ] Add loading states

---

## 📝 Notes

### CORS Considerations
- Same origin (localhost) - no CORS issues
- If deploying separately, configure CORS headers

### Security
- Validate all inputs
- Use prepared statements
- Check authentication on every API call
- Sanitize outputs
- HTTPS in production

### Error Handling
- Consistent error response format
- Appropriate HTTP status codes
- User-friendly error messages

---

**Status:** Architecture defined, ready for implementation  
**Next Step:** Start converting authentication pages
