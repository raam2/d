-- STEP 1: staging table for CSV import
DROP TABLE IF EXISTS purchase_staging;
CREATE TABLE purchase_staging (
    invoice_no       VARCHAR(64),
    invoice_date     DATE,
    supplier         VARCHAR(255),
    item_desc        VARCHAR(255),
    hsn              VARCHAR(10),
    qty_received     DECIMAL(14,3),
    net_value        DECIMAL(16,2),
    gst_percent      DECIMAL(5,2),
    source_file      VARCHAR(128)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- STEP 2: load your CSVs
-- (⚠️ change the file paths according to your MySQL server)
LOAD DATA INFILE 'Purchase Invoice Detail 2025-04-01 to 2025-09-10.csv'
INTO TABLE purchase_staging
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(invoice_no, invoice_date, supplier, item_desc, hsn, qty_received, net_value, gst_percent)
SET source_file = 'Purchase Invoice Detail 2025-04-01 to 2025-09-10.csv';

LOAD DATA INFILE 'Purchase Invoice Detail_2024-2025_HINDI-also.csv'
INTO TABLE purchase_staging
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(invoice_no, invoice_date, supplier, item_desc, hsn, qty_received, net_value, gst_percent)
SET source_file = 'Purchase Invoice Detail_2024-2025_HINDI-also.csv';

-- STEP 3: reverse calculate rate
ALTER TABLE purchase_staging ADD COLUMN rate_calc DECIMAL(12,2);
UPDATE purchase_staging
SET rate_calc = CASE WHEN qty_received > 0 THEN ROUND(net_value/qty_received,2) ELSE NULL END;

-- STEP 4: deduplicate
DROP TABLE IF EXISTS purchase_staging_dedup;
CREATE TABLE purchase_staging_dedup AS
SELECT invoice_no, invoice_date, supplier, hsn,
       MAX(item_desc) AS item_desc,
       SUM(qty_received) AS qty_received,
       SUM(net_value) AS net_value,
       ROUND(SUM(net_value)/NULLIF(SUM(qty_received),0),2) AS rate_calc,
       MAX(gst_percent) AS gst_percent,
       MAX(source_file) AS source_file
FROM purchase_staging
GROUP BY invoice_no, invoice_date, supplier, hsn, rate_calc;

-- STEP 5: build invoices table
DROP TABLE IF EXISTS invoices_corrected;
CREATE TABLE invoices_corrected LIKE invoices;

INSERT INTO invoices_corrected (invoice_no, invoice_date, party_name, grand_total)
SELECT d.invoice_no, d.invoice_date, d.supplier,
       SUM(d.net_value * (1 + d.gst_percent/100)) AS grand_total
FROM purchase_staging_dedup d
GROUP BY d.invoice_no, d.invoice_date, d.supplier;

-- STEP 6: build invoice_items_corrected
DROP TABLE IF EXISTS invoice_items_corrected;
CREATE TABLE invoice_items_corrected LIKE invoice_items;

INSERT INTO invoice_items_corrected
(invoice_id, description, hsn, quantity, rate,
 cgst_rate, sgst_rate, igst_rate, line_total, itc_eligible)
SELECT i.id AS invoice_id, d.item_desc, d.hsn,
       d.qty_received, d.rate_calc,
       d.gst_percent/2, d.gst_percent/2, 0,
       ROUND(d.qty_received * d.rate_calc * (1 + d.gst_percent/100),2) AS line_total,
       1 AS itc_eligible
FROM purchase_staging_dedup d
JOIN invoices_corrected i ON d.invoice_no = i.invoice_no;

-- STEP 7: split registered vs unregistered
DROP TABLE IF EXISTS invoice_items_registered;
DROP TABLE IF EXISTS invoice_items_unregistered;

CREATE TABLE invoice_items_registered LIKE invoice_items_corrected;
CREATE TABLE invoice_items_unregistered LIKE invoice_items_corrected;

INSERT INTO invoice_items_registered
SELECT * FROM invoice_items_corrected
WHERE (cgst_rate + sgst_rate + igst_rate) > 0;

INSERT INTO invoice_items_unregistered
SELECT * FROM invoice_items_corrected
WHERE (cgst_rate + sgst_rate + igst_rate) = 0;

-- STEP 8: verification
SELECT COUNT(*) AS total_all FROM invoice_items_corrected;
SELECT COUNT(*) AS total_reg FROM invoice_items_registered;
SELECT COUNT(*) AS total_unreg FROM invoice_items_unregistered;

