/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.11-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: 127.0.0.1    Database: gst_accounting
-- ------------------------------------------------------
-- Server version	10.11.11-MariaDB-0+deb12u1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `purchase_invoice_staging_reverse`
--

DROP TABLE IF EXISTS `purchase_invoice_staging_reverse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_invoice_staging_reverse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `hindi_name` varchar(255) DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `mfg_date` date DEFAULT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT 1.000,
  `invoice_no` varchar(50) NOT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `supplier_gstin` varchar(15) DEFAULT NULL,
  `hsn_code` varchar(10) DEFAULT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `data_source` varchar(50) DEFAULT NULL,
  `item_net_amount_gross` decimal(12,2) NOT NULL,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `total_gst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `calculated_taxable_amount` decimal(12,2) GENERATED ALWAYS AS (`item_net_amount_gross` / (1 + `total_gst_rate` / 100)) STORED,
  `calculated_rate` decimal(12,2) GENERATED ALWAYS AS (`calculated_taxable_amount` / `quantity`) STORED,
  `calculated_cgst_amount` decimal(12,2) GENERATED ALWAYS AS (`calculated_taxable_amount` * `cgst_rate` / 100) STORED,
  `calculated_sgst_amount` decimal(12,2) GENERATED ALWAYS AS (`calculated_taxable_amount` * `sgst_rate` / 100) STORED,
  `calculated_igst_amount` decimal(12,2) GENERATED ALWAYS AS (`calculated_taxable_amount` * `igst_rate` / 100) STORED,
  `calculated_line_total` decimal(12,2) GENERATED ALWAYS AS (`calculated_taxable_amount` + `calculated_cgst_amount` + `calculated_sgst_amount` + `calculated_igst_amount`) STORED,
  `amount_difference` decimal(12,2) GENERATED ALWAYS AS (abs(`item_net_amount_gross` - `calculated_line_total`)) STORED,
  PRIMARY KEY (`id`),
  KEY `idx_staging_invoice_no` (`invoice_no`),
  KEY `idx_staging_invoice_date` (`invoice_date`),
  KEY `idx_staging_item_name` (`item_name`),
  KEY `idx_staging_supplier` (`supplier_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1302 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-23 20:51:39
