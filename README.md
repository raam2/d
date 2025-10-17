
# GST India Accounting System
simple readable dark UI . vanilla js .
A complete double-entry accounting module designed specifically for Indian businesses with GST compliance. This system integrates with existing invoice data and provides comprehensive financial reporting. Develop an advanced AI-powered GST accounting billing for gst-registered retail merchant . 
Core Features:
create sale bill on base of date range total amount during month/day time , and gst_rates search from internet on base of invoice date selected .
    GST Compliance - Automated GSTIN validation and tax calculations
billing- Direct csv export with proper voucher formatting
    Self-Learning AI - Continuous improvement from user corrections
AI-OCR reduces manual work by 80%
GSTIN Errors	Compliance issues, audit problems	Real-time format validation & error detection
Tax Calculation Mistakes	State-wise computation errors	Intelligent CGST/SGST vs IGST determination
	No Time-consuming manual entry	Direct csv export with voucher formatting
Inconsistent Accuracy	Human errors eliminate , processing fatigue	Machine learning improves with each correction
## 🎯 Features
Market Need:
    Indian businesses process millions of GST invoices monthly
    Strict GST compliance deadlines require faster processing
    Manual processes cannot scale with growing invoice volumes

4. TECHNOLOGY STACK-
  Learning Algorithm: JSON-based pattern storage for continuous improvement
    GST Validator: Regex patterns for real-time GST rates on hsn validation
    Tax Calculator: State code logic for automated tax determination

Integration:    Local Storage: Secure client-side data persistence
Real-World Applications:State-wise Tax Calculation: Automated CGST/SGST vs IGST determination → 100% accuracy.
Month-end Bulk Processing: 500+ invoices .
8. IMPLEMENTATION IMPACT
Industry Transformation:
    Manual → Automated: Complete elimination of repetitive data entry.
    Error-Prone → Accurate: AI precision replacing human inconsistency.
    Reactive → Proactive: Predictive error detection and prevention.
    Isolated → : Seamless accounting workflow integration.
    Competitive Advantage - Advanced technology adoption.
### 📊 Complete Chart of Accounts
- Indian accounting standard structure
- GST-specific accounts (CGST, SGST, IGST Input/Output)
- Hierarchical account organization
- Asset, Liability, Equity, Income, Expense account types
- Account activation/deactivation

### ⚖️ Double-Entry Bookkeeping
- Automatic debit/credit validation (debits must equal credits)
- Manual journal entry creation with real-time balance checking
- Automated invoice posting to ledger
- Complete audit trail with source document tracking
- Transaction reversal and correction capabilities

### 📈 Financial Reports
- **Trial Balance** - Real-time balance verification with drill-down to ledgers
- **Profit & Loss Statement** - Period-based P&L with previous year comparison
- **Balance Sheet** - Assets = Liabilities + Equity validation
- **Account Ledgers** - Detailed transaction history with running balances

### 🧾 GST Invoice Integration
- Automatic posting of sales invoices to accounting
- Intelligent GST split calculation (CGST/SGST vs IGST based on state)
- State-wise tax determination
- Accounts Receivable automation
- Support for existing invoice/invoice_items tables

### 🏛️ Bank Reconciliation
- Statement vs book balance comparison
- Outstanding items tracking
- Multi-bank account support
- Reconciliation audit trail

### 🎨 User Interface
- Dark theme optimized for reduced eye strain
- Responsive design for mobile/tablet access
- Intuitive navigation and workflow
- No external JavaScript dependencies
- Memory-efficient operation

## 🚀 Installation & Setup

### 1. System Requirements
-no external dependancy . offline single admin user .
Server version: 10.11.11-MariaDB-0+deb12u1 Debian 12
PHP 8.2.28 (cli) (built: Mar 13 2025 18:21:38) (NTS)
Copyright (c) The PHP Group
Zend Engine v4.2.28, Copyright (c) Zend Technologies
    with Zend OPcache v8.2.28, Copyright (c), by Zend Technologies
- Apache web server
- PDO MySQL extension

### 2. Database Configuration
Create or update `con.php` in the project root:

```php
<?php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306'); 
define('DB_NAME', 'gst_accounting');
define('DB_USER', 'gstwork');
define('DB_PASS', 'gstwork@123');
```

## 🔧 Technical Details
everything ready to work continue with Existing Database at /database/ .
UPDATE `gst_rate_rules` (or `gst_hsn_rates`), FROM INTERNET AI . GST billing system** to automatically pick the correct GST rate from your database, based on the **invoice date** and the **HSN code**, while respecting the effective date ranges in your `gst_rate_rules` (or `gst_hsn_rates`).
### Database Schema
use existing ones:

**Existing Tables** (used as-is):
## 🗂 Database Tables Involved
- **`gst_rate_rules`**
  - `hsn_start`, `hsn_end` → HSN range
  - `intra_rate_percent`, `inter_rate_percent`
  - `effective_from`, `effective_to`
- **`gst_hsn_rates`**
  - Simple HSN → GST rate mapping (fallback if no rules exist)

---

## 🔑 PDO Query Logic

### Step 1: Fetch GST Rate by HSN + Invoice Date
```php
function getGstRate(PDO $pdo, string $hsn, string $invoiceDate, string $supplyState, string $orgState): ?array {
    // First try gst_rate_rules with date range
    $sql = "
        SELECT intra_rate_percent, inter_rate_percent
        FROM gst_rate_rules
        WHERE :hsn BETWEEN hsn_start AND hsn_end
          AND :invoiceDate BETWEEN effective_from AND IFNULL(effective_to, :invoiceDate)
        ORDER BY priority ASC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'hsn' => $hsn,
        'invoiceDate' => $invoiceDate
    ]);
    $rule = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rule) {
        // Decide intra vs inter based on state
        if ($supplyState === $orgState) {
            return ['cgst' => $rule['intra_rate_percent']/2,
                    'sgst' => $rule['intra_rate_percent']/2,
                    'igst' => 0];
        } else {
            return ['cgst' => 0,
                    'sgst' => 0,
                    'igst' => $rule['inter_rate_percent']];
        }
    }

    // Fallback: gst_hsn_rates
    $sql = "SELECT gst_rate FROM gst_hsn_rates WHERE hsn = :hsn LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['hsn' => $hsn]);
    $rate = $stmt->fetchColumn();

    if ($rate) {
        if ($supplyState === $orgState) {
            return ['cgst' => $rate/2, 'sgst' => $rate/2, 'igst' => 0];
        } else {
            return ['cgst' => 0, 'sgst' => 0, 'igst' => $rate];
        }
    }

    return null; // No rate found
}
```

---

## 🔄 Usage Example
```php
$gst = getGstRate(
    $pdo,
    '30049011',          // HSN code
    '2025-09-23',        // Invoice date
    'UK',                // Supply state
    'UK'                 // Organization state
);

---

## ✅ Key Features of This Approach
- **Date‑sensitive**: Picks the correct rate valid on the invoice date.
- **HSN‑aware**: Matches exact HSN or range.
- **State‑aware**: Auto‑splits into CGST/SGST vs IGST.
- **Fallback**: Uses `gst_hsn_rates` if no rule is found.
- **Priority**: Honors `priority` column in `gst_rate_rules`.

---
### Module Structure
This accounting module is designed for integration with existing GST invoice systems. Modify and extend . As needed for your business requirements.
---
**GST India Accounting System** - Complete double-entry AI-LOGIC accounting for Indian businesses with GST compliance.
