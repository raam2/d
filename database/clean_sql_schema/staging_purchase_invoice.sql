/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `staging_purchase_invoice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `HSN_code` varchar(50) DEFAULT NULL,
  `Received_Qty` varchar(50) DEFAULT NULL,
  `mrp` varchar(50) DEFAULT NULL,
  `Batch_No` varchar(100) DEFAULT NULL,
  `Mfg_date` varchar(50) DEFAULT NULL,
  `GST_Perc` varchar(50) DEFAULT NULL,
  `Supplier_Name` varchar(255) DEFAULT NULL,
  `Item_name` varchar(255) DEFAULT NULL,
  `Hindi_Name` varchar(255) DEFAULT NULL,
  `Item_Net_Amount` varchar(50) DEFAULT NULL,
  `cgstrate` varchar(50) DEFAULT NULL,
  `sgstrate` varchar(50) DEFAULT NULL,
  `igstrate` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
