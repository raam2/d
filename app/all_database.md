mariadb --default-character-set=utf8mb4 --host=localhost --port=3306 --user=gstwork --password='gstwork@123' gst_app_github_copilot
mariadb --default-character-set=utf8mb4 --host=localhost --port=3306 --user=gstwork --password='gstwork@123' gst_notebook_lm
mariadb --default-character-set=utf8mb4 --host=localhost --port=3306 --user=gstwork --password='gstwork@123' gst_accounting_MICROSOFT
mariadb --default-character-set=utf8mb4 --host=localhost --port=3306 --user=gstwork --password='gstwork@123' gst_accounting
mariadb --default-character-set=utf8mb4 --host=localhost --port=3306 --user=u184420243_gst4 --password='Raam2*:1' u184420243_jayanti_enter4
at_hostinger below .
mariadb --default-character-set=utf8mb4 --host=srv684.hstgr.io --port=3306 --user=u184420243_gst4 --password='Raam2:=195' u184420243_jayanti_enter4
mariadb -h 217.21.95.103 -u u184420243_gst4 -p'Raam2:=195' u184420243_jayanti_enter4 \


mariadb \
  --host=srv684.hstgr.io \
  --port=3306 \
  --user=u184420243_gst4 \
  --password='Raam2:=195' \
  --default-character-set=utf8mb4 \
  u184420243_jayanti_enter4 \
  
mariadb --default-character-set=utf8mb4 \
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

SOURCE /var/www/html/bharat_accounting/app/sql/gst_compliant_schema.sql;

SET FOREIGN_KEY_CHECKS=1;
SET UNIQUE_CHECKS=1;
SET sql_mode=@OLD_SQL_MODE;
EOF
