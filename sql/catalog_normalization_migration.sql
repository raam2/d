-- catalog_normalization_migration.sql
-- Author: Copilot (GitHub Copilot)
-- Purpose: Introduce normalized product catalog tables, migrate legacy data,
--          and prepare the database for clean item references.
-- Safety: Designed to be idempotent and non-destructive. Each INSERT uses
--         safeguards to avoid duplicates. Review carefully before production.

START TRANSACTION;

/* -------------------------------------------------------------------------- */
/* 0. Ensure required utility schema pieces                                   */
/* -------------------------------------------------------------------------- */

-- optional: switch to target database (uncomment and adapt)
-- USE `u184420243_jayanti_enter4`;

/* -------------------------------------------------------------------------- */
/* 1. Reference tables                                                         */
/* -------------------------------------------------------------------------- */

CREATE TABLE IF NOT EXISTS product_uoms (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(10) NOT NULL,
    description     VARCHAR(120) NOT NULL,
    conversion_to_base DECIMAL(14,6) NOT NULL DEFAULT 1.000000,
    base_code       VARCHAR(10) DEFAULT NULL,
    UNIQUE KEY uq_product_uoms_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hsn_codes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    hsn_code        VARCHAR(10) NOT NULL,
    description     VARCHAR(255) DEFAULT NULL,
    gst_slab        DECIMAL(5,2) DEFAULT NULL,
    cess_rate       DECIMAL(5,2) DEFAULT 0.00,
    valid_from      DATE NOT NULL DEFAULT '1970-01-01',
    valid_to        DATE DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hsn_code_validfrom (hsn_code, valid_from),
    KEY idx_hsn_codes_code (hsn_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tax_profiles (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    profile_name    VARCHAR(120) NOT NULL,
    cgst_rate       DECIMAL(5,2) DEFAULT 0.00,
    sgst_rate       DECIMAL(5,2) DEFAULT 0.00,
    igst_rate       DECIMAL(5,2) DEFAULT 0.00,
    ugst_rate       DECIMAL(5,2) DEFAULT 0.00,
    cess_rate       DECIMAL(5,2) DEFAULT 0.00,
    is_prepackaged_labelled TINYINT(1) DEFAULT 0,
    is_itc_blocked  TINYINT(1) DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tax_profiles_name (profile_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -------------------------------------------------------------------------- */
/* 2. Product catalog + aliases                                                */
/* -------------------------------------------------------------------------- */

CREATE TABLE IF NOT EXISTS product_catalog (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    canonical_name_en   VARCHAR(255) DEFAULT NULL,
    canonical_name_hi   VARCHAR(255) DEFAULT NULL,
    default_uom_id      INT DEFAULT NULL,
    default_hsn_id      INT DEFAULT NULL,
    default_tax_profile_id INT DEFAULT NULL,
    track_inventory     TINYINT(1) DEFAULT 1,
    is_active           TINYINT(1) DEFAULT 1,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_catalog_name_hi (canonical_name_hi),
    CONSTRAINT fk_product_catalog_uom FOREIGN KEY (default_uom_id)
        REFERENCES product_uoms (id) ON UPDATE CASCADE,
    CONSTRAINT fk_product_catalog_hsn FOREIGN KEY (default_hsn_id)
        REFERENCES hsn_codes (id) ON UPDATE CASCADE,
    CONSTRAINT fk_product_catalog_tax FOREIGN KEY (default_tax_profile_id)
        REFERENCES tax_profiles (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_aliases (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT NOT NULL,
    alias_text      VARCHAR(255) NOT NULL,
    alias_language  ENUM('en','hi','other') DEFAULT 'hi',
    source_tag      VARCHAR(64) DEFAULT 'legacy',
    confidence      DECIMAL(5,2) DEFAULT 1.00,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_alias (product_id, alias_text),
    FULLTEXT KEY ft_product_alias (alias_text),
    CONSTRAINT fk_product_aliases_catalog FOREIGN KEY (product_id)
        REFERENCES product_catalog (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_variants (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT NOT NULL,
    sku_code        VARCHAR(64) DEFAULT NULL,
    pack_size       DECIMAL(12,3) DEFAULT 1.000,
    uom_id          INT DEFAULT NULL,
    mrp             DECIMAL(12,2) DEFAULT NULL,
    UNIQUE KEY uq_product_variant (product_id, pack_size, uom_id),
    KEY idx_product_variants_sku (sku_code),
    CONSTRAINT fk_product_variants_product FOREIGN KEY (product_id)
        REFERENCES product_catalog (id) ON DELETE CASCADE,
    CONSTRAINT fk_product_variants_uom FOREIGN KEY (uom_id)
        REFERENCES product_uoms (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -------------------------------------------------------------------------- */
/* 3. Extend existing tables with catalog references                           */
/* -------------------------------------------------------------------------- */

ALTER TABLE items
    ADD COLUMN IF NOT EXISTS product_id INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS default_variant_id INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS default_uom_id INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS default_hsn_id INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS default_tax_profile_id INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS canonical_name_en VARCHAR(255) DEFAULT NULL;

SET @add_idx_items_product := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.statistics
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'items'
              AND INDEX_NAME = 'idx_items_product_id'
        ),
        'SELECT 1',
        'ALTER TABLE items ADD INDEX idx_items_product_id (product_id)'
    )
);
PREPARE stmt FROM @add_idx_items_product;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_idx_items_variant := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.statistics
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'items'
              AND INDEX_NAME = 'idx_items_default_variant'
        ),
        'SELECT 1',
        'ALTER TABLE items ADD INDEX idx_items_default_variant (default_variant_id)'
    )
);
PREPARE stmt FROM @add_idx_items_variant;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_idx_items_uom := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.statistics
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'items'
              AND INDEX_NAME = 'idx_items_default_uom'
        ),
        'SELECT 1',
        'ALTER TABLE items ADD INDEX idx_items_default_uom (default_uom_id)'
    )
);
PREPARE stmt FROM @add_idx_items_uom;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_idx_items_hsn := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.statistics
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'items'
              AND INDEX_NAME = 'idx_items_default_hsn'
        ),
        'SELECT 1',
        'ALTER TABLE items ADD INDEX idx_items_default_hsn (default_hsn_id)'
    )
);
PREPARE stmt FROM @add_idx_items_hsn;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_idx_items_tax := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.statistics
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'items'
              AND INDEX_NAME = 'idx_items_default_tax'
        ),
        'SELECT 1',
        'ALTER TABLE items ADD INDEX idx_items_default_tax (default_tax_profile_id)'
    )
);
PREPARE stmt FROM @add_idx_items_tax;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_fk_items_product := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = 'items'
              AND CONSTRAINT_NAME = 'fk_items_catalog_product'
        ),
        'SELECT 1',
        'ALTER TABLE items ADD CONSTRAINT fk_items_catalog_product FOREIGN KEY (product_id) REFERENCES product_catalog (id) ON UPDATE CASCADE'
    )
);
PREPARE stmt FROM @add_fk_items_product;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_fk_items_variant := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = 'items'
              AND CONSTRAINT_NAME = 'fk_items_catalog_variant'
        ),
        'SELECT 1',
        'ALTER TABLE items ADD CONSTRAINT fk_items_catalog_variant FOREIGN KEY (default_variant_id) REFERENCES product_variants (id) ON UPDATE CASCADE'
    )
);
PREPARE stmt FROM @add_fk_items_variant;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_fk_items_uom := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = 'items'
              AND CONSTRAINT_NAME = 'fk_items_catalog_uom'
        ),
        'SELECT 1',
        'ALTER TABLE items ADD CONSTRAINT fk_items_catalog_uom FOREIGN KEY (default_uom_id) REFERENCES product_uoms (id) ON UPDATE CASCADE'
    )
);
PREPARE stmt FROM @add_fk_items_uom;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_fk_items_hsn := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = 'items'
              AND CONSTRAINT_NAME = 'fk_items_catalog_hsn'
        ),
        'SELECT 1',
        'ALTER TABLE items ADD CONSTRAINT fk_items_catalog_hsn FOREIGN KEY (default_hsn_id) REFERENCES hsn_codes (id) ON UPDATE CASCADE'
    )
);
PREPARE stmt FROM @add_fk_items_hsn;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_fk_items_tax := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = 'items'
              AND CONSTRAINT_NAME = 'fk_items_catalog_tax'
        ),
        'SELECT 1',
        'ALTER TABLE items ADD CONSTRAINT fk_items_catalog_tax FOREIGN KEY (default_tax_profile_id) REFERENCES tax_profiles (id) ON UPDATE CASCADE'
    )
);
PREPARE stmt FROM @add_fk_items_tax;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

