/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_staging` (
  `invoice_no` varchar(64) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `item_desc` varchar(255) DEFAULT NULL,
  `hsn` varchar(10) DEFAULT NULL,
  `qty_received` decimal(14,3) DEFAULT NULL,
  `net_value` decimal(16,2) DEFAULT NULL,
  `gst_percent` decimal(5,2) DEFAULT NULL,
  `rate_calc` decimal(12,2) DEFAULT NULL,
  `source_file` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
