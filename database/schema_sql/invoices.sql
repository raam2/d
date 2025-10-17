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
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `party_id` int(11) NOT NULL,
  `inv_type` enum('sale','purchase','credit_note','debit_note') NOT NULL,
  `series_code` varchar(20) DEFAULT NULL,
  `seq_no` int(11) DEFAULT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `external_sales_ref_no` varchar(100) DEFAULT NULL,
  `external_supplier_invoice_no` varchar(100) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `place_of_supply` varchar(50) DEFAULT NULL,
  `reverse_charge` tinyint(1) DEFAULT 0,
  `status` enum('draft','final','cancelled') DEFAULT 'draft',
  `itc_eligible` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_inv_date` (`invoice_date`),
  KEY `idx_inv_type` (`inv_type`),
  KEY `idx_inv_party` (`party_id`),
  KEY `idx_invoice_date` (`invoice_date`),
  KEY `idx_invoice_no` (`invoice_no`),
  KEY `idx_invoice_status` (`status`),
  KEY `idx_invoice_type` (`inv_type`),
  KEY `idx_invoice_party` (`party_id`),
  CONSTRAINT `fk_newinv_party` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=336 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`gstwork`@`localhost`*/ /*!50003 TRIGGER trg_generate_purchase_invno
BEFORE INSERT ON invoices
FOR EACH ROW
BEGIN
  IF NEW.inv_type='purchase' AND NEW.party_id=4 THEN
    SET NEW.invoice_no = CONCAT(
        'URD-', DATE_FORMAT(NEW.invoice_date,'%Y%m%d'), '-',
        LPAD(
          IFNULL(
            (
              SELECT MAX(CAST(SUBSTRING_INDEX(invoice_no, '-', -1) AS UNSIGNED))
              FROM invoices
              WHERE inv_type='purchase'
                AND party_id = 4
                AND DATE(invoice_date) = DATE(NEW.invoice_date)
            ), 0
          ) + 1,
          3, '0'
        )
    );
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`gstwork`@`localhost`*/ /*!50003 TRIGGER `bi_invoices_autoserial` BEFORE INSERT ON `invoices` FOR EACH ROW
BEGIN
  DECLARE v_next INT;
  DECLARE v_prefix VARCHAR(20);

  IF NEW.series_code IS NOT NULL AND (NEW.seq_no IS NULL OR NEW.seq_no = 0) THEN
    UPDATE invoice_series
       SET current_no = LAST_INSERT_ID(current_no + 1)
     WHERE inv_type   = NEW.inv_type
       AND series_code= NEW.series_code;

    SET v_next = LAST_INSERT_ID();

    SELECT prefix INTO v_prefix
      FROM invoice_series
     WHERE inv_type   = NEW.inv_type
       AND series_code= NEW.series_code
     LIMIT 1;

    SET NEW.seq_no = v_next;

    IF NEW.invoice_no IS NULL OR NEW.invoice_no = '' THEN
      SET NEW.invoice_no = CONCAT(v_prefix, LPAD(NEW.seq_no, 5, '0'));
    END IF;
  END IF;

  IF NEW.series_code IS NOT NULL AND NEW.seq_no IS NOT NULL AND (NEW.invoice_no IS NULL OR NEW.invoice_no='') THEN
    SELECT prefix INTO v_prefix
      FROM invoice_series
     WHERE inv_type   = NEW.inv_type
       AND series_code= NEW.series_code
     LIMIT 1;

    IF v_prefix IS NOT NULL THEN
      SET NEW.invoice_no = CONCAT(v_prefix, LPAD(NEW.seq_no, 5, '0'));
    END IF;
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-23 20:51:39