/* -------------------------------------------------------------------------- */
/* 4. Seed reference data from existing tables                                 */
/* -------------------------------------------------------------------------- */

-- 4.a Seed product_uoms with basic units (idempotent)
INSERT IGNORE INTO product_uoms (code, description, conversion_to_base, base_code)
VALUES
-- Remove duplicate HSN rows while keeping the earliest valid_from per code
DELETE hc
FROM hsn_codes hc
JOIN (
    SELECT id
    FROM (
        SELECT id,
               ROW_NUMBER() OVER (PARTITION BY hsn_code ORDER BY valid_from) AS seq
        FROM hsn_codes
    ) ranked
    WHERE seq > 1
) dup ON dup.id = hc.id;
    ('PCS', 'Pieces', 1.0, 'PCS'),
    ('KG',  'Kilogram', 1.0, 'KG'),
    ('G',   'Grams', 0.001, 'KG'),
    ('LTR', 'Litre', 1.0, 'LTR'),
    ('ML',  'Millilitre', 0.001, 'LTR'),
    ('PKT', 'Packet', 1.0, 'PCS'),
    ('BOX', 'Box / Carton', 1.0, 'PCS');

-- 4.b Insert tax profiles derived from current GST slabs
INSERT IGNORE INTO tax_profiles (profile_name, cgst_rate, sgst_rate, igst_rate, cess_rate, is_prepackaged_labelled)
SELECT CONCAT('GST_', LPAD(COALESCE(iv.cgst_rate, 0), 2, '0'), '_', LPAD(COALESCE(iv.sgst_rate, 0), 2, '0')) AS profile_name,
       COALESCE(iv.cgst_rate, 0),
       COALESCE(iv.sgst_rate, 0),
       COALESCE(iv.igst_rate, 0),
       0.00,
       MAX(iv.is_prepackaged_labelled)
