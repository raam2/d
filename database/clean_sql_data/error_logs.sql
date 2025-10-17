/*M!999999\- enable the sandbox mode */ 
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
