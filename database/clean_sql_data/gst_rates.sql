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
INSERT INTO `gst_rates` VALUES
(1,0.00,0.00,0.00,0.00),
(2,2.50,2.50,5.00,10.00),
(3,6.00,6.00,12.00,24.00),
(4,9.00,9.00,18.00,36.00),
(5,14.00,14.00,28.00,56.00);