FROM invoice_items iv
GROUP BY COALESCE(iv.cgst_rate, 0), COALESCE(iv.sgst_rate, 0), COALESCE(iv.igst_rate, 0);

-- 4.c Seed hsn_codes from existing invoices and staging tables

DELETE hc
FROM hsn_codes hc
JOIN (
    SELECT id
    FROM (
        SELECT id,
               ROW_NUMBER() OVER (PARTITION BY hsn_code ORDER BY valid_from) AS seq
        FROM hsn_codes
    ) ranked
    WHERE seq > 1
) dup ON dup.id = hc.id;
INSERT INTO hsn_codes (hsn_code, description, gst_slab, valid_from)
SELECT
    src.hsn_code,
    COALESCE(
        MAX(CASE
                WHEN src.description_en IS NULL THEN NULL
                WHEN src.description_en REGEXP '^[[:digit:][:space:].,/%-]+$' THEN NULL
                WHEN src.description_en REGEXP '^[[:digit:]]{2}-[[:digit:]]{2}-[[:digit:]]{4}$' THEN NULL
                WHEN src.description_en REGEXP '^[[:digit:]]{4}-[[:digit:]]{2}-[[:digit:]]{2}$' THEN NULL
                ELSE src.description_en
            END),
        MAX(CASE
                WHEN src.description_local IS NULL THEN NULL
                WHEN src.description_local REGEXP '^[[:digit:][:space:].,/%-]+$' THEN NULL
                WHEN src.description_local REGEXP '^[[:digit:]]{2}-[[:digit:]]{2}-[[:digit:]]{4}$' THEN NULL
                WHEN src.description_local REGEXP '^[[:digit:]]{4}-[[:digit:]]{2}-[[:digit:]]{2}$' THEN NULL
                ELSE src.description_local
            END),
        MAX(CASE
                WHEN src.description_fallback IS NULL THEN NULL
                WHEN src.description_fallback REGEXP '^[[:digit:][:space:].,/%-]+$' THEN NULL
                WHEN src.description_fallback REGEXP '^[[:digit:]]{2}-[[:digit:]]{2}-[[:digit:]]{4}$' THEN NULL
                WHEN src.description_fallback REGEXP '^[[:digit:]]{4}-[[:digit:]]{2}-[[:digit:]]{2}$' THEN NULL
                ELSE src.description_fallback
            END),
        CONCAT('HSN ', src.hsn_code) COLLATE utf8mb4_unicode_ci
    ) AS description,
    COALESCE(MAX(NULLIF(src.gst_rate, 0)), 0) AS gst_slab,
    '1970-01-01' AS valid_from
