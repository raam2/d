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
-- Table structure for table `gst_rate_rules`
--

DROP TABLE IF EXISTS `gst_rate_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gst_rate_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `priority` int(11) NOT NULL DEFAULT 100,
  `hsn_chapter` varchar(4) DEFAULT NULL,
  `hsn_start` varchar(10) DEFAULT NULL,
  `hsn_end` varchar(10) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `condition_type` enum('NONE','VALUE_PER_PCS_LE','PREPACKED_LABELLED') NOT NULL DEFAULT 'NONE',
  `threshold_value` decimal(10,2) DEFAULT NULL,
  `intra_rate_percent` decimal(5,2) NOT NULL,
  `inter_rate_percent` decimal(5,2) NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `notification_ref` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rules_range` (`hsn_chapter`,`hsn_start`,`hsn_end`),
  KEY `idx_rules_eff` (`effective_from`,`effective_to`),
  KEY `idx_gst_rules_hsn` (`hsn_chapter`),
  KEY `idx_gst_rules_effective` (`effective_from`,`effective_to`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gst_rate_rules`
--

LOCK TABLES `gst_rate_rules` WRITE;
/*!40000 ALTER TABLE `gst_rate_rules` DISABLE KEYS */;
INSERT INTO `gst_rate_rules` VALUES
(1,10,'61',NULL,NULL,'Apparel (knitted) ≤ ₹1000/pcs','VALUE_PER_PCS_LE',1000.00,5.00,5.00,'2025-04-01',NULL,'CBIC'),
(2,10,'62',NULL,NULL,'Apparel (not knitted) ≤ ₹1000/pcs','VALUE_PER_PCS_LE',1000.00,5.00,5.00,'2025-04-01',NULL,'CBIC'),
(3,11,'61',NULL,NULL,'Apparel (knitted) > ₹1000/pcs','NONE',NULL,12.00,12.00,'2025-04-01',NULL,'CBIC'),
(4,11,'62',NULL,NULL,'Apparel (not knitted) > ₹1000/pcs','NONE',NULL,12.00,12.00,'2025-04-01',NULL,'CBIC'),
(5,12,'6501',NULL,NULL,'Headgear ≤ ₹1000/pcs','VALUE_PER_PCS_LE',1000.00,5.00,5.00,'2025-04-01',NULL,'CBIC'),
(6,12,'6505',NULL,NULL,'Headgear ≤ ₹1000/pcs','VALUE_PER_PCS_LE',1000.00,5.00,5.00,'2025-04-01',NULL,'CBIC'),
(7,13,'6501',NULL,NULL,'Headgear > ₹1000/pcs','NONE',NULL,12.00,12.00,'2025-04-01',NULL,'CBIC'),
(8,13,'6505',NULL,NULL,'Headgear > ₹1000/pcs','NONE',NULL,12.00,12.00,'2025-04-01',NULL,'CBIC'),
(9,20,'10',NULL,NULL,'Cereals – pre-packaged & labelled','PREPACKED_LABELLED',NULL,5.00,5.00,'2025-04-01',NULL,'2022-07-18'),
(10,21,'11',NULL,NULL,'Flours/Suji – pre-packaged & labelled','PREPACKED_LABELLED',NULL,5.00,5.00,'2025-04-01',NULL,'2022-07-18'),
(11,22,'07',NULL,NULL,'Pulses (0713) – pre-packaged & labelled','PREPACKED_LABELLED',NULL,5.00,5.00,'2025-04-01',NULL,'2022-07-18'),
(12,23,'17',NULL,NULL,'Jaggery (1701) – pre-packaged & labelled','PREPACKED_LABELLED',NULL,5.00,5.00,'2025-04-01',NULL,'2022-07-18'),
(19,10,'61',NULL,NULL,'Apparel knitted per‑piece ≤ ₹1000','VALUE_PER_PCS_LE',1000.00,5.00,5.00,'2025-04-01',NULL,'APPAREL_5_12_2025APR'),
(20,10,'62',NULL,NULL,'Apparel non‑knitted per‑piece ≤ ₹1000','VALUE_PER_PCS_LE',1000.00,5.00,5.00,'2025-04-01',NULL,'APPAREL_5_12_2025APR'),
(21,11,'61',NULL,NULL,'Apparel knitted per‑piece > ₹1000','NONE',NULL,12.00,12.00,'2025-04-01',NULL,'APPAREL_5_12_2025APR'),
(22,11,'62',NULL,NULL,'Apparel non‑knitted per‑piece > ₹1000','NONE',NULL,12.00,12.00,'2025-04-01',NULL,'APPAREL_5_12_2025APR'),
(23,12,'6505',NULL,NULL,'Headgear per‑piece ≤ ₹1000','VALUE_PER_PCS_LE',1000.00,5.00,5.00,'2025-04-01',NULL,'APPAREL_5_12_2025APR'),
(24,13,'6505',NULL,NULL,'Headgear per‑piece > ₹1000','NONE',NULL,12.00,12.00,'2025-04-01',NULL,'APPAREL_5_12_2025APR');
/*!40000 ALTER TABLE `gst_rate_rules` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-28  8:17:59
