
-- REVERSE CALCULATION DATABASE INSERTION SCRIPT
-- This script performs reverse calculation from Item_Net_Amount to find taxable_amount

-- Step 1: Create staging table with reverse calculation fields
DROP TABLE IF EXISTS `purchase_invoice_staging_reverse`;

CREATE TABLE `purchase_invoice_staging_reverse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `hindi_name` varchar(255) DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `mfg_date` date DEFAULT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT 1.000,
  `invoice_no` varchar(50) NOT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `supplier_gstin` varchar(15) DEFAULT NULL,
  `hsn_code` varchar(10) DEFAULT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `data_source` varchar(50) DEFAULT NULL,

  -- Original gross amount (including GST)
  `item_net_amount_gross` decimal(12,2) NOT NULL,

  -- GST rates
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `total_gst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,

  -- Calculated fields (reverse calculation)
  `calculated_taxable_amount` decimal(12,2) GENERATED ALWAYS AS 
    (`item_net_amount_gross` / (1 + `total_gst_rate`/100)) STORED,

  `calculated_rate` decimal(12,2) GENERATED ALWAYS AS 
    (`calculated_taxable_amount` / `quantity`) STORED,

  `calculated_cgst_amount` decimal(12,2) GENERATED ALWAYS AS 
    (`calculated_taxable_amount` * `cgst_rate` / 100) STORED,

  `calculated_sgst_amount` decimal(12,2) GENERATED ALWAYS AS 
    (`calculated_taxable_amount` * `sgst_rate` / 100) STORED,

  `calculated_igst_amount` decimal(12,2) GENERATED ALWAYS AS 
    (`calculated_taxable_amount` * `igst_rate` / 100) STORED,

  `calculated_line_total` decimal(12,2) GENERATED ALWAYS AS 
    (`calculated_taxable_amount` + `calculated_cgst_amount` + `calculated_sgst_amount` + `calculated_igst_amount`) STORED,

  -- Verification field
  `amount_difference` decimal(12,2) GENERATED ALWAYS AS 
    (ABS(`item_net_amount_gross` - `calculated_line_total`)) STORED,

  PRIMARY KEY (`id`),
  KEY `idx_staging_invoice_no` (`invoice_no`),
  KEY `idx_staging_invoice_date` (`invoice_date`),
  KEY `idx_staging_item_name` (`item_name`),
  KEY `idx_staging_supplier` (`supplier_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Import data with reverse calculations
LOAD DATA LOCAL INFILE '/var/www/html/bharat_accounting/purchase_invoice_with_reverse_calculations.csv' 
INTO TABLE purchase_invoice_staging_reverse
FIELDS TERMINATED BY ',' 
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(item_name, hindi_name, exp_date, invoice_date, mfg_date, quantity, 
 invoice_no, batch_no, supplier_gstin, hsn_code, supplier_name, data_source,
 item_net_amount_gross, @taxable_amt, @rate, cgst_rate, sgst_rate, igst_rate, 
 total_gst_rate, @cgst_amt, @sgst_amt, @igst_amt, @line_total);

-- Note: We ignore the pre-calculated fields and let MySQL compute them automatically

-- Step 3: Validation of reverse calculations
SELECT 'Reverse Calculation Validation' as report_title;

SELECT 
  'Total records imported' as metric,
  COUNT(*) as value
FROM purchase_invoice_staging_reverse;

SELECT 
  'Maximum calculation difference' as metric,
  CONCAT('₹', FORMAT(MAX(amount_difference), 2)) as value
FROM purchase_invoice_staging_reverse;

SELECT 
  'Average calculation difference' as metric,
  CONCAT('₹', FORMAT(AVG(amount_difference), 4)) as value
FROM purchase_invoice_staging_reverse;

SELECT 
  'Records with calculation errors > ₹0.01' as metric,
  COUNT(*) as value
FROM purchase_invoice_staging_reverse
WHERE amount_difference > 0.01;

-- Sample data verification
SELECT 
  item_name,
  item_net_amount_gross as gross_amount,
  total_gst_rate as gst_percent,
  calculated_taxable_amount as taxable_amount,
  calculated_rate as unit_rate,
  calculated_cgst_amount + calculated_sgst_amount + calculated_igst_amount as total_gst,
  calculated_line_total as recalculated_total,
  amount_difference as difference
FROM purchase_invoice_staging_reverse
LIMIT 10;
