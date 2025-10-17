/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_series` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inv_type` varchar(20) NOT NULL,
  `series_code` varchar(20) NOT NULL,
  `current_no` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `last_reset_fy` year(4) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
