/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `app_settings` VALUES
('debug_mode','on','2025-09-25 11:18:16','2025-09-25 11:18:16'),
('dependency_refresh_command','mysql --default-character-set=utf8mb4 -u gstwork -p\'gstwork@123\' -h 127.0.0.1 -P 3306 -D gst_accounting < /var/www/html/bharat_accounting/update_code.sql','2025-09-25 11:18:16','2025-09-25 11:18:16'),
('org_state','UK','2025-09-19 09:31:35','2025-09-19 09:31:35');
