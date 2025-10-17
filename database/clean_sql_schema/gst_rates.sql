/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gst_rates` (
  `id` tinyint(4) NOT NULL AUTO_INCREMENT,
  `cgst` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sgst` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst` decimal(5,2) NOT NULL DEFAULT 0.00,
  `total_rate` decimal(5,2) GENERATED ALWAYS AS (`cgst` + `sgst` + `igst`) STORED,
  PRIMARY KEY (`id`),
  KEY `idx_gst_rates_total` (`total_rate`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
