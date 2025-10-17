/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Page_JS` (
  `page_id` int(11) NOT NULL,
  `js_id` int(11) NOT NULL,
  PRIMARY KEY (`page_id`,`js_id`),
  KEY `js_id` (`js_id`),
  CONSTRAINT `Page_JS_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `Pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `Page_JS_ibfk_2` FOREIGN KEY (`js_id`) REFERENCES `JS_Files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
