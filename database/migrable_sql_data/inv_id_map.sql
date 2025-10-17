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
-- Table structure for table `inv_id_map`
--

DROP TABLE IF EXISTS `inv_id_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_id_map` (
  `old_id` int(11) NOT NULL,
  `new_id` int(11) NOT NULL,
  PRIMARY KEY (`old_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inv_id_map`
--

LOCK TABLES `inv_id_map` WRITE;
/*!40000 ALTER TABLE `inv_id_map` DISABLE KEYS */;
INSERT INTO `inv_id_map` VALUES
(2,122),
(3,116),
(4,124),
(5,125),
(6,126),
(7,127),
(8,128),
(9,129),
(10,130),
(11,131),
(12,132),
(13,133),
(14,134),
(15,135),
(16,138),
(17,136),
(18,137),
(19,139),
(20,140),
(21,141),
(22,142),
(23,144),
(24,145),
(25,143),
(1639586,1),
(1639587,2),
(1639588,3),
(1639589,4),
(1639590,5),
(1639591,6),
(1639592,7),
(1639593,8),
(1639594,9),
(1639595,10),
(1639596,11),
(1639597,12),
(1639598,13),
(1639599,14),
(1639600,15),
(1639601,16),
(1639602,17),
(1639603,18),
(1639604,19),
(1639605,20),
(1639606,21),
(1639607,22),
(1639608,23),
(1639609,24),
(1639610,25),
(1639611,26),
(1639612,27),
(1639613,28),
(1639614,29),
(1639615,30),
(1639616,31),
(1639617,32),
(1639618,33),
(1639619,34),
(1639620,35),
(1639621,36),
(1639622,37),
(1639623,38),
(1639624,39),
(1639625,40),
(1639626,41),
(1639627,42),
(1639628,43),
(1639629,44),
(1639630,45),
(1639631,46),
(1639632,47),
(1639633,48),
(1639634,49),
(1639635,50),
(1639636,51),
(1639637,52),
(1639638,53),
(1639639,54),
(1639640,55),
(1639641,56),
(1639642,57),
(1639643,58),
(1639644,59),
(1639645,60),
(1639646,61),
(1639647,62),
(1639648,63),
(1639649,64),
(1639650,65),
(1639651,66),
(1639652,67),
(1639653,68),
(1639654,69),
(1639655,70),
(1639656,71),
(1639657,72),
(1639658,73),
(1639659,74),
(1639660,75),
(1639661,76),
(1639662,77),
(1639663,78),
(1639664,79),
(1639665,80),
(1639666,81),
(1639667,82),
(1639668,83),
(1639669,84),
(1639670,85),
(1639671,86),
(1639672,87),
(1639673,88),
(1639674,89),
(1639675,90),
(1639676,91),
(1639677,92),
(1639678,93),
(1639679,94),
(1639680,95),
(1639681,96),
(1639682,97),
(1639683,98),
(1639684,99),
(1639685,100),
(1639686,101),
(1639687,102),
(1639688,103),
(1639689,104),
(1639690,105),
(1639691,106),
(1639692,107),
(1639693,108),
(1639694,109),
(1639695,110),
(1639696,111),
(1639697,112),
(1639698,113),
(1639699,114),
(1639700,115),
(1639701,117),
(1639702,118),
(1639703,119),
(1639704,120),
(1639705,121),
(1689711,123);
/*!40000 ALTER TABLE `inv_id_map` ENABLE KEYS */;
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
