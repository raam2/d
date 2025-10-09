# 📤 Zoho Books Export - Quick Reference Card

## ⚡ Quick Commands

### Export Everything
```bash
php zoho_export.php all
```

### Export Individual Types
```bash
php zoho_export.php contacts     # Customers & Vendors
php zoho_export.php items        # Products
php zoho_export.php sales        # Sales Invoices
php zoho_export.php purchases    # Purchase Invoices
```

### Web Interface
```bash
# Start local server
php -S 0.0.0.0:8080

# Open in browser
http://localhost:8080/zoho_export.php
```

### Test Exports
```bash
./test_exports.sh
```

## 📋 Import Checklist

- [ ] **Step 1:** Export all data using commands above
- [ ] **Step 2:** Log in to https://books.zoho.in/app
- [ ] **Step 3:** Go to Settings → Import Data
- [ ] **Step 4:** Import Contacts (upload `zoho_contacts_*.csv`)
- [ ] **Step 5:** Import Items (upload `zoho_items_*.csv`)
- [ ] **Step 6:** Import Sales Invoices (upload `zoho_sales_invoices_*.csv`)
- [ ] **Step 7:** Import Purchase Invoices (upload `zoho_purchase_invoices_*.csv`)
- [ ] **Step 8:** Verify totals and GST calculations

## 🗂️ File Locations

| File | Purpose |
|------|---------|
| `zoho_export.php` | Main export utility |
| `exports/` | Generated CSV files |
| `ZOHO_IMPORT_GUIDE.md` | Full documentation |
| `ZOHO_EXPORT_README.md` | Technical details |
| `test_exports.sh` | Test suite |

## ⚙️ Configuration

Database credentials are in `config.php`:
```php
'host' => 'localhost',
'port' => '3306',
'user' => 'your_user',
'pass' => 'your_password',
'db'   => 'your_database'
```

## 🎯 What Gets Exported

### Contacts
- ✅ All parties (customers & vendors)
- ✅ GSTIN information
- ✅ Addresses and state codes
- ❌ Email and phone (add manually in Zoho)

### Items
- ✅ Active items only (`is_active = 1`)
- ✅ Product names
- ✅ HSN codes
- ❌ Rates (configure in Zoho)

### Invoices
- ✅ Finalized invoices only (`status = 'final'`)
- ✅ Line items with quantities
- ✅ GST calculations
- ❌ Payments (record in Zoho)

## ⚠️ Common Issues

### "Cannot create file"
```bash
mkdir -p exports && chmod 755 exports
```

### "Database connection failed"
Check `config.php` credentials

### "No data exported"
- Verify data exists: `SELECT COUNT(*) FROM parties;`
- Check filters (active items, final invoices)

### "GSTIN format error" in Zoho
- Validate: `SELECT gstin FROM parties WHERE gstin NOT REGEXP '^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$'`
- Fix invalid GSTINs before re-exporting

## 📊 Expected Results

After import to Zoho Books:
- **Contacts:** Same count as `SELECT COUNT(*) FROM parties`
- **Items:** Same count as `SELECT COUNT(*) FROM items WHERE is_active=1`
- **Sales:** Same count as `SELECT COUNT(*) FROM invoices WHERE inv_type='sale' AND status='final'`
- **Purchases:** Same count as `SELECT COUNT(*) FROM invoices WHERE inv_type='purchase' AND status='final'`

## 🔗 Resources

- **Zoho Books:** https://books.zoho.in/app
- **Zoho Help:** https://www.zoho.com/books/help/
- **Full Guide:** [ZOHO_IMPORT_GUIDE.md](ZOHO_IMPORT_GUIDE.md)

## 💡 Tips

1. **Test first:** Export a few records and test import
2. **Backup:** Zoho allows data export, back up before mass import
3. **Parallel run:** Keep both systems for 1-2 months
4. **Tax setup:** Configure GST rates in Zoho before importing
5. **Clean data:** Fix invalid GSTINs in source database first

## 🆘 Need Help?

1. Read [ZOHO_IMPORT_GUIDE.md](ZOHO_IMPORT_GUIDE.md)
2. Check Zoho Books import error logs
3. Verify source data in database
4. Test with small sample first

---

**Last Updated:** 2025
**Version:** 1.0
