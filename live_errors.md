
Warning: require(/var/www/html/bharat_accounting/appconfig.php): Failed to open stream: No such file or directory in /var/www/html/bharat_accounting/app/db.php on line 2

Fatal error: Uncaught Error: Failed opening required '/var/www/html/bharat_accounting/appconfig.php' (include_path='.:/usr/share/php') in /var/www/html/bharat_accounting/app/db.php:2 Stack trace: #0 /var/www/html/bharat_accounting/app/main_entry.php(7): require() #1 {main} thrown in /var/www/html/bharat_accounting/app/db.php on line 2


root@boss-lxc-server:/var/www/html/bharat_accounting# cd /var/www/html/bharat_accounting/app
mv ' config.php' config.php
root@boss-lxc-server:/var/www/html/bharat_accounting/app# 
 above code success .
 