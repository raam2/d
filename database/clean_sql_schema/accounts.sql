/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `account_type` enum('ASSET','LIABILITY','EQUITY','INCOME','EXPENSE') NOT NULL,
  `parent_code` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`),
  KEY `idx_type` (`account_type`),
  KEY `idx_parent` (`parent_code`),
  KEY `idx_accounts_active` (`is_active`),
  CONSTRAINT `accounts_ibfk_1` FOREIGN KEY (`parent_code`) REFERENCES `accounts` (`code`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Chart of Accounts';
/*!40101 SET character_set_client = @saved_cs_client */;