FROM (
    SELECT
        TRIM(iv.hsn) COLLATE utf8mb4_unicode_ci AS hsn_code,
        NULLIF(NULLIF(TRIM(iv.description_en) COLLATE utf8mb4_unicode_ci, '0'), '') AS description_en,
        NULLIF(NULLIF(TRIM(iv.description) COLLATE utf8mb4_unicode_ci, '0'), '') AS description_local,
        NULLIF(NULLIF(TRIM(i.canonical_name_en) COLLATE utf8mb4_unicode_ci, '0'), '') AS description_fallback,
        NULLIF(IFNULL(iv.cgst_rate + iv.sgst_rate, iv.igst_rate), 0) AS gst_rate,
        inv.invoice_date AS first_seen
    FROM invoice_items iv
    JOIN invoices inv ON inv.id = iv.invoice_id
    LEFT JOIN items i ON i.id = iv.item_id
    WHERE iv.hsn IS NOT NULL AND TRIM(iv.hsn) <> ''

    UNION ALL

    SELECT
        TRIM(sp.hsn_code) COLLATE utf8mb4_unicode_ci,
        NULLIF(NULLIF(TRIM(sp.item_name) COLLATE utf8mb4_unicode_ci, '0'), '') AS description_en,
        NULLIF(NULLIF(TRIM(COALESCE(sp.hindi_name, sp.item_name)) COLLATE utf8mb4_unicode_ci, '0'), '') AS description_local,
        NULLIF(NULLIF(TRIM(sp.item_name) COLLATE utf8mb4_unicode_ci, '0'), '') AS description_fallback,
        NULLIF(sp.total_gst_rate, 0) AS gst_rate,
        sp.invoice_date AS first_seen
    FROM purchase_invoice_staging sp
    WHERE sp.hsn_code IS NOT NULL AND TRIM(sp.hsn_code) <> ''

    UNION ALL

    SELECT
        TRIM(h.`HSN code`) COLLATE utf8mb4_unicode_ci,
        NULLIF(NULLIF(TRIM(COALESCE(h.`Item name`, h.`Batch No`, h.`Hindi_Name`)) COLLATE utf8mb4_unicode_ci, '0'), '') AS description_en,
        NULLIF(NULLIF(TRIM(COALESCE(h.`Hindi_Name`, h.`Batch No`, h.`Item name`)) COLLATE utf8mb4_unicode_ci, '0'), '') AS description_local,
        NULLIF(NULLIF(TRIM(COALESCE(h.`Batch No`, h.`Hindi_Name`, h.`Item name`)) COLLATE utf8mb4_unicode_ci, '0'), '') AS description_fallback,
        NULLIF(h.`GST Perc`, 0) AS gst_rate,
        STR_TO_DATE(NULLIF(h.`Inv No`, ''), '%d-%m-%Y') AS first_seen
    FROM stg_purchase_invoice_hindi h
    WHERE h.`HSN code` IS NOT NULL AND TRIM(h.`HSN code`) <> ''

    UNION ALL

    SELECT
        TRIM(spr.hsn_code) COLLATE utf8mb4_unicode_ci,
        NULLIF(NULLIF(TRIM(spr.item_name) COLLATE utf8mb4_unicode_ci, '0'), '') AS description_en,
        NULLIF(NULLIF(TRIM(COALESCE(spr.hindi_name, spr.item_name)) COLLATE utf8mb4_unicode_ci, '0'), '') AS description_local,
        NULLIF(NULLIF(TRIM(spr.item_name) COLLATE utf8mb4_unicode_ci, '0'), '') AS description_fallback,
        NULLIF(spr.total_gst_rate, 0) AS gst_rate,
        spr.invoice_date AS first_seen
    FROM purchase_invoice_staging_reverse spr
    WHERE spr.hsn_code IS NOT NULL AND TRIM(spr.hsn_code) <> ''

    UNION ALL

    SELECT
        TRIM(it.hsn) COLLATE utf8mb4_unicode_ci,
        NULLIF(NULLIF(TRIM(it.canonical_name_en) COLLATE utf8mb4_unicode_ci, '0'), '') AS description_en,
        NULLIF(NULLIF(TRIM(it.canonical_name) COLLATE utf8mb4_unicode_ci, '0'), '') AS description_local,
        NULLIF(NULLIF(TRIM(it.canonical_name) COLLATE utf8mb4_unicode_ci, '0'), '') AS description_fallback,
        NULL AS gst_rate,
        NULL AS first_seen
    FROM items it
    WHERE it.hsn IS NOT NULL AND TRIM(it.hsn) <> ''
) AS src
WHERE src.hsn_code IS NOT NULL AND src.hsn_code <> ''
GROUP BY src.hsn_code
ON DUPLICATE KEY UPDATE
    description = CASE
        WHEN VALUES(description) IS NULL OR VALUES(description) = '' THEN
            CASE
                WHEN hsn_codes.description IS NULL
                     OR hsn_codes.description = ''
                     OR hsn_codes.description REGEXP '^[[:digit:][:space:].,/%-]+$'
                     OR hsn_codes.description REGEXP '^[[:digit:]]{2}-[[:digit:]]{2}-[[:digit:]]{4}$'
                     OR hsn_codes.description REGEXP '^[[:digit:]]{4}-[[:digit:]]{2}-[[:digit:]]{2}$'
                    THEN CONCAT('HSN ', hsn_codes.hsn_code)
                ELSE hsn_codes.description
            END
        WHEN VALUES(description) = CONCAT('HSN ', hsn_codes.hsn_code) THEN
            CASE
                WHEN hsn_codes.description IS NULL
                     OR hsn_codes.description = ''
                     OR hsn_codes.description REGEXP '^[[:digit:][:space:].,/%-]+$'
                     OR hsn_codes.description REGEXP '^[[:digit:]]{2}-[[:digit:]]{2}-[[:digit:]]{4}$'
                     OR hsn_codes.description REGEXP '^[[:digit:]]{4}-[[:digit:]]{2}-[[:digit:]]{2}$'
                    THEN VALUES(description)
                ELSE hsn_codes.description
            END
        ELSE VALUES(description)
    END,
    gst_slab = COALESCE(NULLIF(VALUES(gst_slab), 0), hsn_codes.gst_slab),
    updated_at = NOW();

