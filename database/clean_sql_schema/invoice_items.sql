/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `description_en` varchar(255) DEFAULT NULL,
  `hsn` varchar(10) DEFAULT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT 1.000,
  `rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `rate` * `discount_percent` / 100) STORED,
  `taxable_amount` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `rate` - `quantity` * `rate` * `discount_percent` / 100) STORED,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(10,2) GENERATED ALWAYS AS (`taxable_amount` * `cgst_rate` / 100) STORED,
  `sgst_amount` decimal(10,2) GENERATED ALWAYS AS (`taxable_amount` * `sgst_rate` / 100) STORED,
  `igst_amount` decimal(10,2) GENERATED ALWAYS AS (`taxable_amount` * `igst_rate` / 100) STORED,
  `line_total` decimal(12,2) GENERATED ALWAYS AS (`taxable_amount` + `cgst_amount` + `sgst_amount` + `igst_amount`) STORED,
  `itc_eligible` tinyint(1) NOT NULL DEFAULT 1,
  `is_prepackaged_labelled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_item_id` (`item_id`),
  KEY `idx_item_invoice` (`invoice_id`),
  KEY `idx_item_hsn` (`hsn`),
  KEY `idx_item_rate` (`rate`),
  KEY `idx_item_description` (`description`),
  FULLTEXT KEY `description` (`description`),
  FULLTEXT KEY `description_2` (`description`),
  CONSTRAINT `fk_newiitems_inv` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_newiitems_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2366 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
