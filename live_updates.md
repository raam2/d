Database changed
MariaDB [u184420243_jayanti_enter4]> SHOW TABLES LIKE 'product_catalog';
+-------------------------------------------------------+
| Tables_in_u184420243_jayanti_enter4 (product_catalog) |
+-------------------------------------------------------+
| product_catalog                                       |
+-------------------------------------------------------+
1 row in set (0.000 sec)

MariaDB [u184420243_jayanti_enter4]> SELECT COUNT(*) FROM product_catalog;
+----------+
| COUNT(*) |
+----------+
|      867 |
+----------+
1 row in set (0.000 sec)

MariaDB [u184420243_jayanti_enter4]> SELECT COUNT(*) FROM product_aliases;
+----------+
| COUNT(*) |
+----------+
|      524 |
+----------+
1 row in set (0.000 sec)

MariaDB [u184420243_jayanti_enter4]> SELECT COUNT(*) FROM hsn_codes;
+----------+
| COUNT(*) |
+----------+
|        1 |
+----------+
1 row in set (0.000 sec)

MariaDB [u184420243_jayanti_enter4]> 

MariaDB [u184420243_jayanti_enter4]> SELECT COUNT(DISTINCT TRIM(hsn))
    -> FROM invoice_items
    -> WHERE TRIM(hsn) <> '';
+---------------------------+
| COUNT(DISTINCT TRIM(hsn)) |
+---------------------------+
|                        86 |
+---------------------------+
1 row in set (0.001 sec)

MariaDB [u184420243_jayanti_enter4]> SELECT COUNT(DISTINCT TRIM(hsn_code))
    -> FROM purchase_invoice_staging
    -> WHERE TRIM(hsn_code) <> '';
+--------------------------------+
| COUNT(DISTINCT TRIM(hsn_code)) |
+--------------------------------+
|                              0 |
+--------------------------------+
1 row in set (0.000 sec)

MariaDB [u184420243_jayanti_enter4]> 