/* -------------------------------------------------------------------------- */
/* 5. Populate product_catalog and aliases                                     */
/* -------------------------------------------------------------------------- */

-- 5.a Create catalog entries for each legacy item row
INSERT INTO product_catalog (canonical_name_en, canonical_name_hi, track_inventory, is_active)
SELECT
    base.canonical_name_en,
    base.canonical_name_hi,
    base.track_inventory,
    base.is_active
FROM (
    SELECT
        i.id,
        NULLIF(MAX(NULLIF(ii.description_en, '')), '') AS canonical_name_en,
        TRIM(i.canonical_name) AS canonical_name_hi,
        COALESCE(i.track_cogs, 1) AS track_inventory,
        COALESCE(i.is_active, 1) AS is_active
    FROM items i
    LEFT JOIN invoice_items ii ON ii.item_id = i.id
    GROUP BY i.id, TRIM(i.canonical_name), i.track_cogs, i.is_active
) AS base
WHERE base.canonical_name_hi <> ''
  AND NOT EXISTS (
        SELECT 1 FROM product_catalog pc
        WHERE pc.canonical_name_hi = base.canonical_name_hi
    );

-- 5.b Backfill product_id on items
UPDATE items i
JOIN product_catalog pc ON pc.canonical_name_hi = TRIM(i.canonical_name)
SET i.product_id = pc.id
WHERE i.product_id IS NULL;

