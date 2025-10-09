MariaDB [u184420243_jayanti_enter4]> SELECT hsn_code, description, gst_slab
    -> FROM hsn_codes
    -> WHERE hsn_code IN ('3041','3044','150000727')
    -> ORDER BY hsn_code;
+-----------+-------------+----------+
| hsn_code  | description | gst_slab |
+-----------+-------------+----------+
| 150000727 | 11-09-2024  |    17.00 |
| 3041      | 10-04-2024  |    21.00 |
| 3044      | 04-04-2024  |    19.00 |
+-----------+-------------+----------+
3 rows in set (0.000 sec)

MariaDB [u184420243_jayanti_enter4]> exit
Bye
root@boss-lxc-server:~# mariadb --default-character-set=utf8mb4 \
  --host=localhost --port=3306 \
  --user=u184420243_gst4 --password='Raam2*:1' \
  u184420243_jayanti_enter4 \
  > /var/www/html/bharat_accounting/app/output.log \
  2> /var/www/html/bharat_accounting/app/errors.log <<'EOF'
SET @OLD_SQL_MODE := @@sql_mode;
SET sql_mode='';
SET FOREIGN_KEY_CHECKS=0;
SET UNIQUE_CHECKS=0;
SET NAMES utf8mb4;

SOURCE /var/www/html/bharat_accounting/app/sql/catalog_normalization_migration.sql;

SET FOREIGN_KEY_CHECKS=1;
SET UNIQUE_CHECKS=1;
EOF sql_mode=@OLD_SQL_MODE;
root@boss-lxc-server:~# mariadb u184420243_jayanti_enter4;
Reading table information for completion of table and column names
You can turn off this feature to get a quicker startup with -A

Welcome to the MariaDB monitor.  Commands end with ; or \g.
Your MariaDB connection id is 58
Server version: 11.4.8-MariaDB-deb12 mariadb.org binary distribution

Copyright (c) 2000, 2018, Oracle, MariaDB Corporation Ab and others.

Type 'help;' or '\h' for help. Type '\c' to clear the current input statement.

MariaDB [u184420243_jayanti_enter4]> SELECT hsn_code, description, gst_slab
    -> FROM hsn_codes
    -> WHERE hsn_code IN ('3041','3044','150000727','10063020')
    -> ORDER BY hsn_code;
+-----------+------------------------------------------------------------------------------------------------+----------+
| hsn_code  | description                                                                                    | gst_slab |
+-----------+------------------------------------------------------------------------------------------------+----------+
| 10063020  | हेयर कलर डार्क ब्राउन (क्रीम+डेवलपर)                                                           |    31.00 |
| 10063020  | ब्राउन बासमती चावल 1 किलो (पाउच)                                                               |     5.00 |
| 10063020  | ब्राउन बासमती चावल 1 किलो (पाउच)                                                               |     5.00 |
| 150000727 | 11-09-2024                                                                                     |    17.00 |
| 3041      | 10-04-2024                                                                                     |    21.00 |
| 3044      | 04-04-2024                                                                                     |    19.00 |
+-----------+------------------------------------------------------------------------------------------------+----------+
6 rows in set (0.000 sec)

MariaDB [u184420243_jayanti_enter4]> 
