/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSS_Files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `CSS_Files` VALUES
(1,'invoice_form','form { max-width: 800px; margin: 20px auto; padding: 15px; border: 1px solid #ccc; background: #fafafa; }\nform label { display: block; margin: 8px 0; }\n#items div { margin-bottom: 6px; padding: 6px; border: 1px dashed #aaa; }\nbutton.add-row, button.remove-row { margin-left: 10px; }');
