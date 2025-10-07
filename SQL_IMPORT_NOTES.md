# SQL Import Instructions

## Issue with Generated Columns

The `gst_accounting_portable.sql` file contains INSERT statements that include values for MySQL generated columns. This causes import errors in MySQL 8.0+.

### Affected Tables

1. **gst_rates** - has a generated column `total_rate`
2. **invoice_items** - has multiple generated columns:
   - discount_amount
   - taxable_amount
   - cgst_amount
   - sgst_amount
   - igst_amount
   - line_total

### Solution

When importing the full database, you need to either:

**Option 1: Import Schema Only** (Recommended for testing)
```bash
# Import only the first 172 lines which include core tables (Pages, CSS_Files, JS_Files)
head -172 gst_accounting_portable.sql > core_schema.sql
mysql -u gstwork -p'gstwork@123' gst_notebook_lm < core_schema.sql
```

**Option 2: Fix INSERT Statements**

Modify the INSERT statements to exclude generated columns:

For `gst_rates`:
```sql
-- Change from:
INSERT INTO `gst_rates` VALUES (1,0.00,0.00,0.00,0.00);

-- To:
INSERT INTO `gst_rates` (id, cgst, sgst, igst) VALUES (1,0.00,0.00,0.00);
```

For `invoice_items`:
```sql
-- Change from:
INSERT INTO `invoice_items` VALUES (1,116,279,'...', ..., all 20 columns);

-- To:
INSERT INTO `invoice_items` (id, invoice_id, item_id, description, description_en, hsn, quantity, rate, discount_percent, cgst_rate, sgst_rate, igst_rate, itc_eligible, is_prepackaged_labelled) 
VALUES (1,116,279,'...', ..., only 14 non-generated columns);
```

**Option 3: Use MySQL 5.7** (if available)

MySQL 5.7 handles INSERT statements with generated columns differently and may accept the current format.

### Database Fix for page_manager

After importing, run this SQL to fix the e() function redeclaration issue:

```sql
UPDATE Pages 
SET code = REPLACE(code, 
'// Helper function for rendering safe HTML
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, \'UTF-8\');
}',
'// Helper function e() is already defined in index.php')
WHERE name = 'page_manager';
```

### Quick Setup for Development

```bash
# 1. Create database and user
mysql -u root -p << 'EOF'
CREATE DATABASE gst_notebook_lm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'gstwork'@'localhost' IDENTIFIED BY 'gstwork@123';
GRANT ALL PRIVILEGES ON gst_notebook_lm.* TO 'gstwork'@'localhost';
FLUSH PRIVILEGES;
EOF

# 2. Import core schema (Pages, CSS, JS)
head -172 gst_accounting_portable.sql > /tmp/core_schema.sql
mysql -u gstwork -p'gstwork@123' gst_notebook_lm < /tmp/core_schema.sql

# 3. Fix page_manager function
mysql -u gstwork -p'gstwork@123' gst_notebook_lm -e "
UPDATE Pages 
SET code = REPLACE(code, '// Helper function for rendering safe HTML\nfunction e(\$string) {\n    return htmlspecialchars(\$string, ENT_QUOTES, \'UTF-8\');\n}', '// Helper function e() is already defined in index.php')
WHERE name = 'page_manager';
"

# 4. Start PHP server
php -S localhost:8000 index.php
```

### Testing

After setup, access the application:
- Dashboard: http://localhost:8000/
- Content Manager: http://localhost:8000/?page=page_manager

All pages are served from the database with a dark theme UI.
