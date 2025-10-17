/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `diagnostics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `level` enum('INFO','WARN','ERROR') NOT NULL DEFAULT 'ERROR',
  `message` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `diagnostics` VALUES
(1,'2025-09-27 11:17:47','INFO','Page accessed: dashboard'),
(2,'2025-09-27 11:17:53','INFO','Page accessed: invoices/list'),
(3,'2025-09-27 11:17:58','INFO','Page accessed: invoices/view'),
(4,'2025-09-27 11:18:00','INFO','Page accessed: invoices/print'),
(5,'2025-09-27 11:18:02','INFO','Page accessed: invoices/post'),
(6,'2025-09-27 11:18:07','INFO','Page accessed: dashboard'),
(7,'2025-09-27 11:18:11','INFO','Page accessed: parties/master'),
(8,'2025-09-27 11:18:14','INFO','Page accessed: tools/gst-rules-audit'),
(9,'2025-09-27 11:18:16','INFO','Page accessed: tools/manual-refresh-reminder'),
(10,'2025-09-27 11:18:21','INFO','Page accessed: tools/gst-summary'),
(11,'2025-09-27 11:18:23','INFO','Page accessed: tools/diagnostics'),
(12,'2025-09-27 11:39:39','INFO','Page accessed: dashboard by IP: 10.160.118.1'),
(13,'2025-09-27 11:39:39','INFO','Page \'dashboard\' loaded successfully'),
(14,'2025-09-27 11:39:42','INFO','Page accessed: invoices/post by IP: 10.160.118.1'),
(15,'2025-09-27 11:39:42','INFO','Page \'invoices/post\' loaded successfully'),
(16,'2025-09-27 11:40:02','INFO','Page accessed: invoices/list by IP: 10.160.118.1'),
(17,'2025-09-27 11:40:02','INFO','Page \'invoices/list\' loaded successfully'),
(18,'2025-09-27 11:57:52','INFO','Page accessed: dashboard'),
(19,'2025-09-27 11:57:52','INFO','Executing page: dashboard'),
(20,'2025-09-27 11:58:00','INFO','Page accessed: invoices/post'),
(21,'2025-09-27 11:58:00','INFO','Executing page: invoices/post'),
(22,'2025-09-27 16:47:27','INFO','Database syntax errors fixed via SQL update script'),
(23,'2025-09-27 16:51:23','INFO','Database syntax errors fixed via SQL update script');
