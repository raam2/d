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
-- Dumping routines for database 'gst_accounting'
--
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `HasChildren` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`gstwork`@`localhost` FUNCTION `HasChildren`(record_id INT) RETURNS tinyint(1)
    READS SQL DATA
    DETERMINISTIC
BEGIN
    DECLARE child_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO child_count 
    FROM hierarchical_records 
    WHERE parent_id = record_id AND status = 'active';
    
    RETURN child_count > 0;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `GetHierarchyPath` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`gstwork`@`localhost` PROCEDURE `GetHierarchyPath`(IN record_id INT)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE current_id INT DEFAULT record_id;
    DECLARE current_title VARCHAR(255);
    DECLARE current_parent INT;
    
    
    CREATE TEMPORARY TABLE IF NOT EXISTS temp_path (
        level_order INT AUTO_INCREMENT PRIMARY KEY,
        id INT,
        title VARCHAR(255),
        parent_id INT
    );
    
    
    DELETE FROM temp_path;
    
    
    path_loop: LOOP
        SELECT id, title, parent_id INTO current_id, current_title, current_parent
        FROM hierarchical_records 
        WHERE id = current_id;
        
        IF current_id IS NULL THEN
            LEAVE path_loop;
        END IF;
        
        INSERT INTO temp_path (id, title, parent_id) VALUES (current_id, current_title, current_parent);
        
        IF current_parent = 0 OR current_parent IS NULL THEN
            LEAVE path_loop;
        END IF;
        
        SET current_id = current_parent;
    END LOOP;
    
    
    SELECT id, title, parent_id, level_order 
    FROM temp_path 
    ORDER BY level_order DESC;
    
    DROP TEMPORARY TABLE temp_path;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `party_id` int(11) NOT NULL,
  `party_gstin` varchar(15) DEFAULT NULL,
  `party_name` varchar(120) DEFAULT NULL,
  `party_type` varchar(20) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
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
  UNIQUE KEY `uq_invoice_gstin_scope` (`series_code`,`inv_type`,`party_gstin`,`invoice_no`),
  UNIQUE KEY `uq_invoice_cash_scope` (`series_code`,`inv_type`,`invoice_no`,`party_name`,`party_type`,`city`),
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
/*!50003 CREATE*/ /*!50017 DEFINER=`gstwork`@`localhost`*/ /*!50003 TRIGGER bi_invoices_party_scope
BEFORE INSERT ON invoices
FOR EACH ROW
BEGIN
  DECLARE v_gstin VARCHAR(15);
  DECLARE v_name  VARCHAR(120);
  DECLARE v_type  VARCHAR(20);
  DECLARE v_city  VARCHAR(120);

  IF NEW.party_id IS NOT NULL THEN
    SELECT gstin, name, party_type, city
      INTO v_gstin, v_name, v_type, v_city
      FROM parties
      WHERE id = NEW.party_id
      LIMIT 1;

    SET NEW.party_gstin = v_gstin;
    SET NEW.party_name  = v_name;
    SET NEW.party_type  = v_type;
    SET NEW.city        = v_city;
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
/*!50003 CREATE*/ /*!50017 DEFINER=`gstwork`@`localhost`*/ /*!50003 TRIGGER bi_invoices_autoserial
BEFORE INSERT ON invoices
FOR EACH ROW
BEGIN
  DECLARE v_series VARCHAR(50);
  DECLARE v_type   ENUM('sale','purchase','credit_note','debit_note');
  DECLARE v_next   INT;
  DECLARE v_today  DATE;
  DECLARE v_year   INT;
  DECLARE v_month  INT;
  DECLARE v_fy     INT;
  DECLARE v_last_fy INT;

  SET v_series = NEW.series_code;
  SET v_type   = NEW.inv_type;
  SET v_today  = COALESCE(NEW.invoice_date, CURRENT_DATE());
  SET v_year   = YEAR(v_today);
  SET v_month  = MONTH(v_today);

  
  SET v_fy = IF(v_month >= 4, v_year, v_year - 1);

  
  SELECT current_no, last_reset_fy
    INTO v_next, v_last_fy
    FROM invoice_series
    WHERE series_code = v_series
    FOR UPDATE;

  
  IF v_last_fy IS NULL OR v_last_fy <> v_fy THEN
    UPDATE invoice_series
       SET current_no = 0, last_reset_fy = v_fy
     WHERE series_code = v_series;
    SET v_next = 0;
  END IF;

  
  SET v_next = v_next + 1;
  UPDATE invoice_series
     SET current_no = v_next
   WHERE series_code = v_series;

  
  SET NEW.seq_no     = v_next;
  SET NEW.invoice_no = CONCAT(v_series, LPAD(v_next, 5, '0'));
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
/*!50003 CREATE*/ /*!50017 DEFINER=`gstwork`@`localhost`*/ /*!50003 TRIGGER bu_invoices_party_scope
BEFORE UPDATE ON invoices
FOR EACH ROW
BEGIN
  DECLARE v_gstin VARCHAR(15);
  DECLARE v_name  VARCHAR(120);
  DECLARE v_type  VARCHAR(20);
  DECLARE v_city  VARCHAR(120);

  IF NEW.party_id IS NOT NULL AND NEW.party_id <> OLD.party_id THEN
    SELECT gstin, name, party_type, city
      INTO v_gstin, v_name, v_type, v_city
      FROM parties
      WHERE id = NEW.party_id
      LIMIT 1;

    SET NEW.party_gstin = v_gstin;
    SET NEW.party_name  = v_name;
    SET NEW.party_type  = v_type;
    SET NEW.city        = v_city;
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

-- Dump completed on 2025-09-28  8:11:50
