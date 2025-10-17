/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `party_id` int(11) NOT NULL,
  `pay_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `mode` enum('cash','bank','upi','card','other') NOT NULL DEFAULT 'cash',
  `ref_no` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pay_party_date` (`party_id`,`pay_date`),
  CONSTRAINT `fk_pay_party` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