-- 5.c Preferred HSN mapping per product (based on invoice usage)
UPDATE product_catalog pc
LEFT JOIN (
    SELECT
        TRIM(i.canonical_name) AS canonical_name_hi,
        (
            SELECT TRIM(iv2.hsn)
            FROM invoice_items iv2
            WHERE iv2.item_id = i.id AND TRIM(iv2.hsn) <> ''
            GROUP BY TRIM(iv2.hsn)
            ORDER BY COUNT(*) DESC
            LIMIT 1
        ) AS top_hsn
    FROM items i
) AS agg ON agg.canonical_name_hi = pc.canonical_name_hi
LEFT JOIN hsn_codes hc ON hc.hsn_code = agg.top_hsn
SET pc.default_hsn_id = hc.id
WHERE pc.default_hsn_id IS NULL AND agg.top_hsn IS NOT NULL;

-- 5.d Alias ingestion from staging tables
INSERT INTO product_aliases (product_id, alias_text, alias_language, source_tag, confidence)
SELECT DISTINCT
    pc.id,
    src.alias_text,
    src.alias_language,
    src.source_tag,
    src.confidence
FROM (
    SELECT DISTINCT TRIM(s.item_name) COLLATE utf8mb4_unicode_ci AS alias_text,
        'hi' COLLATE utf8mb4_unicode_ci AS alias_language,
        'purchase_invoice_staging' COLLATE utf8mb4_unicode_ci AS source_tag,
        0.70 AS confidence
    FROM purchase_invoice_staging s
    WHERE TRIM(s.item_name) <> ''

    UNION

    SELECT DISTINCT TRIM(s.hindi_name) COLLATE utf8mb4_unicode_ci,
        'hi' COLLATE utf8mb4_unicode_ci,
        'purchase_invoice_staging' COLLATE utf8mb4_unicode_ci,
        0.80
    FROM purchase_invoice_staging s
    WHERE TRIM(s.hindi_name) <> ''

    UNION

    SELECT DISTINCT TRIM(sr.item_name) COLLATE utf8mb4_unicode_ci,
        'hi' COLLATE utf8mb4_unicode_ci,
        'purchase_invoice_staging_reverse' COLLATE utf8mb4_unicode_ci,
        0.75
    FROM purchase_invoice_staging_reverse sr
    WHERE TRIM(sr.item_name) <> ''

    UNION

    SELECT DISTINCT TRIM(`Item name`) COLLATE utf8mb4_unicode_ci,
        'hi' COLLATE utf8mb4_unicode_ci,
        'stg_purchase_invoice_hindi' COLLATE utf8mb4_unicode_ci,
        0.80
    FROM stg_purchase_invoice_hindi
    WHERE TRIM(`Item name`) <> ''
) AS src
JOIN items i ON TRIM(i.canonical_name) COLLATE utf8mb4_unicode_ci = src.alias_text
JOIN product_catalog pc ON pc.canonical_name_hi = TRIM(i.canonical_name)
WHERE NOT EXISTS (
    SELECT 1 FROM product_aliases pa
    WHERE pa.product_id = pc.id AND pa.alias_text = src.alias_text
);

