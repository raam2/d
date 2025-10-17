/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_reconciliation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reconciliation_id` int(11) NOT NULL,
  `journal_line_id` int(11) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `type` enum('DEPOSIT','WITHDRAWAL','OUTSTANDING_DEPOSIT','OUTSTANDING_WITHDRAWAL') NOT NULL,
  `is_reconciled` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_reconciliation` (`reconciliation_id`),
  KEY `idx_journal_line` (`journal_line_id`),
  CONSTRAINT `bank_reconciliation_items_ibfk_1` FOREIGN KEY (`reconciliation_id`) REFERENCES `bank_reconciliation` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_reconciliation_items_ibfk_2` FOREIGN KEY (`journal_line_id`) REFERENCES `journal_lines` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Bank Reconciliation Items';
/*!40101 SET character_set_client = @saved_cs_client */;
