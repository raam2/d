# Zoho Books Export Utility

This utility helps you export data from your GST accounting system to Zoho Books.

## Quick Start

### Web Interface
1. Open `zoho_export.php` in your browser
2. Click the export buttons for each data type
3. Download the generated CSV files from `/exports/` directory

### Command Line
```bash
# Export everything
php zoho_export.php all

# Export specific data types
php zoho_export.php contacts
php zoho_export.php items
php zoho_export.php sales
php zoho_export.php purchases
```

## What Gets Exported?

### 1. Contacts (Parties)
- **Source:** `parties` table
- **Output:** `zoho_contacts_*.csv`
- **Includes:**
  - Customers and Vendors
  - GSTIN information
  - Billing addresses
  - State information for GST compliance

### 2. Items (Products)
- **Source:** `items` table
- **Output:** `zoho_items_*.csv`
- **Includes:**
  - Active items only
  - Product names (canonical_name)
  - HSN codes
  - SKU (auto-generated as ITEM-{id})

### 3. Sales Invoices
- **Source:** `invoices` + `invoice_items` tables
- **Output:** `zoho_sales_invoices_*.csv`
- **Includes:**
  - Finalized sales invoices only
  - Line item details
  - GST calculations (CGST, SGST, IGST)

### 4. Purchase Invoices
- **Source:** `invoices` + `invoice_items` tables
- **Output:** `zoho_purchase_invoices_*.csv`
- **Includes:**
  - Finalized purchase invoices only
  - Vendor information
  - ITC eligibility flags

## Import to Zoho Books

After exporting, follow these steps:

1. **Log in to Zoho Books:** https://books.zoho.in/app
2. **Go to Settings → Import Data**
3. **Import in this order:**
   - Contacts FIRST (required for invoices)
   - Items SECOND
   - Sales Invoices THIRD
   - Purchase Invoices LAST

4. **Review and verify:**
   - Check import success rates
   - Fix any errors
   - Verify tax calculations

See [ZOHO_IMPORT_GUIDE.md](ZOHO_IMPORT_GUIDE.md) for detailed instructions.

## Data Mapping

The export script maps your database fields to Zoho Books compatible format:

| Your Field | Zoho Field | Notes |
|------------|------------|-------|
| `parties.name` | Contact Name | Primary identifier |
| `parties.gstin` | GSTIN | Must match Indian GSTIN format |
| `items.canonical_name` | Item Name | Product name |
| `items.hsn` | HSN/SAC | For tax classification |
| `invoices.invoice_no` | Invoice Number | Must be unique |

## Requirements

- PHP 7.4 or higher
- PDO MySQL extension
- Write access to `/exports/` directory
- Database connection configured in `config.php` and `db.php`

## File Locations

- **Export Script:** `zoho_export.php`
- **Output Directory:** `/exports/`
- **Import Guide:** `ZOHO_IMPORT_GUIDE.md`
- **Database Components:** `zoho_export_components.sql` (for integrating into main app)

## Troubleshooting

### "Cannot create file" error
```bash
# Ensure exports directory exists and is writable
mkdir -p exports
chmod 755 exports
```

### "Database connection failed"
- Check credentials in `config.php`
- Verify database server is running
- Test connection: `php -r "require 'db.php'; db();"`

### Empty export files
- Verify data exists in database
- Check filters (e.g., `is_active = 1`, `status = 'final'`)
- Run SQL queries manually to debug

### GSTIN format errors in Zoho
- The export script validates GSTIN format
- Check source data: `SELECT gstin FROM parties WHERE gstin IS NOT NULL`
- Fix invalid GSTINs before re-exporting

## Customization

You can modify the export script to:
- Include/exclude specific fields
- Change CSV headers
- Add custom data transformations
- Filter by date ranges or other criteria

Edit `zoho_export.php` functions:
- `exportContacts()` - Customize contact export
- `exportItems()` - Customize item export  
- `exportInvoices()` - Customize invoice export

## Security Notes

- Export files may contain sensitive business data
- Store exports securely
- Don't commit CSV files to version control (.gitignore is configured)
- Delete old exports after successful import
- Use HTTPS for web-based exports

## Support

For issues:
1. Check the [ZOHO_IMPORT_GUIDE.md](ZOHO_IMPORT_GUIDE.md)
2. Review Zoho Books import documentation
3. Verify source data in database
4. Test with small sample exports first

## Database Integration

To add export functionality to your main app:

```bash
# Import the database components
mysql -u your_user -p your_database < zoho_export_components.sql
```

This adds a "Zoho Books Export" page to your application with:
- Export statistics dashboard
- Step-by-step instructions
- Quick export buttons
- Links to documentation
