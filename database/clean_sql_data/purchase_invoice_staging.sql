/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_invoice_staging` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `hindi_name` varchar(255) DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `mfg_date` date DEFAULT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT 1.000,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `invoice_no` varchar(50) NOT NULL,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `batch_no` varchar(50) DEFAULT NULL,
  `taxable_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `supplier_gstin` varchar(15) DEFAULT NULL,
  `total_gst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `hsn_code` varchar(10) DEFAULT NULL,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `mrp` decimal(8,2) DEFAULT NULL,
  `igst_amount` decimal(10,2) DEFAULT 0.00,
  `supplier_name` varchar(255) NOT NULL,
  `data_source` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_staging_invoice_no` (`invoice_no`),
  KEY `idx_staging_invoice_date` (`invoice_date`),
  KEY `idx_staging_item_name` (`item_name`),
  KEY `idx_staging_supplier` (`supplier_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
