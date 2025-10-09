-- Metadata Update Script for Normalized Database
-- This script adds UI components for the new normalized tables
-- Run this AFTER running database_normalization.sql

-- ============================================================================
-- Add Item Variants Management Page
-- ============================================================================

INSERT INTO `app_pages` (`slug`, `title`, `page_type`, `template`)
VALUES (
    'item_variants',
    'Item Name Variants',
    'list',
    '<div class="card">
        <h3>Item Name Variants</h3>
        <p class="muted">Manage multiple names for products (Hindi, English, aliases).</p>
        {{component:variant_search}}
        {{component:variant_list}}
        {{component:variant_add_form}}
    </div>'
)
ON DUPLICATE KEY UPDATE 
    title = VALUES(title),
    template = VALUES(template);

-- ============================================================================
-- Add Purchase Invoice Management Page
-- ============================================================================

INSERT INTO `app_pages` (`slug`, `title`, `page_type`, `template`)
VALUES (
    'purchase_invoices',
    'Purchase Invoices (Normalized)',
    'list',
    '<div class="card">
        <h3>Purchase Invoices</h3>
        <p class="muted">Normalized purchase invoice management.</p>
        {{component:invoice_summary}}
        {{component:invoice_details}}
    </div>'
)
ON DUPLICATE KEY UPDATE 
    title = VALUES(title),
    template = VALUES(template);

-- ============================================================================
-- Item Variants Components
-- ============================================================================

-- Component: Search items by variant name
INSERT INTO `app_components` (`page_slug`, `comp_type`, `name`, `sql_text`, `meta_json`, `ord`)
VALUES (
    'item_variants',
    'form',
    'variant_search',
    'CALL sp_find_item_by_name(?)',
    '{
        "inputs": [
            {"name": "search_name", "label": "Search by any name", "placeholder": "Enter Hindi or English name"}
        ],
        "submit": "Search Items",
        "params": ["search_name"],
        "success": "Search completed."
    }',
    10
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json),
    ord = VALUES(ord);

-- Component: List all item variants
INSERT INTO `app_components` (`page_slug`, `comp_type`, `name`, `sql_text`, `meta_json`, `ord`)
VALUES (
    'item_variants',
    'list',
    'variant_list',
    'SELECT 
        inv.id,
        i.canonical_name as item_name,
        inv.variant_name,
        inv.variant_type,
        inv.source_table,
        CASE WHEN inv.is_primary = 1 THEN "Yes" ELSE "No" END as is_primary
    FROM item_name_variants inv
    INNER JOIN items i ON inv.item_id = i.id
    ORDER BY i.canonical_name, inv.variant_type
    LIMIT 200',
    '{
        "layout": "table",
        "columns": [
            {"field": "id", "label": "ID"},
            {"field": "item_name", "label": "Canonical Name"},
            {"field": "variant_name", "label": "Variant Name"},
            {"field": "variant_type", "label": "Type"},
            {"field": "source_table", "label": "Source"},
            {"field": "is_primary", "label": "Primary"}
        ],
        "emptyText": "No variants found."
    }',
    20
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json),
    ord = VALUES(ord);

-- Component: Add new item variant
INSERT INTO `app_components` (`page_slug`, `comp_type`, `name`, `sql_text`, `meta_json`, `ord`)
VALUES (
    'item_variants',
    'form',
    'variant_add_form',
    'INSERT INTO item_name_variants (item_id, variant_name, variant_type, source_table)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE variant_type = VALUES(variant_type)',
    '{
        "inputs": [
            {"name": "item_id", "label": "Item ID", "type": "number"},
            {"name": "variant_name", "label": "Variant Name"},
            {"name": "variant_type", "label": "Type", "type": "select", "options": ["hindi", "english", "alias", "brand"]},
            {"name": "source_table", "label": "Source", "placeholder": "e.g., manual_entry"}
        ],
        "submit": "Add Variant",
        "params": ["item_id", "variant_name", "variant_type", "source_table"],
        "success": "Variant added successfully."
    }',
    30
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json),
    ord = VALUES(ord);

-- ============================================================================
-- Purchase Invoice Components
-- ============================================================================

