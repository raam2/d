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
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `account_type` enum('ASSET','LIABILITY','EQUITY','INCOME','EXPENSE') NOT NULL,
  `parent_code` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`),
  KEY `idx_type` (`account_type`),
  KEY `idx_parent` (`parent_code`),
  KEY `idx_accounts_active` (`is_active`),
  CONSTRAINT `accounts_ibfk_1` FOREIGN KEY (`parent_code`) REFERENCES `accounts` (`code`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Chart of Accounts';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounts`
--

LOCK TABLES `accounts` WRITE;
/*!40000 ALTER TABLE `accounts` DISABLE KEYS */;
INSERT INTO `accounts` VALUES
(1,'1000','CURRENT ASSETS','ASSET',NULL,1,'Current Assets','2025-09-19 07:26:58','2025-09-19 07:26:58'),
(2,'1010','Bank Account - Main','ASSET','1000',1,'Primary bank account','2025-09-19 07:26:58','2025-09-19 07:26:58'),
(3,'1020','Bank Account - Secondary','ASSET','1000',1,'Secondary bank account','2025-09-19 07:26:58','2025-09-19 07:26:58'),
(4,'1100','Accounts Receivable','ASSET','1000',1,'Money owed by customers','2025-09-19 07:26:58','2025-09-19 07:26:58'),
(5,'1200','Inventory','ASSET','1000',1,'Goods for sale','2025-09-19 07:26:58','2025-09-19 07:26:58'),
(6,'1300','Prepaid Expenses','ASSET','1000',1,'Prepaid expenses','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(7,'1400','GST INPUT ACCOUNTS','ASSET','1000',1,'GST paid on purchases','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(8,'1401','CGST Input','ASSET','1400',1,'Central GST paid','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(9,'1402','SGST Input','ASSET','1400',1,'State GST paid','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(10,'1403','IGST Input','ASSET','1400',1,'Integrated GST paid','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(11,'1404','CESS Input','ASSET','1400',1,'Cess paid','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(12,'1500','FIXED ASSETS','ASSET',NULL,1,'Fixed Assets','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(13,'1510','Plant & Machinery','ASSET','1500',1,'Plant and machinery','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(14,'1520','Furniture & Fixtures','ASSET','1500',1,'Furniture and fixtures','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(15,'1530','Computer Equipment','ASSET','1500',1,'Computer equipment','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(16,'2000','CURRENT LIABILITIES','LIABILITY',NULL,1,'Current Liabilities','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(17,'2100','Accounts Payable','LIABILITY','2000',1,'Money owed to suppliers','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(18,'2200','Accrued Expenses','LIABILITY','2000',1,'Accrued expenses','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(19,'2300','GST OUTPUT ACCOUNTS','LIABILITY','2000',1,'GST collected on sales','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(20,'2301','CGST Output','LIABILITY','2300',1,'Central GST collected','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(21,'2302','SGST Output','LIABILITY','2300',1,'State GST collected','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(22,'2303','IGST Output','LIABILITY','2300',1,'Integrated GST collected','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(23,'2304','CESS Output','LIABILITY','2300',1,'Cess collected','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(24,'2500','LONG TERM LIABILITIES','LIABILITY',NULL,1,'Long-term Liabilities','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(25,'2510','Bank Loans','LIABILITY','2500',1,'Bank loans','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(26,'3000','EQUITY','EQUITY',NULL,1,'Owner\'s Equity','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(27,'3100','Capital','EQUITY','3000',1,'Owner\'s capital','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(28,'3200','Retained Earnings','EQUITY','3000',1,'Retained earnings','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(29,'3300','Drawings','EQUITY','3000',1,'Owner withdrawals','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(30,'4000','SALES REVENUE','INCOME',NULL,1,'Sales Revenue','2025-09-19 07:26:59','2025-09-19 07:26:59'),
(31,'4100','Sales - Domestic','INCOME','4000',1,'Domestic sales','2025-09-19 07:27:00','2025-09-19 07:27:00'),
(32,'4200','Sales - Export','INCOME','4000',1,'Export sales','2025-09-19 07:27:00','2025-09-19 07:27:00'),
(33,'4300','Other Income','INCOME','4000',1,'Miscellaneous income','2025-09-19 07:27:00','2025-09-19 07:27:00'),
(34,'5000','COST OF GOODS SOLD','EXPENSE',NULL,1,'Cost of Goods Sold','2025-09-19 07:27:00','2025-09-19 07:27:00'),
(35,'5100','Purchases','EXPENSE','5000',1,'Purchase of goods','2025-09-19 07:27:00','2025-09-19 07:27:00'),
(36,'5200','Direct Labor','EXPENSE','5000',1,'Direct labor costs','2025-09-19 07:27:00','2025-09-19 07:27:00'),
(37,'5300','Manufacturing Overhead','EXPENSE','5000',1,'Manufacturing overhead','2025-09-19 07:27:00','2025-09-19 07:27:00'),
(38,'6000','OPERATING EXPENSES','EXPENSE',NULL,1,'Operating Expenses','2025-09-19 07:27:00','2025-09-19 07:27:00'),
(39,'6100','Salaries & Wages','EXPENSE','6000',1,'Employee salaries','2025-09-19 07:27:00','2025-09-19 07:27:00'),
(40,'6200','Rent Expense','EXPENSE','6000',1,'Office/factory rent','2025-09-19 07:27:00','2025-09-19 07:27:00'),
(41,'6300','Utilities','EXPENSE','6000',1,'Electricity, water, etc.','2025-09-19 07:27:00','2025-09-19 07:27:00'),
(42,'6400','Transportation','EXPENSE','6000',1,'Transportation costs','2025-09-19 07:27:00','2025-09-19 07:27:00'),
(43,'6500','Professional Services','EXPENSE','6000',1,'Legal, audit, consulting','2025-09-19 07:27:00','2025-09-19 07:27:00'),
(44,'6600','Insurance','EXPENSE','6000',1,'Insurance premiums','2025-09-19 07:27:00','2025-09-19 07:27:00'),
(45,'6700','Depreciation','EXPENSE','6000',1,'Asset depreciation','2025-09-19 07:27:00','2025-09-19 07:27:00'),
(46,'6800','Bank Charges','EXPENSE','6000',1,'Bank fees and charges','2025-09-19 07:27:00','2025-09-19 07:27:00'),
(47,'6900','Miscellaneous Expenses','EXPENSE','6000',1,'Other expenses','2025-09-19 07:27:00','2025-09-19 07:27:00');
/*!40000 ALTER TABLE `accounts` ENABLE KEYS */;
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
