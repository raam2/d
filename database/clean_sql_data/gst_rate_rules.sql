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
INSERT INTO `gst_rate_rules` VALUES
(1,10,'61',NULL,NULL,'Apparel (knitted) ≤ ₹1000/pcs','VALUE_PER_PCS_LE',1000.00,5.00,5.00,'2025-04-01',NULL,'CBIC'),
(2,10,'62',NULL,NULL,'Apparel (not knitted) ≤ ₹1000/pcs','VALUE_PER_PCS_LE',1000.00,5.00,5.00,'2025-04-01',NULL,'CBIC'),
(3,11,'61',NULL,NULL,'Apparel (knitted) > ₹1000/pcs','NONE',NULL,12.00,12.00,'2025-04-01',NULL,'CBIC'),
(4,11,'62',NULL,NULL,'Apparel (not knitted) > ₹1000/pcs','NONE',NULL,12.00,12.00,'2025-04-01',NULL,'CBIC'),
(5,12,'6501',NULL,NULL,'Headgear ≤ ₹1000/pcs','VALUE_PER_PCS_LE',1000.00,5.00,5.00,'2025-04-01',NULL,'CBIC'),
(6,12,'6505',NULL,NULL,'Headgear ≤ ₹1000/pcs','VALUE_PER_PCS_LE',1000.00,5.00,5.00,'2025-04-01',NULL,'CBIC'),
(7,13,'6501',NULL,NULL,'Headgear > ₹1000/pcs','NONE',NULL,12.00,12.00,'2025-04-01',NULL,'CBIC'),
(8,13,'6505',NULL,NULL,'Headgear > ₹1000/pcs','NONE',NULL,12.00,12.00,'2025-04-01',NULL,'CBIC'),
(9,20,'10',NULL,NULL,'Cereals – pre-packaged & labelled','PREPACKED_LABELLED',NULL,5.00,5.00,'2025-04-01',NULL,'2022-07-18'),
(10,21,'11',NULL,NULL,'Flours/Suji – pre-packaged & labelled','PREPACKED_LABELLED',NULL,5.00,5.00,'2025-04-01',NULL,'2022-07-18'),
(11,22,'07',NULL,NULL,'Pulses (0713) – pre-packaged & labelled','PREPACKED_LABELLED',NULL,5.00,5.00,'2025-04-01',NULL,'2022-07-18'),
(12,23,'17',NULL,NULL,'Jaggery (1701) – pre-packaged & labelled','PREPACKED_LABELLED',NULL,5.00,5.00,'2025-04-01',NULL,'2022-07-18'),
(19,10,'61',NULL,NULL,'Apparel knitted per‑piece ≤ ₹1000','VALUE_PER_PCS_LE',1000.00,5.00,5.00,'2025-04-01',NULL,'APPAREL_5_12_2025APR'),
(20,10,'62',NULL,NULL,'Apparel non‑knitted per‑piece ≤ ₹1000','VALUE_PER_PCS_LE',1000.00,5.00,5.00,'2025-04-01',NULL,'APPAREL_5_12_2025APR'),
(21,11,'61',NULL,NULL,'Apparel knitted per‑piece > ₹1000','NONE',NULL,12.00,12.00,'2025-04-01',NULL,'APPAREL_5_12_2025APR'),
(22,11,'62',NULL,NULL,'Apparel non‑knitted per‑piece > ₹1000','NONE',NULL,12.00,12.00,'2025-04-01',NULL,'APPAREL_5_12_2025APR'),
(23,12,'6505',NULL,NULL,'Headgear per‑piece ≤ ₹1000','VALUE_PER_PCS_LE',1000.00,5.00,5.00,'2025-04-01',NULL,'APPAREL_5_12_2025APR'),
(24,13,'6505',NULL,NULL,'Headgear per‑piece > ₹1000','NONE',NULL,12.00,12.00,'2025-04-01',NULL,'APPAREL_5_12_2025APR');
