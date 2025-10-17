/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `organization_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `org_name` varchar(255) NOT NULL DEFAULT 'My Organization',
  `org_state_code` varchar(2) NOT NULL DEFAULT 'DL' COMMENT 'State code for GST classification',
  `org_gstin` varchar(15) DEFAULT NULL,
  `org_address` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Organization configuration for GST';
/*!40101 SET character_set_client = @saved_cs_client */;
