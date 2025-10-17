/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_reconciliation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_account_code` varchar(20) NOT NULL,
  `statement_date` date NOT NULL,
  `statement_balance` decimal(18,2) NOT NULL,
  `book_balance` decimal(18,2) NOT NULL,
  `reconciled_balance` decimal(18,2) NOT NULL,
  `status` enum('DRAFT','RECONCILED') DEFAULT 'DRAFT',
  `reconciled_at` timestamp NULL DEFAULT NULL,
  `reconciled_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_account_date` (`bank_account_code`,`statement_date`),
  CONSTRAINT `bank_reconciliation_ibfk_1` FOREIGN KEY (`bank_account_code`) REFERENCES `accounts` (`code`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Bank Reconciliation Headers';
/*!40101 SET character_set_client = @saved_cs_client */;
