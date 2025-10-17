-- gst_compliant_schema.sql
-- Purpose : Build a GST-India compliant dataset with hardened integrity.
-- Layout  : Creates `gst_records` (authoritative data) and `pdo_web_app` (application views).
-- Notes   : Run on MariaDB 10.6+ / MySQL 8.0+ (uses generated columns, check constraints, window funcs).

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------

DROP DATABASE IF EXISTS `transactions`;
DROP DATABASE IF EXISTS `audit`;
DROP DATABASE IF EXISTS `gst_catalog_compliant`;
DROP DATABASE IF EXISTS `reference`;
DROP DATABASE IF EXISTS `core`;
DROP DATABASE IF EXISTS `staging`;
DROP DATABASE IF EXISTS `gst_records`;
DROP DATABASE IF EXISTS `pdo_web_app`;
CREATE DATABASE `gst_records` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE `pdo_web_app` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gst_records`;

-- ---------------------------------------------------------------------------
-- 1. Reference layer (GST master data)                                       
-- ---------------------------------------------------------------------------

CREATE TABLE reference_hsn_codes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    hsn_code        VARCHAR(10) NOT NULL,
    description     VARCHAR(255) NOT NULL,
    gst_slab        DECIMAL(5,2) NOT NULL,
    cess_rate       DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    effective_from  DATE NOT NULL,
    effective_to    DATE DEFAULT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by      VARCHAR(100) NOT NULL DEFAULT 'system',
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by      VARCHAR(100) NOT NULL DEFAULT 'system',
    CONSTRAINT chk_hsn_code_format CHECK (hsn_code REGEXP '^[0-9]{4,10}$'),
    CONSTRAINT chk_hsn_desc_not_numeric CHECK (description NOT REGEXP '^[[:digit:][:space:][:punct:]]+$'),
    CONSTRAINT chk_hsn_desc_not_date CHECK (
        description NOT REGEXP '^[0-9]{2}-[0-9]{2}-[0-9]{4}$'
        AND description NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
    ),
    CONSTRAINT chk_hsn_gst_range CHECK (gst_slab >= 0 AND gst_slab <= 28),
    CONSTRAINT chk_hsn_effective CHECK (effective_to IS NULL OR effective_to > effective_from),
    UNIQUE KEY uq_hsn_effective (hsn_code, effective_from)

) ENGINE=InnoDB;

CREATE TABLE reference_gst_registration_types (
    code        VARCHAR(20) PRIMARY KEY,
    description VARCHAR(120) NOT NULL,
    is_composite TINYINT(1) NOT NULL DEFAULT 0

) ENGINE=InnoDB;

INSERT INTO reference_gst_registration_types(code, description, is_composite) VALUES
    ('REGULAR', 'Regular GST Registrant', 0),
    ('COMPOSITION', 'Composition Dealer', 1),
    ('UNREGISTERED', 'Unregistered Counterparty', 0)
ON DUPLICATE KEY UPDATE description = VALUES(description), is_composite = VALUES(is_composite);

CREATE TABLE core_uoms (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(10) NOT NULL,
    description VARCHAR(100) NOT NULL,
    to_base     DECIMAL(14,6) NOT NULL DEFAULT 1.000000,
    base_code   VARCHAR(10) DEFAULT NULL,
    UNIQUE KEY uq_uom_code (code)

) ENGINE=InnoDB;

INSERT INTO core_uoms(code, description, to_base, base_code) VALUES
    ('PCS','Pieces',1.0,'PCS'),
    ('KG','Kilogram',1.0,'KG'),
    ('G','Gram',0.001,'KG'),
    ('LTR','Litre',1.0,'LTR'),
    ('ML','Millilitre',0.001,'LTR')
ON DUPLICATE KEY UPDATE description = VALUES(description), to_base = VALUES(to_base), base_code = VALUES(base_code);

CREATE TABLE core_products (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    canonical_name_hi   VARCHAR(255) NOT NULL,
    canonical_name_en   VARCHAR(255) DEFAULT NULL,
    default_uom_id      INT NOT NULL,
    default_hsn_id      INT DEFAULT NULL,
    default_tax_rate    DECIMAL(5,2) DEFAULT NULL,
    track_inventory     TINYINT(1) NOT NULL DEFAULT 1,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_uom FOREIGN KEY (default_uom_id) REFERENCES core_uoms(id) ON UPDATE CASCADE,
    CONSTRAINT fk_products_hsn FOREIGN KEY (default_hsn_id) REFERENCES reference_hsn_codes(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE FULLTEXT INDEX ft_products_hi ON core_products (canonical_name_hi);
CREATE FULLTEXT INDEX ft_products_en ON core_products (canonical_name_en);

CREATE TABLE core_product_aliases (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    product_id    INT NOT NULL,
    alias_text    VARCHAR(255) NOT NULL,
    alias_lang    ENUM('hi','en','other') NOT NULL DEFAULT 'hi',
    source_tag    VARCHAR(64) NOT NULL DEFAULT 'manual',
    confidence    DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_alias_product (product_id, alias_text),
    CONSTRAINT fk_alias_product FOREIGN KEY (product_id) REFERENCES core_products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE core_tax_profiles (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    profile_name    VARCHAR(80) NOT NULL,
    cgst_rate       DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    sgst_rate       DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    igst_rate       DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    cess_rate       DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    is_prepackaged  TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_tax_profile (profile_name),
    CONSTRAINT chk_tax_rate_total CHECK (
        (cgst_rate + sgst_rate = igst_rate AND igst_rate > 0)
        OR (igst_rate = 0 AND cgst_rate >= 0 AND sgst_rate >= 0)
    )
) ENGINE=InnoDB;

CREATE TABLE core_parties (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    legal_name         VARCHAR(255) NOT NULL,
    trade_name         VARCHAR(255) DEFAULT NULL,
    gstin              VARCHAR(15) DEFAULT NULL,
    registration_type  VARCHAR(20) NOT NULL DEFAULT 'UNREGISTERED',
    state_code         CHAR(2) DEFAULT NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_gstin_format CHECK (gstin IS NULL OR gstin REGEXP '^[0-9A-Z]{15}$'),
    CONSTRAINT fk_party_reg_type FOREIGN KEY (registration_type) REFERENCES reference_gst_registration_types(code)
) ENGINE=InnoDB;

CREATE UNIQUE INDEX uq_party_gstin ON core_parties (gstin);

CREATE TABLE transactions_invoices (
    id                 BIGINT AUTO_INCREMENT PRIMARY KEY,
    invoice_number     VARCHAR(50) NOT NULL,
    invoice_date       DATE NOT NULL,
    buyer_id           INT NOT NULL,
    supplier_id        INT NOT NULL,
    place_of_supply    CHAR(2) NOT NULL,
    total_taxable      DECIMAL(14,2) NOT NULL,
    total_tax          DECIMAL(14,2) NOT NULL,
    total_invoice      DECIMAL(14,2) NOT NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoice_number_supplier (supplier_id, invoice_number),
    CONSTRAINT fk_invoice_buyer FOREIGN KEY (buyer_id) REFERENCES core_parties(id) ON UPDATE CASCADE,
    CONSTRAINT fk_invoice_supplier FOREIGN KEY (supplier_id) REFERENCES core_parties(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE transactions_invoice_lines (
    id                 BIGINT AUTO_INCREMENT PRIMARY KEY,
    invoice_id         BIGINT NOT NULL,
    line_number        INT NOT NULL,
    product_id         INT NOT NULL,
    description_hi     VARCHAR(255) NOT NULL,
    description_en     VARCHAR(255) DEFAULT NULL,
    hsn_id             INT NOT NULL,
    quantity           DECIMAL(14,3) NOT NULL,
    uom_id             INT NOT NULL,
    unit_price         DECIMAL(14,4) NOT NULL,
    discount_percent   DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    taxable_value      DECIMAL(14,2) NOT NULL,
    cgst_rate          DECIMAL(5,2) NOT NULL,
    sgst_rate          DECIMAL(5,2) NOT NULL,
    igst_rate          DECIMAL(5,2) NOT NULL,
    cess_rate          DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_line_invoice FOREIGN KEY (invoice_id) REFERENCES transactions_invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_line_product FOREIGN KEY (product_id) REFERENCES core_products(id) ON UPDATE CASCADE,
    CONSTRAINT fk_line_hsn FOREIGN KEY (hsn_id) REFERENCES reference_hsn_codes(id) ON UPDATE CASCADE,
    CONSTRAINT fk_line_uom FOREIGN KEY (uom_id) REFERENCES core_uoms(id) ON UPDATE CASCADE,
    CONSTRAINT chk_line_desc_not_numeric CHECK (description_hi NOT REGEXP '^[[:digit:][:space:][:punct:]]+$'),
    CONSTRAINT chk_line_rate_match CHECK (
        (cgst_rate + sgst_rate = igst_rate AND igst_rate > 0)
        OR (igst_rate = 0 AND cgst_rate >= 0 AND sgst_rate >= 0)
    ),
    UNIQUE KEY uq_invoice_line (invoice_id, line_number)
) ENGINE=InnoDB;

-- Auto-derived totals enforcement
CREATE TRIGGER trg_invoice_lines_bi BEFORE INSERT ON transactions_invoice_lines
FOR EACH ROW
BEGIN
    DECLARE total_rate DECIMAL(5,2);
    IF NEW.description_hi REGEXP '^[[:digit:][:space:][:punct:]]+$' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Invoice line description cannot be purely numeric/dated';
    END IF;
    SELECT gst_slab INTO total_rate
    FROM reference_hsn_codes
    WHERE id = NEW.hsn_id
      AND is_active = 1
      AND (effective_to IS NULL OR effective_to >= CURRENT_DATE())
      AND effective_from <= CURRENT_DATE();
    IF total_rate IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'HSN not active for current date';
    END IF;
END;

CREATE TABLE audit_event_log (
    id             BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_type     VARCHAR(40) NOT NULL,
    entity_name    VARCHAR(80) NOT NULL,
    entity_id      BIGINT NOT NULL,
    payload        JSON NOT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by     VARCHAR(100) NOT NULL DEFAULT 'system'
) ENGINE=InnoDB;

DELIMITER $$
CREATE PROCEDURE audit_sp_record_event(
    IN p_event_type VARCHAR(40),
    IN p_entity_name VARCHAR(80),
    IN p_entity_id BIGINT,
    IN p_payload JSON,
    IN p_actor VARCHAR(100)
)
BEGIN
    INSERT INTO audit_event_log(event_type, entity_name, entity_id, payload, created_by)
    VALUES (p_event_type, p_entity_name, p_entity_id, p_payload, COALESCE(p_actor, 'system'));
END$$
DELIMITER ;

CREATE TABLE staging_hsn_candidates (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    source_name     VARCHAR(80) NOT NULL,
    hsn_code        VARCHAR(10) NOT NULL,
    candidate_text  VARCHAR(255) NOT NULL,
    gst_rate        DECIMAL(5,2) DEFAULT NULL,
    evidence_score  DECIMAL(5,2) NOT NULL DEFAULT 0.50,
    observed_on     DATE DEFAULT CURRENT_DATE(),
    raw_payload     JSON DEFAULT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_candidate_text CHECK (
        candidate_text NOT REGEXP '^[[:digit:][:space:][:punct:]]+$'
    )
) ENGINE=InnoDB;

DELIMITER $$
CREATE PROCEDURE staging_sp_promote_hsn_candidates(IN p_actor VARCHAR(100))
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_hsn VARCHAR(10);
    DECLARE v_desc VARCHAR(255);
    DECLARE v_rate DECIMAL(5,2);
    DECLARE v_score DECIMAL(5,2);
    DECLARE cur CURSOR FOR
        SELECT hsn_code,
               candidate_text,
               COALESCE(gst_rate, 0),
               evidence_score
        FROM staging_hsn_candidates
        WHERE candidate_text NOT REGEXP '^[[:digit:][:space:][:punct:]]+$'
        ORDER BY evidence_score DESC, observed_on ASC;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_hsn, v_desc, v_rate, v_score;
        IF done THEN
            LEAVE read_loop;
        END IF;

        INSERT INTO reference_hsn_codes
            (hsn_code, description, gst_slab, cess_rate, effective_from, is_active, created_by, updated_by)
        VALUES
            (v_hsn, v_desc, v_rate, 0.00, CURRENT_DATE(), 1, p_actor, p_actor)
        ON DUPLICATE KEY UPDATE
            description = VALUES(description),
            gst_slab = CASE
                WHEN VALUES(gst_slab) = 0 THEN reference_hsn_codes.gst_slab
                ELSE VALUES(gst_slab)
            END,
            updated_by = p_actor;

        CALL audit_sp_record_event(
            'HSN_UPSERT',
            'reference_hsn_codes',
            LAST_INSERT_ID(),
            JSON_OBJECT('hsn_code', v_hsn, 'description', v_desc, 'gst_slab', v_rate, 'score', v_score),
            p_actor
        );
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

CREATE OR REPLACE VIEW reference_v_active_hsn AS
SELECT
    h.id,
    h.hsn_code,
    h.description,
    h.gst_slab,
    h.cess_rate,
    h.effective_from,
    h.effective_to
FROM reference_hsn_codes h
WHERE h.is_active = 1
  AND h.effective_from <= CURRENT_DATE()
  AND (h.effective_to IS NULL OR h.effective_to >= CURRENT_DATE());

CREATE OR REPLACE VIEW transactions_v_invoice_line_validation AS
SELECT
    l.id AS line_id,
    i.invoice_number,
    i.invoice_date,
    h.hsn_code,
    h.description,
    l.description_hi AS line_description,
    l.gst_slab,
    l.cgst_rate,
    l.sgst_rate,
    l.igst_rate,
    CASE
        WHEN l.cgst_rate + l.sgst_rate <> h.gst_slab AND l.igst_rate = 0 THEN 'RATE_MISMATCH_CGST_SGST'
        WHEN l.igst_rate <> h.gst_slab AND l.igst_rate > 0 THEN 'RATE_MISMATCH_IGST'
        ELSE 'OK'
    END AS compliance_status
FROM transactions_invoice_lines l
JOIN transactions_invoices i ON i.id = l.invoice_id
JOIN reference_hsn_codes h ON h.id = l.hsn_id;

-- ---------------------------------------------------------------------------
-- 9. Application-facing views (pdo_web_app)                                   
-- ---------------------------------------------------------------------------

USE pdo_web_app;

CREATE OR REPLACE VIEW app_v_active_hsn AS
SELECT *
FROM gst_records.reference_v_active_hsn;

CREATE OR REPLACE VIEW app_v_products AS
SELECT
    p.id,
    p.canonical_name_hi,
    p.canonical_name_en,
    u.code        AS default_uom_code,
    u.description AS default_uom_description,
    h.hsn_code,
    h.description AS hsn_description,
    p.default_tax_rate,
    p.track_inventory,
    p.is_active,
    p.created_at,
    p.updated_at
FROM gst_records.core_products p
JOIN gst_records.core_uoms u ON u.id = p.default_uom_id
LEFT JOIN gst_records.reference_hsn_codes h ON h.id = p.default_hsn_id;

CREATE OR REPLACE VIEW app_v_parties AS
SELECT
    party.id,
    party.legal_name,
    party.trade_name,
    party.gstin,
    party.registration_type,
    reg.description AS registration_description,
    party.state_code,
    party.created_at,
    party.updated_at
FROM gst_records.core_parties party
JOIN gst_records.reference_gst_registration_types reg ON reg.code = party.registration_type;

CREATE OR REPLACE VIEW app_v_invoice_summary AS
SELECT
    i.id,
    i.invoice_number,
    i.invoice_date,
    i.place_of_supply,
    i.total_taxable,
    i.total_tax,
    i.total_invoice,
    buyer.legal_name    AS buyer_legal_name,
    buyer.gstin         AS buyer_gstin,
    supplier.legal_name AS supplier_legal_name,
    supplier.gstin      AS supplier_gstin,
    i.created_at,
    i.updated_at
FROM gst_records.transactions_invoices i
JOIN gst_records.core_parties buyer ON buyer.id = i.buyer_id
JOIN gst_records.core_parties supplier ON supplier.id = i.supplier_id;

CREATE OR REPLACE VIEW app_v_invoice_lines AS
SELECT
    l.id,
    l.invoice_id,
    i.invoice_number,
    l.line_number,
    l.product_id,
    p.canonical_name_hi AS product_name_hi,
    p.canonical_name_en AS product_name_en,
    l.description_hi,
    l.description_en,
    h.hsn_code,
    h.description       AS hsn_description,
    l.quantity,
    u.code              AS uom_code,
    l.unit_price,
    l.discount_percent,
    l.taxable_value,
    l.cgst_rate,
    l.sgst_rate,
    l.igst_rate,
    l.cess_rate,
    l.created_at
FROM gst_records.transactions_invoice_lines l
JOIN gst_records.transactions_invoices i ON i.id = l.invoice_id
JOIN gst_records.core_products p ON p.id = l.product_id
JOIN gst_records.core_uoms u ON u.id = l.uom_id
JOIN gst_records.reference_hsn_codes h ON h.id = l.hsn_id;

CREATE OR REPLACE VIEW app_v_invoice_compliance AS
SELECT *
FROM gst_records.transactions_v_invoice_line_validation;

CREATE OR REPLACE VIEW app_v_staging_hsn_candidates AS
SELECT *
FROM gst_records.staging_hsn_candidates;

GRANT SELECT, INSERT, UPDATE, DELETE ON `gst_records`.* TO 'gst_app'@'%' IDENTIFIED BY 'StrongP@ssw0rd!';
GRANT SELECT, INSERT, UPDATE, DELETE ON `pdo_web_app`.* TO 'gst_app'@'%';
GRANT EXECUTE ON PROCEDURE `gst_records`.staging_sp_promote_hsn_candidates TO 'gst_app'@'%';
GRANT EXECUTE ON PROCEDURE `gst_records`.audit_sp_record_event TO 'gst_app'@'%';

-- DONE ----------------------------------------------------------------------
