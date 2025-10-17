/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stg_purchase_invoice_hindi` (
  `HSN code` varchar(20) DEFAULT NULL,
  `Received Qty` decimal(12,3) DEFAULT NULL,
  `mrp` decimal(10,2) DEFAULT NULL,
  `Batch No` varchar(50) DEFAULT NULL,
  `Mfg date` date DEFAULT NULL,
  `GST Perc` decimal(5,2) DEFAULT NULL,
  `Supplier Name` varchar(255) DEFAULT NULL,
  `Item name` varchar(255) DEFAULT NULL,
  `Hindi_Name` varchar(255) DEFAULT NULL,
  `Item Net Amount` decimal(14,2) DEFAULT NULL,
  `cgstrate` decimal(6,2) DEFAULT NULL,
  `sgstrate` decimal(6,2) DEFAULT NULL,
  `igstrate` decimal(6,2) DEFAULT NULL,
  `Inv No` varchar(50) DEFAULT NULL,
  `Supplier GST` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
