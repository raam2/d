/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_statements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_code` varchar(20) NOT NULL,
  `statement_date` date NOT NULL,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `closing_balance` decimal(15,2) NOT NULL,
  `total_credits` decimal(15,2) DEFAULT 0.00,
  `total_debits` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','reconciled','partial') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_statement` (`account_code`,`statement_date`),
  KEY `idx_account_date` (`account_code`,`statement_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bank statement headers';
/*!40101 SET character_set_client = @saved_cs_client */;
