/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `source_type` varchar(50) DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` varchar(100) DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'posted',
  PRIMARY KEY (`id`),
  KEY `idx_company_date` (`entry_date`),
  KEY `idx_journal_source` (`source_type`,`source_id`),
  CONSTRAINT `chk_journal_status` CHECK (`status` in ('draft','posted','cancelled'))
) ENGINE=InnoDB AUTO_INCREMENT=313 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
