/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
DROP TABLE IF EXISTS `CSS_Files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSS_Files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `CSS_History`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `CSS_History` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `css_id` int(11) NOT NULL,
  `version_no` int(11) NOT NULL,
  `code` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `css_id` (`css_id`,`version_no`),
  CONSTRAINT `CSS_History_ibfk_1` FOREIGN KEY (`css_id`) REFERENCES `CSS_Files` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `JS_Files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `JS_Files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `JS_History`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `JS_History` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `js_id` int(11) NOT NULL,
  `version_no` int(11) NOT NULL,
  `code` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `js_id` (`js_id`,`version_no`),
  CONSTRAINT `JS_History_ibfk_1` FOREIGN KEY (`js_id`) REFERENCES `JS_Files` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `Page_CSS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Page_CSS` (
  `page_id` int(11) NOT NULL,
  `css_id` int(11) NOT NULL,
  PRIMARY KEY (`page_id`,`css_id`),
  KEY `css_id` (`css_id`),
  CONSTRAINT `Page_CSS_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `Pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `Page_CSS_ibfk_2` FOREIGN KEY (`css_id`) REFERENCES `CSS_Files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `Page_History`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Page_History` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `version_no` int(11) NOT NULL,
  `code` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_id` (`page_id`,`version_no`),
  CONSTRAINT `Page_History_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `Pages` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `Page_JS`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Page_JS` (
  `page_id` int(11) NOT NULL,
  `js_id` int(11) NOT NULL,
  PRIMARY KEY (`page_id`,`js_id`),
  KEY `js_id` (`js_id`),
  CONSTRAINT `Page_JS_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `Pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `Page_JS_ibfk_2` FOREIGN KEY (`js_id`) REFERENCES `JS_Files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `Pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `Pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `menu_label` varchar(100) DEFAULT NULL,
  `menu_group` varchar(100) DEFAULT NULL,
  `menu_order` int(11) DEFAULT 0,
  `code` mediumtext DEFAULT NULL,
  `tables_used` varchar(500) DEFAULT NULL COMMENT 'Comma-separated list of transaction tables this page interacts with',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `account_type` enum('ASSET','LIABILITY','EQUITY','INCOME','EXPENSE') NOT NULL,
  `parent_code` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`),
  KEY `idx_type` (`account_type`),
  KEY `idx_parent` (`parent_code`),
  KEY `idx_accounts_active` (`is_active`),
  CONSTRAINT `accounts_ibfk_1` FOREIGN KEY (`parent_code`) REFERENCES `accounts` (`code`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Chart of Accounts';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_reconciliation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_reconciliation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_account_code` varchar(20) NOT NULL,
  `statement_date` date NOT NULL,
  `statement_balance` decimal(18,2) NOT NULL,
  `book_balance` decimal(18,2) NOT NULL,
  `reconciled_balance` decimal(18,2) NOT NULL,
  `status` enum('DRAFT','RECONCILED') DEFAULT 'DRAFT',
  `reconciled_at` timestamp NULL DEFAULT NULL,
  `reconciled_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_account_date` (`bank_account_code`,`statement_date`),
  CONSTRAINT `bank_reconciliation_ibfk_1` FOREIGN KEY (`bank_account_code`) REFERENCES `accounts` (`code`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Bank Reconciliation Headers';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_reconciliation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_reconciliation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reconciliation_id` int(11) NOT NULL,
  `journal_line_id` int(11) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `type` enum('DEPOSIT','WITHDRAWAL','OUTSTANDING_DEPOSIT','OUTSTANDING_WITHDRAWAL') NOT NULL,
  `is_reconciled` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_reconciliation` (`reconciliation_id`),
  KEY `idx_journal_line` (`journal_line_id`),
  CONSTRAINT `bank_reconciliation_items_ibfk_1` FOREIGN KEY (`reconciliation_id`) REFERENCES `bank_reconciliation` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_reconciliation_items_ibfk_2` FOREIGN KEY (`journal_line_id`) REFERENCES `journal_lines` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Bank Reconciliation Items';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_statement_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_statement_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `statement_id` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `debit_amount` decimal(15,2) DEFAULT 0.00,
  `credit_amount` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `is_reconciled` tinyint(1) DEFAULT 0,
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stmt_date` (`statement_id`,`transaction_date`),
  KEY `idx_reconciled` (`is_reconciled`),
  KEY `idx_journal` (`journal_entry_id`),
  CONSTRAINT `bank_statement_lines_ibfk_1` FOREIGN KEY (`statement_id`) REFERENCES `bank_statements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bank statement transaction lines';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_statements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_statements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_code` varchar(20) NOT NULL,
  `statement_date` date NOT NULL,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `closing_balance` decimal(15,2) NOT NULL,
  `total_credits` decimal(15,2) DEFAULT 0.00,
  `total_debits` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','reconciled','partial') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_statement` (`account_code`,`statement_date`),
  KEY `idx_account_date` (`account_code`,`statement_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bank statement headers';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `diagnostics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `diagnostics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `level` enum('INFO','WARN','ERROR') NOT NULL DEFAULT 'ERROR',
  `message` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `error_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `error_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `occurred_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `page_name` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `context` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `financial_periods`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Financial reporting periods';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gst_hsn_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gst_hsn_rates` (
  `hsn` varchar(10) NOT NULL,
  `gst_rate` decimal(5,2) NOT NULL,
  PRIMARY KEY (`hsn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gst_rate_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gst_rate_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `priority` int(11) NOT NULL DEFAULT 100,
  `hsn_chapter` varchar(4) DEFAULT NULL,
  `hsn_start` varchar(10) DEFAULT NULL,
  `hsn_end` varchar(10) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `condition_type` enum('NONE','VALUE_PER_PCS_LE','PREPACKED_LABELLED') NOT NULL DEFAULT 'NONE',
  `threshold_value` decimal(10,2) DEFAULT NULL,
  `intra_rate_percent` decimal(5,2) NOT NULL,
  `inter_rate_percent` decimal(5,2) NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `notification_ref` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rules_range` (`hsn_chapter`,`hsn_start`,`hsn_end`),
  KEY `idx_rules_eff` (`effective_from`,`effective_to`),
  KEY `idx_gst_rules_hsn` (`hsn_chapter`),
  KEY `idx_gst_rules_effective` (`effective_from`,`effective_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gst_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gst_rates` (
  `id` tinyint(4) NOT NULL AUTO_INCREMENT,
  `cgst` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sgst` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst` decimal(5,2) NOT NULL DEFAULT 0.00,
  `total_rate` decimal(5,2) GENERATED ALWAYS AS (`cgst` + `sgst` + `igst`) STORED,
  PRIMARY KEY (`id`),
  KEY `idx_gst_rates_total` (`total_rate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inv_id_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inv_id_map` (
  `old_id` int(11) NOT NULL,
  `new_id` int(11) NOT NULL,
  PRIMARY KEY (`old_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `movement_type` enum('IN','OUT','ADJUSTMENT') NOT NULL,
  `movement_date` date NOT NULL,
  `qty` decimal(12,3) NOT NULL,
  `unit_cost` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `source_type` varchar(50) DEFAULT NULL COMMENT 'invoice, adjustment, etc',
  `source_id` int(11) DEFAULT NULL COMMENT 'Reference to source document',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_item_date` (`item_id`,`movement_date`),
  KEY `idx_source` (`source_type`,`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory movement tracking';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_valuations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_valuations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `valuation_date` date NOT NULL,
  `qty_on_hand` decimal(12,3) NOT NULL DEFAULT 0.000,
  `unit_cost` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `valuation_method` enum('FIFO','LIFO','WEIGHTED_AVERAGE') DEFAULT 'WEIGHTED_AVERAGE',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_item_date` (`item_id`,`valuation_date`),
  KEY `idx_item_date` (`item_id`,`valuation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory valuations for COGS calculation';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `description_en` varchar(255) DEFAULT NULL,
  `hsn` varchar(10) DEFAULT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT 1.000,
  `rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `rate` * `discount_percent` / 100) STORED,
  `taxable_amount` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `rate` - `quantity` * `rate` * `discount_percent` / 100) STORED,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(10,2) GENERATED ALWAYS AS (`taxable_amount` * `cgst_rate` / 100) STORED,
  `sgst_amount` decimal(10,2) GENERATED ALWAYS AS (`taxable_amount` * `sgst_rate` / 100) STORED,
  `igst_amount` decimal(10,2) GENERATED ALWAYS AS (`taxable_amount` * `igst_rate` / 100) STORED,
  `line_total` decimal(12,2) GENERATED ALWAYS AS (`taxable_amount` + `cgst_amount` + `sgst_amount` + `igst_amount`) STORED,
  `itc_eligible` tinyint(1) NOT NULL DEFAULT 1,
  `is_prepackaged_labelled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_item_id` (`item_id`),
  KEY `idx_item_invoice` (`invoice_id`),
  KEY `idx_item_hsn` (`hsn`),
  KEY `idx_item_rate` (`rate`),
  KEY `idx_item_description` (`description`),
  FULLTEXT KEY `description` (`description`),
  FULLTEXT KEY `description_2` (`description`),
  CONSTRAINT `fk_newiitems_inv` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_newiitems_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_series`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_series` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inv_type` varchar(20) NOT NULL,
  `series_code` varchar(20) NOT NULL,
  `current_no` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `last_reset_fy` year(4) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `canonical_name` varchar(255) NOT NULL,
  `hsn` varchar(10) DEFAULT NULL,
  `is_SSC_PAYABLE` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_prepackaged_labelled` tinyint(1) NOT NULL DEFAULT 0,
  `track_cogs` tinyint(1) DEFAULT 1 COMMENT 'Track COGS for this item',
  `inventory_account` varchar(20) DEFAULT '1200' COMMENT 'Inventory GL account',
  `cogs_account` varchar(20) DEFAULT '5100' COMMENT 'COGS GL account',
  PRIMARY KEY (`id`),
  KEY `idx_item_name` (`canonical_name`),
  KEY `idx_item_hsn_code` (`hsn`),
  FULLTEXT KEY `ft_item_name` (`canonical_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `source_type` varchar(50) DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` varchar(100) DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'posted',
  PRIMARY KEY (`id`),
  KEY `idx_company_date` (`entry_date`),
  KEY `idx_journal_source` (`source_type`,`source_id`),
  CONSTRAINT `chk_journal_status` CHECK (`status` in ('draft','posted','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) NOT NULL,
  `account_type` enum('ledger','bank') NOT NULL,
  `party_id` int(11) DEFAULT NULL,
  `account_code` varchar(100) DEFAULT NULL,
  `debit_amount` decimal(12,2) DEFAULT NULL,
  `credit_amount` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_id` (`entry_id`),
  CONSTRAINT `fk_journal_line_entry` FOREIGN KEY (`entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `journal_lines_ibfk_1` FOREIGN KEY (`entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `journals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_no` varchar(64) NOT NULL,
  `voucher_date` date NOT NULL,
  `narration` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `organization_config`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Organization configuration for GST';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `parties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `party_type` enum('customer','supplier','both') NOT NULL DEFAULT 'customer',
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `state_code` varchar(2) DEFAULT NULL COMMENT 'State code for GST classification',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parties_gstin` (`gstin`),
  KEY `idx_party_gstin` (`gstin`),
  KEY `idx_party_name` (`name`),
  KEY `idx_party_state` (`state`),
  KEY `idx_party_type` (`party_type`),
  CONSTRAINT `chk_gstin_format` CHECK (`gstin` regexp '^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
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
DROP TABLE IF EXISTS `purchase_invoice_staging`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_invoice_staging` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `hindi_name` varchar(255) DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `mfg_date` date DEFAULT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT 1.000,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `invoice_no` varchar(50) NOT NULL,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `batch_no` varchar(50) DEFAULT NULL,
  `taxable_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `supplier_gstin` varchar(15) DEFAULT NULL,
  `total_gst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `hsn_code` varchar(10) DEFAULT NULL,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `mrp` decimal(8,2) DEFAULT NULL,
  `igst_amount` decimal(10,2) DEFAULT 0.00,
  `supplier_name` varchar(255) NOT NULL,
  `data_source` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_staging_invoice_no` (`invoice_no`),
  KEY `idx_staging_invoice_date` (`invoice_date`),
  KEY `idx_staging_item_name` (`item_name`),
  KEY `idx_staging_supplier` (`supplier_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_invoice_staging_reverse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_invoice_staging_reverse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `hindi_name` varchar(255) DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `mfg_date` date DEFAULT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT 1.000,
  `invoice_no` varchar(50) NOT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `supplier_gstin` varchar(15) DEFAULT NULL,
  `hsn_code` varchar(10) DEFAULT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `data_source` varchar(50) DEFAULT NULL,
  `item_net_amount_gross` decimal(12,2) NOT NULL,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `total_gst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `calculated_taxable_amount` decimal(12,2) GENERATED ALWAYS AS (`item_net_amount_gross` / (1 + `total_gst_rate` / 100)) STORED,
  `calculated_rate` decimal(12,2) GENERATED ALWAYS AS (`calculated_taxable_amount` / `quantity`) STORED,
  `calculated_cgst_amount` decimal(12,2) GENERATED ALWAYS AS (`calculated_taxable_amount` * `cgst_rate` / 100) STORED,
  `calculated_sgst_amount` decimal(12,2) GENERATED ALWAYS AS (`calculated_taxable_amount` * `sgst_rate` / 100) STORED,
  `calculated_igst_amount` decimal(12,2) GENERATED ALWAYS AS (`calculated_taxable_amount` * `igst_rate` / 100) STORED,
  `calculated_line_total` decimal(12,2) GENERATED ALWAYS AS (`calculated_taxable_amount` + `calculated_cgst_amount` + `calculated_sgst_amount` + `calculated_igst_amount`) STORED,
  `amount_difference` decimal(12,2) GENERATED ALWAYS AS (abs(`item_net_amount_gross` - `calculated_line_total`)) STORED,
  PRIMARY KEY (`id`),
  KEY `idx_staging_invoice_no` (`invoice_no`),
  KEY `idx_staging_invoice_date` (`invoice_date`),
  KEY `idx_staging_item_name` (`item_name`),
  KEY `idx_staging_supplier` (`supplier_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_staging`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_staging` (
  `invoice_no` varchar(64) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `item_desc` varchar(255) DEFAULT NULL,
  `hsn` varchar(10) DEFAULT NULL,
  `qty_received` decimal(14,3) DEFAULT NULL,
  `net_value` decimal(16,2) DEFAULT NULL,
  `gst_percent` decimal(5,2) DEFAULT NULL,
  `rate_calc` decimal(12,2) DEFAULT NULL,
  `source_file` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staging_purchase_invoice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `staging_purchase_invoice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `HSN_code` varchar(50) DEFAULT NULL,
  `Received_Qty` varchar(50) DEFAULT NULL,
  `mrp` varchar(50) DEFAULT NULL,
  `Batch_No` varchar(100) DEFAULT NULL,
  `Mfg_date` varchar(50) DEFAULT NULL,
  `GST_Perc` varchar(50) DEFAULT NULL,
  `Supplier_Name` varchar(255) DEFAULT NULL,
  `Item_name` varchar(255) DEFAULT NULL,
  `Hindi_Name` varchar(255) DEFAULT NULL,
  `Item_Net_Amount` varchar(50) DEFAULT NULL,
  `cgstrate` varchar(50) DEFAULT NULL,
  `sgstrate` varchar(50) DEFAULT NULL,
  `igstrate` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stg_purchase_invoice_hindi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stg_purchase_invoice_hindi` (
  `HSN code` varchar(20) DEFAULT NULL,
  `Received Qty` decimal(12,3) DEFAULT NULL,
  `mrp` decimal(10,2) DEFAULT NULL,
  `Batch No` varchar(50) DEFAULT NULL,
  `Mfg date` date DEFAULT NULL,
  `GST Perc` decimal(5,2) DEFAULT NULL,
  `Supplier Name` varchar(255) DEFAULT NULL,
  `Item name` varchar(255) DEFAULT NULL,
  `Hindi_Name` varchar(255) DEFAULT NULL,
  `Item Net Amount` decimal(14,2) DEFAULT NULL,
  `cgstrate` decimal(6,2) DEFAULT NULL,
  `sgstrate` decimal(6,2) DEFAULT NULL,
  `igstrate` decimal(6,2) DEFAULT NULL,
  `Inv No` varchar(50) DEFAULT NULL,
  `Supplier GST` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `txn_ref_log`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `uqc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `uqc` (
  `id` tinyint(4) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `v_customer_suggestions`;
/*!50001 DROP VIEW IF EXISTS `v_customer_suggestions`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_customer_suggestions` AS SELECT
 1 AS `id`,
  1 AS `label`,
  1 AS `name`,
  1 AS `gstin`,
  1 AS `city`,
  1 AS `party_type` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_page_table_pairs`;
/*!50001 DROP VIEW IF EXISTS `v_page_table_pairs`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_page_table_pairs` AS SELECT
 1 AS `page_id`,
  1 AS `page_name`,
  1 AS `table_name` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_pages_to_tables`;
/*!50001 DROP VIEW IF EXISTS `v_pages_to_tables`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_pages_to_tables` AS SELECT
 1 AS `page_name`,
  1 AS `dependent_tables`,
  1 AS `tables_count` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_tables_to_pages`;
/*!50001 DROP VIEW IF EXISTS `v_tables_to_pages`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_tables_to_pages` AS SELECT
 1 AS `table_name`,
  1 AS `pages_count`,
  1 AS `pages_list` */;
SET character_set_client = @saved_cs_client;
/*!50001 DROP VIEW IF EXISTS `v_customer_suggestions`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`gstwork`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_customer_suggestions` AS select `parties`.`id` AS `id`,concat(`parties`.`name`,case when `parties`.`gstin` is not null then concat(' [',`parties`.`gstin`,']') else '' end,case when `parties`.`city` is not null then concat(' - ',`parties`.`city`) else '' end) AS `label`,`parties`.`name` AS `name`,`parties`.`gstin` AS `gstin`,`parties`.`city` AS `city`,`parties`.`party_type` AS `party_type` from `parties` where `parties`.`party_type` in ('customer','both') */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_page_table_pairs`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`gstwork`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_page_table_pairs` AS select `p`.`id` AS `page_id`,`p`.`name` AS `page_name`,`t`.`table_name` AS `table_name` from ((select `information_schema`.`tables`.`TABLE_NAME` AS `table_name` from `information_schema`.`tables` where `information_schema`.`tables`.`TABLE_SCHEMA` = 'gst_accounting' and `information_schema`.`tables`.`TABLE_TYPE` = 'BASE TABLE' and `information_schema`.`tables`.`TABLE_NAME` not in ('Pages','CSS_Files','JS_Files','Users')) `t` join `gst_accounting`.`Pages` `p` on(find_in_set(`t`.`table_name`,`p`.`tables_used`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_pages_to_tables`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`gstwork`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_pages_to_tables` AS select `p`.`name` AS `page_name`,`p`.`tables_used` AS `dependent_tables`,case when `p`.`tables_used` is null or `p`.`tables_used` = '' then 0 else 1 + octet_length(`p`.`tables_used`) - octet_length(replace(`p`.`tables_used`,',','')) end AS `tables_count` from `Pages` `p` order by case when `p`.`tables_used` is null or `p`.`tables_used` = '' then 0 else 1 + octet_length(`p`.`tables_used`) - octet_length(replace(`p`.`tables_used`,',','')) end desc,`p`.`name` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_tables_to_pages`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`gstwork`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_tables_to_pages` AS select `t`.`table_name` AS `table_name`,count(`p`.`id`) AS `pages_count`,group_concat(`p`.`name` order by `p`.`name` ASC separator ', ') AS `pages_list` from ((select `information_schema`.`tables`.`TABLE_NAME` AS `table_name` from `information_schema`.`tables` where `information_schema`.`tables`.`TABLE_SCHEMA` = 'gst_accounting' and `information_schema`.`tables`.`TABLE_TYPE` = 'BASE TABLE' and `information_schema`.`tables`.`TABLE_NAME` not in ('Pages','CSS_Files','JS_Files','Users')) `t` left join `gst_accounting`.`Pages` `p` on(find_in_set(`t`.`table_name`,`p`.`tables_used`))) group by `t`.`table_name` order by count(`p`.`id`) desc,`t`.`table_name` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