-- 5.e Capture additional aliases for products with similar names (fallback)
INSERT INTO product_aliases (product_id, alias_text, alias_language, source_tag, confidence)
SELECT DISTINCT
    pc.id,
    src.alias_text,
    src.alias_language,
    src.source_tag,
    src.confidence
FROM (
    SELECT DISTINCT TRIM(s.item_name) COLLATE utf8mb4_unicode_ci AS alias_text,
        'hi' COLLATE utf8mb4_unicode_ci AS alias_language,
        'purchase_invoice_staging' COLLATE utf8mb4_unicode_ci AS source_tag,
        0.50 AS confidence
    FROM purchase_invoice_staging s
    WHERE TRIM(s.item_name) <> ''

    UNION

    SELECT DISTINCT TRIM(`Hindi_Name`) COLLATE utf8mb4_unicode_ci,
        'hi' COLLATE utf8mb4_unicode_ci,
        'stg_purchase_invoice_hindi' COLLATE utf8mb4_unicode_ci,
        0.60
    FROM stg_purchase_invoice_hindi
    WHERE TRIM(`Hindi_Name`) <> ''
) AS src
JOIN product_catalog pc ON SOUNDEX(pc.canonical_name_hi) = SOUNDEX(src.alias_text)
WHERE NOT EXISTS (
    SELECT 1 FROM product_aliases pa
    WHERE pa.product_id = pc.id AND pa.alias_text = src.alias_text
);

/* -------------------------------------------------------------------------- */
/* 6. Link items to reference tables                                           */
/* -------------------------------------------------------------------------- */

-- Determine default UOM
UPDATE items i
LEFT JOIN product_uoms u ON u.code = 'PCS'
SET i.default_uom_id = u.id
WHERE i.default_uom_id IS NULL;

-- Map default tax profile by rate combination
UPDATE items i
LEFT JOIN (
    SELECT
        iv.item_id,
        iv.cgst_rate,
        iv.sgst_rate,
        iv.igst_rate,
        COUNT(*) AS usage_count
    FROM invoice_items iv
    GROUP BY iv.item_id, iv.cgst_rate, iv.sgst_rate, iv.igst_rate
) rate_sel ON rate_sel.item_id = i.id
JOIN tax_profiles tp ON tp.cgst_rate = rate_sel.cgst_rate
    AND tp.sgst_rate = rate_sel.sgst_rate
    AND tp.igst_rate = rate_sel.igst_rate
SET i.default_tax_profile_id = tp.id
WHERE i.default_tax_profile_id IS NULL
  AND rate_sel.usage_count IS NOT NULL;

-- Set default HSN from catalog mapping
UPDATE items i
JOIN product_catalog pc ON pc.id = i.product_id
SET i.default_hsn_id = pc.default_hsn_id
WHERE i.default_hsn_id IS NULL AND pc.default_hsn_id IS NOT NULL;

/* -------------------------------------------------------------------------- */
/* 7. Prepare views for reporting                                              */
/* -------------------------------------------------------------------------- */

CREATE OR REPLACE VIEW v_product_catalog_enriched AS
SELECT
    pc.id AS product_id,
    COALESCE(pc.canonical_name_en, pc.canonical_name_hi) AS product_name_en,
    pc.canonical_name_hi,
    u.code AS default_uom,
    hc.hsn_code,
    hc.gst_slab,
    tp.profile_name,
    tp.cgst_rate,
    tp.sgst_rate,
    tp.igst_rate,
    pc.track_inventory,
    pc.is_active,
    pc.created_at,
    pc.updated_at
FROM product_catalog pc
LEFT JOIN product_uoms u ON u.id = pc.default_uom_id
LEFT JOIN hsn_codes hc ON hc.id = pc.default_hsn_id
LEFT JOIN tax_profiles tp ON tp.id = pc.default_tax_profile_id;

COMMIT;
