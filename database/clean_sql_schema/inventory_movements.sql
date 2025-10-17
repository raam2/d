/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `movement_type` enum('IN','OUT','ADJUSTMENT') NOT NULL,
  `movement_date` date NOT NULL,
  `qty` decimal(12,3) NOT NULL,
  `unit_cost` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `source_type` varchar(50) DEFAULT NULL COMMENT 'invoice, adjustment, etc',
  `source_id` int(11) DEFAULT NULL COMMENT 'Reference to source document',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_item_date` (`item_id`,`movement_date`),
  KEY `idx_source` (`source_type`,`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory movement tracking';
/*!40101 SET character_set_client = @saved_cs_client */;
