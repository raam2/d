/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSS_History` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `css_id` int(11) NOT NULL,
  `version_no` int(11) NOT NULL,
  `code` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `css_id` (`css_id`,`version_no`),
  CONSTRAINT `CSS_History_ibfk_1` FOREIGN KEY (`css_id`) REFERENCES `CSS_Files` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
