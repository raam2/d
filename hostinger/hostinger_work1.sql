password 	Raam2:=195		 	MySQL Databases= 	u184420243_jayanti_enterp	 And User=		u184420243_gst		
Our MySQL server hostname is: srv684.hstgr.io or you can use this IP as your hostname: 217.21.95.103	
https://hpanel.hostinger.com/websites/xxxxxxx/databases/remote-my-sql?redirectLocation=side_menu	

gst_notebook_lm
mysql --default-character-set=utf8mb4 -u gstwork -p\'gstwork@123\' -h 127.0.0.1 -P 3306 gst_notebook_lm
mariadb --host=localhost --port=3306 --user=u184420243_gst4 --password='Raam2:=195' $u184420243_jayanti_enter4
mariadb -h 217.21.95.103 -u u184420243_gst4 -p'Raam2:=195' u184420243_jayanti_enter4 \

4_db=	u184420243_jayanti_enter4		user=u184420243_gst4		;	3_db=	u184420243_jayanti_enter3	user=u184420243_gst3 

Remember change -- Host: 127.0.0.1:3306 as localhost		

mariadb \
  --host=srv684.hstgr.io \
  --port=3306 \
  --user=u184420243_gst4 \
  --password='Raam2:=195' \
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
SOURCE /var/www/html/bharat_accounting/hostinger/4_db.sql;

-- Restore settings
SET FOREIGN_KEY_CHECKS=1;
SET UNIQUE_CHECKS=1;
SET sql_mode=@OLD_SQL_MODE;

EOF
