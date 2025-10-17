from_hostinger at localhost =	db=		u184420243_jayanti_enter4	user=u184420243_gst4	pass=Raam2*:1
CREATE USER 'u184420243_gst4'@'localhost' IDENTIFIED BY 'Raam2*:1';

mariadb \
  --host=localhost \
  --port=3306 \
  --user=u184420243_gst4 \
  --password='Raam2*:1' \
  --default-character-set=utf8mb4 \
  u184420243_jayanti_enter4 \
   <<'EOF' \
  > /var/www/html/bharat_accounting/hostinger/remote_output.log \
  2> /var/www/html/bharat_accounting/hostinger/remote_errors.log

-- Save old settings
SET @OLD_SQL_MODE := @@sql_mode;
SET sql_mode='';
SET FOREIGN_KEY_CHECKS=0;
SET UNIQUE_CHECKS=0;
SET NAMES utf8mb4;

-- Import dump
SOURCE /var/www/html/bharat_accounting/.sql;

-- Restore settings
SET FOREIGN_KEY_CHECKS=1;
SET UNIQUE_CHECKS=1;
SET sql_mode=@OLD_SQL_MODE;

EOF
