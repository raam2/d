	lxc exec boss-lxc-server -- bash			10.160.118.61
	
	10.160.118.61/?p=dashboard
	
	Reload the web app
Open main_entry.php in your browser (or whichever minimal entry point you’re using).
Navigate using ?p=dashboard, ?p=parties, ?p=items, ?p=invoices.
The UI should be generated entirely from the metadata that now lives in the DB.
mariadb u184420243_jayanti_enter4 \
  -e "SELECT slug, title FROM app_pages ORDER BY slug;"
