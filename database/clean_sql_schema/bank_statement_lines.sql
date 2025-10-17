/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_statement_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `statement_id` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `debit_amount` decimal(15,2) DEFAULT 0.00,
  `credit_amount` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `is_reconciled` tinyint(1) DEFAULT 0,
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stmt_date` (`statement_id`,`transaction_date`),
  KEY `idx_reconciled` (`is_reconciled`),
  KEY `idx_journal` (`journal_entry_id`),
  CONSTRAINT `bank_statement_lines_ibfk_1` FOREIGN KEY (`statement_id`) REFERENCES `bank_statements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bank statement transaction lines';
/*!40101 SET character_set_client = @saved_cs_client */;
