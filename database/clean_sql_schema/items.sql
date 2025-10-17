/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `canonical_name` varchar(255) NOT NULL,
  `hsn` varchar(10) DEFAULT NULL,
  `is_SSC_PAYABLE` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_prepackaged_labelled` tinyint(1) NOT NULL DEFAULT 0,
  `track_cogs` tinyint(1) DEFAULT 1 COMMENT 'Track COGS for this item',
  `inventory_account` varchar(20) DEFAULT '1200' COMMENT 'Inventory GL account',
  `cogs_account` varchar(20) DEFAULT '5100' COMMENT 'COGS GL account',
  PRIMARY KEY (`id`),
  KEY `idx_item_name` (`canonical_name`),
  KEY `idx_item_hsn_code` (`hsn`),
  FULLTEXT KEY `ft_item_name` (`canonical_name`)
) ENGINE=InnoDB AUTO_INCREMENT=955 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
