/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `parties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `party_type` enum('customer','supplier','both') NOT NULL DEFAULT 'customer',
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `state_code` varchar(2) DEFAULT NULL COMMENT 'State code for GST classification',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parties_gstin` (`gstin`),
  KEY `idx_party_gstin` (`gstin`),
  KEY `idx_party_name` (`name`),
  KEY `idx_party_state` (`state`),
  KEY `idx_party_type` (`party_type`),
  CONSTRAINT `chk_gstin_format` CHECK (`gstin` regexp '^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$')
) ENGINE=InnoDB AUTO_INCREMENT=3011 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
