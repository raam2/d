/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) NOT NULL,
  `account_type` enum('ledger','bank') NOT NULL,
  `party_id` int(11) DEFAULT NULL,
  `account_code` varchar(100) DEFAULT NULL,
  `debit_amount` decimal(12,2) DEFAULT NULL,
  `credit_amount` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_id` (`entry_id`),
  CONSTRAINT `fk_journal_line_entry` FOREIGN KEY (`entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `journal_lines_ibfk_1` FOREIGN KEY (`entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=824 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
