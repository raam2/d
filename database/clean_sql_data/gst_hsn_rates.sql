/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gst_hsn_rates` (
  `hsn` varchar(10) NOT NULL,
  `gst_rate` decimal(5,2) NOT NULL,
  PRIMARY KEY (`hsn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `gst_hsn_rates` VALUES
('3401',18.00),
('3406',12.00),
('3506',18.00),
('3605',18.00),
('3919',18.00),
('3924',18.00),
('3926',18.00),
('4016',12.00),
('4820',12.00),
('5204',12.00),
('6302',12.00),
('7319',18.00),
('7323',12.00),
('7326',18.00),
('8213',18.00),
('8214',18.00),
('9603',18.00),
('9606',18.00),
('9608',12.00),
('9609',12.00),
('9615',18.00);
