/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `party_id` int(11) NOT NULL,
  `party_gstin` varchar(15) DEFAULT NULL,
  `party_name` varchar(120) DEFAULT NULL,
  `party_type` varchar(20) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `inv_type` enum('sale','purchase','credit_note','debit_note') NOT NULL,
  `series_code` varchar(20) DEFAULT NULL,
  `seq_no` int(11) DEFAULT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `external_sales_ref_no` varchar(100) DEFAULT NULL,
  `external_supplier_invoice_no` varchar(100) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `place_of_supply` varchar(50) DEFAULT NULL,
  `reverse_charge` tinyint(1) DEFAULT 0,
  `status` enum('draft','final','cancelled') DEFAULT 'draft',
  `itc_eligible` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoice_gstin_scope` (`series_code`,`inv_type`,`party_gstin`,`invoice_no`),
  UNIQUE KEY `uq_invoice_cash_scope` (`series_code`,`inv_type`,`invoice_no`,`party_name`,`party_type`,`city`),
  KEY `idx_inv_date` (`invoice_date`),
  KEY `idx_inv_type` (`inv_type`),
  KEY `idx_inv_party` (`party_id`),
  KEY `idx_invoice_date` (`invoice_date`),
  KEY `idx_invoice_no` (`invoice_no`),
  KEY `idx_invoice_status` (`status`),
  KEY `idx_invoice_type` (`inv_type`),
  KEY `idx_invoice_party` (`party_id`),
  CONSTRAINT `fk_newinv_party` FOREIGN KEY (`party_id`) REFERENCES `parties` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=336 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`gstwork`@`localhost`*/ /*!50003 TRIGGER trg_generate_purchase_invno
BEFORE INSERT ON invoices
FOR EACH ROW
BEGIN
  IF NEW.inv_type='purchase' AND NEW.party_id=4 THEN
    SET NEW.invoice_no = CONCAT(
        'URD-', DATE_FORMAT(NEW.invoice_date,'%Y%m%d'), '-',
        LPAD(
          IFNULL(
            (
              SELECT MAX(CAST(SUBSTRING_INDEX(invoice_no, '-', -1) AS UNSIGNED))
              FROM invoices
              WHERE inv_type='purchase'
                AND party_id = 4
                AND DATE(invoice_date) = DATE(NEW.invoice_date)
            ), 0
          ) + 1,
          3, '0'
        )
    );
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`gstwork`@`localhost`*/ /*!50003 TRIGGER bi_invoices_party_scope
BEFORE INSERT ON invoices
FOR EACH ROW
BEGIN
  DECLARE v_gstin VARCHAR(15);
  DECLARE v_name  VARCHAR(120);
  DECLARE v_type  VARCHAR(20);
  DECLARE v_city  VARCHAR(120);

  IF NEW.party_id IS NOT NULL THEN
    SELECT gstin, name, party_type, city
      INTO v_gstin, v_name, v_type, v_city
      FROM parties
      WHERE id = NEW.party_id
      LIMIT 1;

    SET NEW.party_gstin = v_gstin;
    SET NEW.party_name  = v_name;
    SET NEW.party_type  = v_type;
    SET NEW.city        = v_city;
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`gstwork`@`localhost`*/ /*!50003 TRIGGER bi_invoices_autoserial
BEFORE INSERT ON invoices
FOR EACH ROW
BEGIN
  DECLARE v_series VARCHAR(50);
  DECLARE v_type   ENUM('sale','purchase','credit_note','debit_note');
  DECLARE v_next   INT;
  DECLARE v_today  DATE;
  DECLARE v_year   INT;
  DECLARE v_month  INT;
  DECLARE v_fy     INT;
  DECLARE v_last_fy INT;

  SET v_series = NEW.series_code;
  SET v_type   = NEW.inv_type;
  SET v_today  = COALESCE(NEW.invoice_date, CURRENT_DATE());
  SET v_year   = YEAR(v_today);
  SET v_month  = MONTH(v_today);

  
  SET v_fy = IF(v_month >= 4, v_year, v_year - 1);

  
  SELECT current_no, last_reset_fy
    INTO v_next, v_last_fy
    FROM invoice_series
    WHERE series_code = v_series
    FOR UPDATE;

  
  IF v_last_fy IS NULL OR v_last_fy <> v_fy THEN
    UPDATE invoice_series
       SET current_no = 0, last_reset_fy = v_fy
     WHERE series_code = v_series;
    SET v_next = 0;
  END IF;

  
  SET v_next = v_next + 1;
  UPDATE invoice_series
     SET current_no = v_next
   WHERE series_code = v_series;

  
  SET NEW.seq_no     = v_next;
  SET NEW.invoice_no = CONCAT(v_series, LPAD(v_next, 5, '0'));
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb3 */ ;
/*!50003 SET character_set_results = utf8mb3 */ ;
/*!50003 SET collation_connection  = utf8mb3_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`gstwork`@`localhost`*/ /*!50003 TRIGGER bu_invoices_party_scope
BEFORE UPDATE ON invoices
FOR EACH ROW
BEGIN
  DECLARE v_gstin VARCHAR(15);
  DECLARE v_name  VARCHAR(120);
  DECLARE v_type  VARCHAR(20);
  DECLARE v_city  VARCHAR(120);

  IF NEW.party_id IS NOT NULL AND NEW.party_id <> OLD.party_id THEN
    SELECT gstin, name, party_type, city
      INTO v_gstin, v_name, v_type, v_city
      FROM parties
      WHERE id = NEW.party_id
      LIMIT 1;

    SET NEW.party_gstin = v_gstin;
    SET NEW.party_name  = v_name;
    SET NEW.party_type  = v_type;
    SET NEW.city        = v_city;
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
