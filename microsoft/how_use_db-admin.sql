INSERT INTO settings (setting_key, setting_value)
VALUES ('super_admin_token', 'CHANGE_ME_STRONG_TOKEN')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Purpose: A simple gate so only someone who knows the token can access the explorer.

-- How to use: Visit new_index.php?page=tools/schema-explorer&token=CHANGE_ME_STRONG_TOKEN

-- Change required: Replace CHANGE_ME_STRONG_TOKEN with a strong secret.
