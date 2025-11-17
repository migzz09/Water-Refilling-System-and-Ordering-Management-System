# Feedback System - Complete Flow Documentation

## Database Structure (3NF Normalized)

### Tables
1. **feedback_category**
   - `category_id` (PK, AUTO_INCREMENT)
   - `category_name` (UNIQUE)
   - `created_at`
   
   Categories: Product, Service, Website, Other

2. **customer_feedback**
   - `feedback_id` (PK)
   - `customer_id` (FK)
   - `category_id` (FK → feedback_category)
   - `rating` (1-5)
   - `feedback_text` (full text including category/subject/message)
   - `feedback_date`

## Complete Data Flow

### 1. Customer Submits Feedback (Frontend)
**File:** `pages/feedback.html`

```html
<!-- Category cards with data-category attributes -->
<div class="category-card" data-category="product">Product Quality</div>
<div class="category-card" data-category="service">Service</div>
<div class="category-card" data-category="website">Website</div>
<div class="category-card" data-category="other">Other</div>
```

**JavaScript:**
- User clicks category card → stores `data-category` value
- Form submits with: `{ rating, category, subject, message }`
- Sends to: `/api/common/submit_feedback.php`

### 2. Backend Processes Submission
**File:** `api/common/submit_feedback.php`

**Logic:**
```php
// 1. Lookup category_id from feedback_category table
SELECT category_id FROM feedback_category 
WHERE LOWER(category_name) = LOWER('service')

// 2. If category doesn't exist, create it
INSERT INTO feedback_category (category_name) VALUES ('Service')

// 3. Insert feedback with normalized category_id
INSERT INTO customer_feedback (
    customer_id, 
    category_id,  // ← Foreign key reference
    rating, 
    feedback_text
) VALUES (?, ?, ?, ?)
```

**Note:** `feedback_text` still contains full format for backward compatibility:
```
Category: Service
Subject: Fast delivery
Message: The water arrived quickly...
```

### 3. Admin Views Feedback
**File:** `api/admin/feedback.php`

**Query with JOIN:**
```sql
SELECT 
    cf.feedback_id,
    cf.rating,
    cf.feedback_text,
    fc.category_name,  -- ← From joined table
    c.first_name,
    c.last_name,
    a.username
FROM customer_feedback cf
LEFT JOIN feedback_category fc ON cf.category_id = fc.category_id
LEFT JOIN customers c ON cf.customer_id = c.customer_id
LEFT JOIN accounts a ON a.customer_id = c.customer_id
```

**Processing:**
- Uses `fc.category_name` from database (primary source)
- Falls back to parsing `feedback_text` if `category_name` is NULL (old data)
- Extracts subject and message from `feedback_text`
- Returns structured data to admin dashboard

### 4. Admin Dashboard Display
**File:** `pages/admin/admin-dashboard.html`

**JavaScript Function:** `renderFeedback()`
```javascript
// Displays:
- Customer name and username
- Category badge (from database)
- Rating stars
- Subject line
- Message text
- Date
```

## Migration Process

**File:** `db/migrations/003_add_feedback_category_table.sql`

**Steps:**
1. Create `feedback_category` table with 4 categories
2. Add `category_id` column to `customer_feedback`
3. Add foreign key constraint (ON DELETE SET NULL)
4. Migrate existing feedback by parsing `feedback_text`
5. Map old "delivery"/"delivery service" → "Service"
6. Create index for performance

## Data Consistency

### Frontend → Database Mapping
| Frontend Card | data-category | Database Category |
|--------------|---------------|-------------------|
| Product Quality | "product" | Product |
| Service | "service" | Service |
| Website | "website" | Website |
| Other | "other" | Other |

### API Behavior
- **Submit:** Looks up category by name (case-insensitive)
- **Submit:** Creates category if doesn't exist
- **Retrieve:** Joins `feedback_category` table
- **Retrieve:** Falls back to text parsing for old data

### Backward Compatibility
✓ Old feedback without `category_id` displays correctly
✓ `feedback_text` format preserved for legacy systems
✓ Foreign key constraint allows NULL (won't break on delete)
✓ Admin API handles missing category gracefully

## Testing Checklist

- [ ] Run migration: `003_add_feedback_category_table.sql`
- [ ] Run test: `test_feedback_flow.sql`
- [ ] Submit new feedback from customer page
- [ ] Verify category_id inserted correctly
- [ ] Check admin dashboard displays category
- [ ] Test search functionality includes categories
- [ ] Verify old feedback (if any) still displays

## Benefits of 3NF Normalization

1. **Data Integrity:** Categories managed in one place
2. **Consistency:** No typos or variations (e.g., "Delivery" vs "delivery service")
3. **Flexibility:** Easy to add/rename categories without touching feedback data
4. **Performance:** Indexed category lookups
5. **Reporting:** Easy to aggregate by category
6. **Maintenance:** Update category name in one place affects all feedback
