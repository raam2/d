

DROP TABLE IF EXISTS purchase_staging;
CREATE TABLE purchase_staging (
  invoice_no   VARCHAR(64),
  invoice_date DATE,
  supplier     VARCHAR(255),
  item_desc    VARCHAR(255),
  hsn          VARCHAR(10),
  qty_received DECIMAL(14,3),
  net_value    DECIMAL(16,2),
  gst_percent  DECIMAL(5,2),
  rate_calc    DECIMAL(12,2),
  source_file  VARCHAR(128)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

LOAD DATA INFILE 'Purchase Invoice Detail 2025-04-01 to 2025-09-10.csv'
INTO TABLE purchase_staging
CHARACTER SET utf8mb4
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(invoice_no, invoice_date, supplier, item_desc, hsn, qty_received, net_value, gst_percent)
SET
  source_file = 'Purchase Invoice Detail 2025-04-01 to 2025-09-10.csv',
  rate_calc = CASE WHEN qty_received > 0
                   THEN ROUND(net_value/qty_received,2)
                   ELSE NULL END;

LOAD DATA INFILE 'Purchase Invoice Detail_2024-2025_HINDI-also.csv'
INTO TABLE purchase_staging
CHARACTER SET utf8mb4
FIELDS TERMINATED BY ','  ENCLOSED BY '"' 
LINES  TERMINATED BY '\r\n'
IGNORE 1 ROWS
(
 @invoice_no,
 @invoice_date,
 @supplier,
 @item_desc,
 @hsn,
 @qty_received,
 @net_value,
 @gst_percent
)
SET
  invoice_no   = NULLIF(TRIM(@invoice_no), ''),
  supplier     = NULLIF(TRIM(@supplier), ''),
  item_desc    = NULLIF(TRIM(@item_desc), ''),
  hsn          = NULLIF(REPLACE(TRIM(@hsn),' ','') , ''),
  invoice_date =
    CASE
      WHEN @invoice_date REGEXP '^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$'
        THEN STR_TO_DATE(@invoice_date,'%d/%m/%Y')
      WHEN @invoice_date REGEXP '^[0-9]{1,2}-[0-9]{1,2}-[0-9]{4}$'
        THEN STR_TO_DATE(@invoice_date,'%d-%m-%Y')
      WHEN @invoice_date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
        THEN STR_TO_DATE(@invoice_date,'%Y-%m-%d')
      ELSE NULL
    end,
  @qty_clean = NULLIF(REPLACE(REPLACE(TRIM(@qty_received),',',''),' ',''),
                      ''),
  @net_clean = NULLIF(REPLACE(REPLACE(REPLACE(TRIM(@net_value),',',''),'₹',''),' '),
                      ''),
  @gst_clean = NULLIF(REPLACE(TRIM(@gst_percent),'%',''), ''),
  qty_received = (@qty_clean + 0),
  net_value    = (@net_clean + 0),
  gst_percent  = (@gst_clean + 0),
  rate_calc    = CASE WHEN (@qty_clean + 0) > 0
                      THEN ROUND((@net_clean + 0)/(@qty_clean + 0), 2)
                      ELSE NULL END,
  source_file  = 'Purchase Invoice Detail_2024-2025_HINDI-also.csv';

