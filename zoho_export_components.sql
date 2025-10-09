-- Zoho Books Export Page and Components
-- Add this to your database to integrate Zoho export functionality into the app

-- Create the export page
INSERT INTO app_pages (slug, title, page_type, template) 
VALUES (
    'zoho_export',
    'Zoho Books Export',
    'workspace',
    '<div class="card">
        <h1>📤 Zoho Books Data Export</h1>
        <p class="muted">Export your accounting data to CSV files compatible with Zoho Books import.</p>
        <p><strong>Export Order:</strong> Always export and import in this sequence:</p>
        <ol>
            <li>Contacts (Customers & Vendors)</li>
            <li>Items (Products)</li>
            <li>Invoices (Sales & Purchase)</li>
        </ol>
        <hr style="border-color: #1f2937; margin: 20px 0;">
        {{component:export_instructions}}
        <hr style="border-color: #1f2937; margin: 20px 0;">
        {{component:export_stats}}
        <hr style="border-color: #1f2937; margin: 20px 0;">
        <h3>Export Actions</h3>
        <p class="muted">Click the buttons below to generate export files:</p>
        {{component:export_contacts_btn}}
        {{component:export_items_btn}}
        {{component:export_sales_btn}}
        {{component:export_purchases_btn}}
    </div>'
)
ON DUPLICATE KEY UPDATE 
    title = VALUES(title),
    template = VALUES(template);

-- Export statistics component
INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES (
    'zoho_export',
    'list',
    'export_stats',
    'SELECT 
        "Total Contacts" as metric,
        COUNT(*) as value
    FROM parties
    UNION ALL
    SELECT 
        "Active Items" as metric,
        COUNT(*) as value
    FROM items
    WHERE is_active = 1
    UNION ALL
    SELECT 
        "Final Sales Invoices" as metric,
        COUNT(*) as value
    FROM invoices
    WHERE inv_type = "sale" AND status = "final"
    UNION ALL
    SELECT 
        "Final Purchase Invoices" as metric,
        COUNT(*) as value
    FROM invoices
    WHERE inv_type = "purchase" AND status = "final"',
    '{
        "layout": "stat",
        "columns": [
            {"field": "metric", "label": "Metric"},
            {"field": "value", "label": "Count"}
        ],
        "emptyText": "No data available for export"
    }',
    10
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json);

-- Export instructions list
INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES (
    'zoho_export',
    'list',
    'export_instructions',
    'SELECT 
        1 as step,
        "Generate Export Files" as task,
        "Click export buttons below to create CSV files" as description
    UNION ALL
    SELECT 2, "Access Zoho Books", "Visit https://books.zoho.in/app and log in"
    UNION ALL
    SELECT 3, "Go to Import Data", "Settings → Import Data"
    UNION ALL
    SELECT 4, "Import Contacts First", "Upload contacts CSV and map fields"
    UNION ALL
    SELECT 5, "Import Items Second", "Upload items CSV and configure tax rates"
    UNION ALL
    SELECT 6, "Import Invoices Last", "Upload sales/purchase invoice CSVs"
    UNION ALL
    SELECT 7, "Verify Data", "Check import logs and verify totals"',
    '{
        "columns": [
            {"field": "step", "label": "Step"},
            {"field": "task", "label": "Task"},
            {"field": "description", "label": "Description"}
        ],
        "emptyText": "No instructions available"
    }',
    20
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json);

-- Note: The actual export actions are handled by zoho_export.php
-- These are just informational links/buttons that direct to that script

-- You can add these as simple HTML components or external links
-- For now, we'll use the form system to display links

INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES (
    'zoho_export',
    'list',
    'export_contacts_btn',
    'SELECT 
        "Export Contacts" as action,
        "zoho_export.php?export=contacts" as url,
        "Export all parties (customers and vendors)" as description',
    '{
        "columns": [
            {"field": "action", "label": "Action"},
            {"field": "description", "label": "Description"}
        ]
    }',
    30
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json);

INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES (
    'zoho_export',
    'list',
    'export_items_btn',
    'SELECT 
        "Export Items" as action,
        "zoho_export.php?export=items" as url,
        "Export all active items/products" as description',
    '{
        "columns": [
            {"field": "action", "label": "Action"},
            {"field": "description", "label": "Description"}
        ]
    }',
    40
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json);

INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES (
    'zoho_export',
    'list',
    'export_sales_btn',
    'SELECT 
        "Export Sales Invoices" as action,
        "zoho_export.php?export=sales" as url,
        "Export all finalized sales invoices" as description',
    '{
        "columns": [
            {"field": "action", "label": "Action"},
            {"field": "description", "label": "Description"}
        ]
    }',
    50
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json);

INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES (
    'zoho_export',
    'list',
    'export_purchases_btn',
    'SELECT 
        "Export Purchase Invoices" as action,
        "zoho_export.php?export=purchases" as url,
        "Export all finalized purchase invoices" as description',
    '{
        "columns": [
            {"field": "action", "label": "Action"},
            {"field": "description", "label": "Description"}
        ]
    }',
    60
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json);

-- Add a quick link to the documentation
INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES (
    'zoho_export',
    'list',
    'export_docs',
    'SELECT 
        "📖 Full Import Guide" as resource,
        "ZOHO_IMPORT_GUIDE.md" as location,
        "Complete step-by-step instructions for importing to Zoho Books" as description',
    '{
        "columns": [
            {"field": "resource", "label": "Resource"},
            {"field": "location", "label": "File"},
            {"field": "description", "label": "Description"}
        ]
    }',
    70
)
ON DUPLICATE KEY UPDATE 
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json);
