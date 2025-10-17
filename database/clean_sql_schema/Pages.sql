/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `menu_label` varchar(100) DEFAULT NULL,
  `menu_group` varchar(100) DEFAULT NULL,
  `menu_order` int(11) DEFAULT 0,
  `code` mediumtext DEFAULT NULL,
  `tables_used` varchar(500) DEFAULT NULL COMMENT 'Comma-separated list of transaction tables this page interacts with',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
