# Security Best Practices for Zoho Export

## 🔒 Security Considerations

### Database Credentials

The `config.php` file contains database credentials. Follow these best practices:

1. **Never commit real credentials to version control**
   ```bash
   # Add to .gitignore
   config.php
   ```

2. **Use environment variables instead**
   ```php
   // Recommended approach
   'user' => getenv('DB_USER'),
   'pass' => getenv('DB_PASS'),
   ```

3. **Set environment variables before running exports**
   ```bash
   export DB_HOST=localhost
   export DB_PORT=3306
   export DB_USER=your_user
   export DB_PASS=your_password
   export DB_NAME=your_database
   ```

### Export File Security

Export CSV files contain sensitive business data:

1. **Protect export directory**
   ```bash
   chmod 700 exports/
   ```

2. **Delete exports after successful import**
   ```bash
   rm exports/zoho_*.csv
   ```

3. **Don't commit export files** (already in `.gitignore`)

4. **Use HTTPS for web-based exports**
   - Never run exports over unencrypted HTTP in production
   - Use SSL/TLS certificates

### Access Control

1. **Restrict database user permissions**
   ```sql
   -- Create read-only user for exports
   CREATE USER 'export_user'@'localhost' IDENTIFIED BY 'secure_password';
   GRANT SELECT ON your_database.parties TO 'export_user'@'localhost';
   GRANT SELECT ON your_database.items TO 'export_user'@'localhost';
   GRANT SELECT ON your_database.invoices TO 'export_user'@'localhost';
   GRANT SELECT ON your_database.invoice_items TO 'export_user'@'localhost';
   ```

2. **Limit web access**
   ```apache
   # .htaccess for Apache
   <Files "zoho_export.php">
       Require ip 127.0.0.1
       Require ip YOUR_OFFICE_IP
   </Files>
   ```

3. **Use authentication**
   ```php
   // Add to top of zoho_export.php for web access
   session_start();
   if (!isset($_SESSION['authenticated'])) {
       header('HTTP/1.1 403 Forbidden');
       exit('Access denied');
   }
   ```

### Data Privacy

1. **GSTIN and PII**
   - Export files contain GSTIN numbers (business tax IDs)
   - Some party records may include personal information
   - Handle according to data protection regulations

2. **Secure transmission**
   - Upload to Zoho Books over HTTPS only
   - Don't email export files unencrypted
   - Use secure file transfer methods

3. **Data retention**
   - Delete export files after successful import
   - Don't store exports on public servers
   - Keep backups encrypted

### Zoho Books Account Security

1. **Enable 2FA** on your Zoho Books account
2. **Use strong passwords**
3. **Limit user permissions** in Zoho Books
4. **Monitor import logs** for unauthorized access
5. **Regularly review** access logs

### Production Checklist

Before running exports in production:

- [ ] Remove hardcoded credentials from config.php
- [ ] Use environment variables for all sensitive data
- [ ] Verify export directory permissions (700 or 750)
- [ ] Ensure `.gitignore` excludes exports/ and config.php
- [ ] Use HTTPS for web-based exports
- [ ] Restrict IP access to export script
- [ ] Use read-only database user for exports
- [ ] Have incident response plan for data breaches
- [ ] Document who has access to export functionality
- [ ] Enable audit logging for export operations

### Recommended config.php Template

```php
<?php // config.php - SECURE VERSION

$ENV = getenv('APP_ENV') ?: 'local';

// Do NOT hardcode credentials
// Set via environment variables
$config = [
    'local' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'user' => getenv('DB_USER'),
        'pass' => getenv('DB_PASS'),
        'db'   => getenv('DB_NAME'),
        'charset' => 'utf8mb4',
    ],
    'production' => [
        'host' => getenv('DB_HOST'),
        'port' => getenv('DB_PORT') ?: '3306',
        'user' => getenv('DB_USER'),
        'pass' => getenv('DB_PASS'),
        'db'   => getenv('DB_NAME'),
        'charset' => 'utf8mb4',
    ],
];

// Validate required variables
$required = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'];
foreach ($required as $var) {
    if (!getenv($var)) {
        throw new RuntimeException("Missing required environment variable: $var");
    }
}

if (!isset($config[$ENV])) {
    throw new RuntimeException("Unknown APP_ENV: {$ENV}");
}
```

### Setting Environment Variables

**Linux/macOS:**
```bash
# Create .env file (add to .gitignore!)
cat > .env << 'EOF'
export DB_HOST=localhost
export DB_PORT=3306
export DB_USER=your_user
export DB_PASS=your_secure_password
export DB_NAME=your_database
export APP_ENV=production
EOF

# Load before running exports
source .env
php zoho_export.php all
```

**Windows:**
```cmd
set DB_HOST=localhost
set DB_PORT=3306
set DB_USER=your_user
set DB_PASS=your_secure_password
set DB_NAME=your_database
set APP_ENV=production

php zoho_export.php all
```

### Incident Response

If export files are exposed:

1. **Immediately delete** exposed files
2. **Revoke access** to compromised systems
3. **Change database passwords**
4. **Review access logs** for unauthorized activity
5. **Notify stakeholders** if required by regulations
6. **Update security measures** to prevent recurrence

### Compliance

Depending on your jurisdiction and business:

- **GDPR** (EU): Personal data in exports must be protected
- **GST Law** (India): Business records must be kept secure
- **SOC 2**: Export operations may need audit trails
- **ISO 27001**: Information security management

### Questions?

For security concerns or questions:
1. Review Zoho Books security documentation
2. Consult with security professionals
3. Follow industry best practices
4. Implement least-privilege access

---

**Remember:** Security is an ongoing process, not a one-time setup. Regularly review and update your security measures.
