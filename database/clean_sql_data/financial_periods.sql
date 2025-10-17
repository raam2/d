/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period_name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_closed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_period` (`start_date`,`end_date`),
  KEY `idx_active` (`is_active`),
  KEY `idx_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Financial reporting periods';
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `financial_periods` VALUES
(1,'Q1 2025','2025-01-01','2025-03-31',1,0,'2025-09-22 14:32:00'),
(2,'Q2 2025','2025-04-01','2025-06-30',1,0,'2025-09-22 14:32:00'),
(3,'Q3 2025','2025-07-01','2025-09-30',1,0,'2025-09-22 14:32:00'),
(4,'Q4 2025','2025-10-01','2025-12-31',1,0,'2025-09-22 14:32:00'),
(5,'FY 2025','2025-04-01','2026-03-31',1,0,'2025-09-22 14:32:01');
