/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_valuations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `valuation_date` date NOT NULL,
  `qty_on_hand` decimal(12,3) NOT NULL DEFAULT 0.000,
  `unit_cost` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `valuation_method` enum('FIFO','LIFO','WEIGHTED_AVERAGE') DEFAULT 'WEIGHTED_AVERAGE',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_item_date` (`item_id`,`valuation_date`),
  KEY `idx_item_date` (`item_id`,`valuation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory valuations for COGS calculation';
/*!40101 SET character_set_client = @saved_cs_client */;
