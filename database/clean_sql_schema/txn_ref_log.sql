/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `txn_ref_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ref_no` varchar(50) NOT NULL,
  `source` varchar(20) DEFAULT NULL,
  `txn_date` date DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_txn_ref` (`ref_no`,`txn_date`,`amount`,`source`)
) ENGINE=InnoDB AUTO_INCREMENT=313 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
