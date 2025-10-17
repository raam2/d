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
-- Table structure for table `error_logs`
--

DROP TABLE IF EXISTS `error_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `error_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `occurred_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `page_name` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `context` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `error_logs`
--

LOCK TABLES `error_logs` WRITE;
/*!40000 ALTER TABLE `error_logs` DISABLE KEYS */;
INSERT INTO `error_logs` VALUES
(1,'2025-09-27 11:17:53','invoices/list','Parse Error in page: invoices/list','syntax error, unexpected identifier \"text\", expecting \",\" or \";\"'),
(2,'2025-09-27 11:17:58','invoices/view','Parse Error in page: invoices/view','syntax error, unexpected identifier \"color\", expecting \",\" or \";\"'),
(3,'2025-09-27 11:18:00','invoices/print','Parse Error in page: invoices/print','syntax error, unexpected identifier \"color\", expecting \",\" or \";\"'),
(4,'2025-09-27 11:18:02','invoices/post','Parse Error in page: invoices/post','syntax error, unexpected identifier \"color\", expecting \",\" or \";\"'),
(5,'2025-09-27 11:18:11','parties/master','Parse Error in page: parties/master','syntax error, unexpected identifier \"color\", expecting \",\" or \";\"'),
(6,'2025-09-27 11:18:21','tools/gst-summary','Parse Error in page: tools/gst-summary','syntax error, unexpected identifier \"text\", expecting \",\" or \";\"'),
(7,'2025-09-27 11:21:44','invoices/post','syntax error, unexpected identifier \"color\", expecting \",\" or \";\"','Page Parse Error'),
(8,'2025-09-27 11:21:47','invoices/list','syntax error, unexpected identifier \"text\", expecting \",\" or \";\"','Page Parse Error'),
(9,'2025-09-27 11:21:50','invoices/view','syntax error, unexpected identifier \"color\", expecting \",\" or \";\"','Page Parse Error'),
(10,'2025-09-27 11:33:03','invoices/list','syntax error, unexpected identifier \"text\", expecting \",\" or \";\"','Page Parse Error'),
(11,'2025-09-27 11:39:42','invoices/post','syntax error, unexpected identifier \"color\", expecting \",\" or \";\"','Page Parse Error'),
(12,'2025-09-27 11:40:02','invoices/list','syntax error, unexpected identifier \"text\", expecting \",\" or \";\"','Page Parse Error'),
(13,'2025-09-27 11:58:00','invoices/post','syntax error, unexpected identifier \"color\", expecting \",\" or \";\"','Parse Error'),
(14,'2025-09-27 12:12:43','invoices/post','Fixed syntax errors in page: invoices/post','Code Auto-Fixed'),
(15,'2025-09-27 12:13:01','dashboard','Fixed syntax errors in page: dashboard','Code Auto-Fixed'),
(16,'2025-09-27 12:13:08','invoices/post','Fixed syntax errors in page: invoices/post','Code Auto-Fixed'),
(17,'2025-09-27 12:13:12','invoices/view','Fixed syntax errors in page: invoices/view','Code Auto-Fixed'),
(18,'2025-09-27 12:13:22','invoices/view','Fixed syntax errors in page: invoices/view','Code Auto-Fixed'),
(19,'2025-09-27 12:13:29','dashboard','Fixed syntax errors in page: dashboard','Code Auto-Fixed'),
(20,'2025-09-27 12:13:33','parties/master','Fixed syntax errors in page: parties/master','Code Auto-Fixed'),
(21,'2025-09-27 12:13:47','dashboard','Fixed syntax errors in page: dashboard','Code Auto-Fixed'),
(22,'2025-09-27 16:51:08','invoices/post','syntax error, unexpected identifier \"alert\", expecting \",\" or \";\"','Parse Error');
/*!40000 ALTER TABLE `error_logs` ENABLE KEYS */;
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