-- Component: Invoice summary list
INSERT INTO `app_components` (`page_slug`, `comp_type`, `name`, `sql_text`, `meta_json`, `ord`)
VALUES (
    'purchase_invoices',
    'list',
    'invoice_summary',
    'SELECT 
        id,
        invoice_no,
        invoice_date,
        supplier_name,
        supplier_gstin,
        line_count,
        total_amount,
        status
    FROM v_purchase_invoice_summary
    ORDER BY invoice_date DESC
    LIMIT 100',
    '{
        "layout": "table",
        "columns": [
            {"field": "id", "label": "ID"},
            {"field": "invoice_no", "label": "Invoice No"},
            {"field": "invoice_date", "label": "Date"},
            {"field": "supplier_name", "label": "Supplier"},
            {"field": "supplier_gstin", "label": "GSTIN"},
            {"field": "line_count", "label": "Lines"},
            {"field": "total_amount", "label": "Total"},
            {"field": "status", "label": "Status"}
        ],
        "emptyText": "No invoices found."
    }',
    10
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json),
    ord = VALUES(ord);

-- Component: Invoice line items
INSERT INTO `app_components` (`page_slug`, `comp_type`, `name`, `sql_text`, `meta_json`, `ord`)
VALUES (
    'purchase_invoices',
    'list',
    'invoice_details',
    'SELECT 
        pil.id,
        pih.invoice_no,
        pil.item_name_variant,
        i.canonical_name,
        pil.hsn_code,
        pil.quantity,
        pil.rate,
        pil.taxable_amount,
        pil.cgst_amount,
        pil.sgst_amount,
        pil.igst_amount,
        pil.line_total
    FROM purchase_invoice_line_items pil
    INNER JOIN purchase_invoice_header pih ON pil.invoice_header_id = pih.id
    LEFT JOIN items i ON pil.item_id = i.id
    WHERE (:invoice_id IS NULL OR pih.id = :invoice_id)
    ORDER BY pih.invoice_date DESC, pil.id
    LIMIT 200',
    '{
        "layout": "table",
        "params": ["invoice_id"],
        "columns": [
            {"field": "id", "label": "Line ID"},
            {"field": "invoice_no", "label": "Invoice"},
            {"field": "item_name_variant", "label": "Item (Original)"},
            {"field": "canonical_name", "label": "Item (Canonical)"},
            {"field": "hsn_code", "label": "HSN"},
            {"field": "quantity", "label": "Qty"},
            {"field": "rate", "label": "Rate"},
            {"field": "taxable_amount", "label": "Taxable"},
            {"field": "cgst_amount", "label": "CGST"},
            {"field": "sgst_amount", "label": "SGST"},
            {"field": "igst_amount", "label": "IGST"},
            {"field": "line_total", "label": "Total"}
        ],
        "emptyText": "No line items found."
    }',
    20
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json),
    ord = VALUES(ord);

-- ============================================================================
-- Update Items Page with HSN Management
-- ============================================================================

-- Component: Update item HSN code
INSERT INTO `app_components` (`page_slug`, `comp_type`, `name`, `sql_text`, `meta_json`, `ord`)
VALUES (
    'items',
    'form',
    'update_item_hsn',
    'UPDATE items SET hsn = ?, hsn_code = ? WHERE id = ?',
    '{
        "inputs": [
            {"name": "item_id", "label": "Item ID", "type": "number"},
            {"name": "hsn_code", "label": "HSN Code", "placeholder": "e.g., 19059020"},
            {"name": "hsn_code_confirm", "label": "HSN Code (confirm)", "placeholder": "Same as above"}
        ],
        "submit": "Update HSN",
        "params": ["hsn_code", "hsn_code_confirm", "item_id"],
        "success": "HSN code updated successfully."
    }',
    40
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json),
    ord = VALUES(ord);

-- Component: List items missing HSN codes
INSERT INTO `app_components` (`page_slug`, `comp_type`, `name`, `sql_text`, `meta_json`, `ord`)
VALUES (
    'items',
    'list',
    'items_missing_hsn',
    'SELECT 
        id,
        canonical_name,
        hsn,
        hsn_code,
        is_active
    FROM items
    WHERE (hsn IS NULL OR hsn = "") AND (hsn_code IS NULL OR hsn_code = "")
    ORDER BY canonical_name
    LIMIT 50',
    '{
        "layout": "table",
        "columns": [
            {"field": "id", "label": "ID"},
            {"field": "canonical_name", "label": "Name"},
            {"field": "hsn", "label": "HSN (old)"},
            {"field": "hsn_code", "label": "HSN Code"},
            {"field": "is_active", "label": "Active"}
        ],
        "emptyText": "All items have HSN codes assigned!"
    }',
    50
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json),
    ord = VALUES(ord);

-- ============================================================================
-- Add Diagnostics Page for Data Quality
-- ============================================================================

