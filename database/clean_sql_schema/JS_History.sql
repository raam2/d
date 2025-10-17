/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `JS_History` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `js_id` int(11) NOT NULL,
  `version_no` int(11) NOT NULL,
  `code` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `js_id` (`js_id`,`version_no`),
  CONSTRAINT `JS_History_ibfk_1` FOREIGN KEY (`js_id`) REFERENCES `JS_Files` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
