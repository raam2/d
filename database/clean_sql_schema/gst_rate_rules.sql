/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gst_rate_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `priority` int(11) NOT NULL DEFAULT 100,
  `hsn_chapter` varchar(4) DEFAULT NULL,
  `hsn_start` varchar(10) DEFAULT NULL,
  `hsn_end` varchar(10) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `condition_type` enum('NONE','VALUE_PER_PCS_LE','PREPACKED_LABELLED') NOT NULL DEFAULT 'NONE',
  `threshold_value` decimal(10,2) DEFAULT NULL,
  `intra_rate_percent` decimal(5,2) NOT NULL,
  `inter_rate_percent` decimal(5,2) NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `notification_ref` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rules_range` (`hsn_chapter`,`hsn_start`,`hsn_end`),
  KEY `idx_rules_eff` (`effective_from`,`effective_to`),
  KEY `idx_gst_rules_hsn` (`hsn_chapter`),
  KEY `idx_gst_rules_effective` (`effective_from`,`effective_to`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