INSERT INTO `app_pages` (`slug`, `title`, `page_type`, `template`)
VALUES (
    'data_diagnostics',
    'Data Quality Diagnostics',
    'list',
    '<div class="card">
        <h3>Data Quality Diagnostics</h3>
        <p class="muted">Check data quality and normalization status.</p>
        {{component:hsn_coverage}}
        {{component:variant_stats}}
        {{component:invoice_migration_status}}
    </div>'
)
ON DUPLICATE KEY UPDATE 
    title = VALUES(title),
    template = VALUES(template);

-- Component: HSN coverage statistics
INSERT INTO `app_components` (`page_slug`, `comp_type`, `name`, `sql_text`, `meta_json`, `ord`)
VALUES (
    'data_diagnostics',
    'list',
    'hsn_coverage',
    'SELECT 
        "HSN Coverage" as metric,
        COUNT(*) as total_items,
        COUNT(CASE WHEN hsn IS NOT NULL AND hsn != "" THEN 1 END) as items_with_hsn_old,
        COUNT(CASE WHEN hsn_code IS NOT NULL AND hsn_code != "" THEN 1 END) as items_with_hsn_code,
        ROUND(COUNT(CASE WHEN hsn_code IS NOT NULL AND hsn_code != "" THEN 1 END) * 100.0 / COUNT(*), 2) as coverage_percentage
    FROM items',
    '{
        "layout": "stat",
        "columns": [
            {"field": "metric", "label": "Metric"},
            {"field": "total_items", "label": "Total Items"},
            {"field": "items_with_hsn_code", "label": "Items with HSN"},
            {"field": "coverage_percentage", "label": "Coverage %"}
        ],
        "emptyText": "No data available."
    }',
    10
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json),
    ord = VALUES(ord);

-- Component: Variant statistics
INSERT INTO `app_components` (`page_slug`, `comp_type`, `name`, `sql_text`, `meta_json`, `ord`)
VALUES (
    'data_diagnostics',
    'list',
    'variant_stats',
    'SELECT 
        variant_type,
        COUNT(*) as variant_count,
        COUNT(DISTINCT item_id) as unique_items,
        ROUND(COUNT(*) * 1.0 / COUNT(DISTINCT item_id), 2) as avg_variants_per_item
    FROM item_name_variants
    GROUP BY variant_type
    ORDER BY variant_count DESC',
    '{
        "layout": "table",
        "columns": [
            {"field": "variant_type", "label": "Variant Type"},
            {"field": "variant_count", "label": "Total Variants"},
            {"field": "unique_items", "label": "Unique Items"},
            {"field": "avg_variants_per_item", "label": "Avg per Item"}
        ],
        "emptyText": "No variants recorded yet."
    }',
    20
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json),
    ord = VALUES(ord);

-- Component: Invoice migration status
INSERT INTO `app_components` (`page_slug`, `comp_type`, `name`, `sql_text`, `meta_json`, `ord`)
VALUES (
    'data_diagnostics',
    'list',
    'invoice_migration_status',
    'SELECT 
        "purchase_invoice_header" as table_name,
        COUNT(*) as record_count
    FROM purchase_invoice_header
    UNION ALL
    SELECT 
        "purchase_invoice_line_items" as table_name,
        COUNT(*) as record_count
    FROM purchase_invoice_line_items
    UNION ALL
    SELECT 
        "item_name_variants" as table_name,
        COUNT(*) as record_count
    FROM item_name_variants',
    '{
        "layout": "table",
        "columns": [
            {"field": "table_name", "label": "Table"},
            {"field": "record_count", "label": "Records"}
        ],
        "emptyText": "No data migrated yet."
    }',
    30
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json),
    ord = VALUES(ord);

-- ============================================================================
-- Update Navigation/Dashboard with New Links
-- ============================================================================

-- Update dashboard to include links to new pages
UPDATE `app_pages`
SET `template` = REPLACE(
    `template`,
    '</div>',
    '<ul class="nav-links">
        <li><a href="?p=item_variants">Item Name Variants</a></li>
        <li><a href="?p=purchase_invoices">Purchase Invoices (Normalized)</a></li>
        <li><a href="?p=data_diagnostics">Data Quality</a></li>
    </ul></div>'
)
WHERE `slug` = 'dashboard'
  AND `template` NOT LIKE '%item_variants%';

-- ============================================================================
-- Validation Query
-- ============================================================================

SELECT 
    'Metadata updated successfully' as status,
    COUNT(*) as new_pages_added
FROM app_pages
WHERE slug IN ('item_variants', 'purchase_invoices', 'data_diagnostics');

SELECT 
    page_slug,
    COUNT(*) as components_added
FROM app_components
WHERE page_slug IN ('item_variants', 'purchase_invoices', 'data_diagnostics', 'items')
GROUP BY page_slug;
