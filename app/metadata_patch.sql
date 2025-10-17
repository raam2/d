-- Metadata patch for Accounting Workspace UI
-- Safe to run multiple times; uses upserts so existing data is preserved.

START TRANSACTION;

INSERT INTO app_pages (id, slug, title, page_type, template)
VALUES
    (1, 'dashboard', 'Accounting Workspace', 'workspace', '<div class="card"><h1>{{title}}</h1><p class="muted">Central dashboard for memory-efficient GST accounting.</p><div class="grid">{{component:dashboard_summary}}</div><div class="grid">{{component:recent_activity}}</div></div>'),
    (2, 'parties', 'Parties', 'workspace', '<div class="card"><h1>Parties Master</h1><p class="muted">Maintain suppliers and customers; data stays inside the database.</p>{{component:party_list}}<hr class="divider" />{{component:party_form}}</div>'),
    (3, 'items', 'Items', 'workspace', '<div class="card"><h1>Inventory Items</h1><p class="muted">Items with GST rates and default pricing.</p>{{component:item_list}}<hr class="divider" />{{component:item_form}}</div>'),
    (4, 'invoices', 'Invoices', 'workspace', '<div class="card"><h1>Invoices</h1><p class="muted">Post sales and purchases directly from database metadata.</p>{{component:invoice_list}}<hr class="divider" />{{component:invoice_line_items}}</div>')
ON DUPLICATE KEY UPDATE
    slug = VALUES(slug),
    title = VALUES(title),
    page_type = VALUES(page_type),
    template = VALUES(template),
    updated_at = NOW();

INSERT INTO app_components (id, page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES
    (1, 'dashboard', 'list', 'dashboard_summary', 'SELECT party_type AS label, COUNT(*) AS total FROM parties GROUP BY party_type ORDER BY party_type', '{"slot":"summary","layout":"stat","columns":[{"label":"Type","field":"label"},{"label":"Total","field":"total"}],"emptyText":"No parties captured yet."}', 1),
    (2, 'dashboard', 'list', 'recent_activity', 'SELECT DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") AS occurred_at, level, message FROM diagnostics ORDER BY created_at DESC LIMIT 10', '{"slot":"activity","layout":"table","columns":[{"label":"When","field":"occurred_at"},{"label":"Level","field":"level"},{"label":"Message","field":"message"}],"emptyText":"No activity recorded."}', 2),
    (3, 'parties', 'list', 'party_list', 'SELECT id, name, gstin, party_type, city, state, email, phone, DATE_FORMAT(created_at, "%Y-%m-%d") AS created_on FROM parties ORDER BY created_at DESC LIMIT 200', '{"slot":"list","layout":"table","columns":[{"label":"Name","field":"name"},{"label":"GSTIN","field":"gstin"},{"label":"Type","field":"party_type"},{"label":"City","field":"city"},{"label":"State","field":"state"},{"label":"Email","field":"email"},{"label":"Phone","field":"phone"},{"label":"Created","field":"created_on"}],"actions":[{"label":"Edit","type":"link","href":"?p=parties&edit={{id}}"}]}', 1),
    (4, 'parties', 'form', 'party_form', 'INSERT INTO parties (name, gstin, party_type, city, state, email, phone) VALUES (:name, :gstin, :party_type, :city, :state, :email, :phone)', '{"slot":"form","method":"POST","success":"Party saved successfully.","fields":[{"name":"name","label":"Party Name","type":"text","required":true},{"name":"gstin","label":"GSTIN","type":"text","pattern":"^[0-9A-Z]{15}$","placeholder":"Optional"},{"name":"party_type","label":"Type","type":"select","options":[{"label":"Customer","value":"customer"},{"label":"Supplier","value":"supplier"},{"label":"Both","value":"both"}],"default":"customer"},{"name":"city","label":"City","type":"text"},{"name":"state","label":"State","type":"text","default":"Uttarakhand"},{"name":"email","label":"Email","type":"email"},{"name":"phone","label":"Phone","type":"text"}]}', 2),
    (5, 'items', 'list', 'item_list', 'SELECT id, name, hsn_code, unit, default_rate, cgst_rate, sgst_rate, igst_rate, DATE_FORMAT(created_at, "%Y-%m-%d") AS created_on FROM items ORDER BY created_at DESC LIMIT 200', '{"slot":"list","layout":"table","columns":[{"label":"Name","field":"name"},{"label":"HSN","field":"hsn_code"},{"label":"Unit","field":"unit"},{"label":"Rate","field":"default_rate"},{"label":"CGST","field":"cgst_rate"},{"label":"SGST","field":"sgst_rate"},{"label":"IGST","field":"igst_rate"},{"label":"Created","field":"created_on"}],"emptyText":"No items defined."}', 1),
    (6, 'items', 'form', 'item_form', 'INSERT INTO items (name, hsn_code, unit, default_rate, cgst_rate, sgst_rate, igst_rate) VALUES (:name, :hsn_code, :unit, :default_rate, :cgst_rate, :sgst_rate, :igst_rate)', '{"slot":"form","method":"POST","success":"Item saved successfully.","fields":[{"name":"name","label":"Item Name","type":"text","required":true},{"name":"hsn_code","label":"HSN","type":"text","required":true,"maxlength":8},{"name":"unit","label":"Unit","type":"text","default":"PCS"},{"name":"default_rate","label":"Default Rate","type":"number","step":"0.01"},{"name":"cgst_rate","label":"CGST %","type":"number","step":"0.01"},{"name":"sgst_rate","label":"SGST %","type":"number","step":"0.01"},{"name":"igst_rate","label":"IGST %","type":"number","step":"0.01"}]}', 2),
    (7, 'invoices', 'list', 'invoice_list', 'SELECT invoice_no, invoice_date, inv_type, status, place_of_supply, reverse_charge, itc_eligible FROM invoices ORDER BY invoice_date DESC, id DESC LIMIT 200', '{"slot":"list","layout":"table","columns":[{"label":"Invoice No","field":"invoice_no"},{"label":"Date","field":"invoice_date"},{"label":"Type","field":"inv_type"},{"label":"Status","field":"status"},{"label":"Place of Supply","field":"place_of_supply"},{"label":"Reverse Charge","field":"reverse_charge","format":"boolean"},{"label":"ITC Eligible","field":"itc_eligible","format":"boolean"}],"emptyText":"No invoices posted."}', 1),
    (8, 'invoices', 'list', 'invoice_line_items', 'SELECT i.invoice_no, ii.description, ii.quantity, ii.rate, ii.cgst_rate, ii.sgst_rate, ii.igst_rate, ii.line_total FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id ORDER BY i.invoice_date DESC, i.id DESC LIMIT 200', '{"slot":"detail","layout":"table","columns":[{"label":"Invoice","field":"invoice_no"},{"label":"Description","field":"description"},{"label":"Qty","field":"quantity"},{"label":"Rate","field":"rate"},{"label":"CGST %","field":"cgst_rate"},{"label":"SGST %","field":"sgst_rate"},{"label":"IGST %","field":"igst_rate"},{"label":"Line Total","field":"line_total"}]}', 2),
    (9, 'parties', 'action', 'delete_party', 'DELETE FROM parties WHERE id = :id', '{"slot":"list","method":"POST","params":["id"],"confirm":"Delete selected party?","success":"Party removed."}', 3)
ON DUPLICATE KEY UPDATE
    page_slug = VALUES(page_slug),
    comp_type = VALUES(comp_type),
    name = VALUES(name),
    sql_text = VALUES(sql_text),
    meta_json = VALUES(meta_json),
    ord = VALUES(ord),
    updated_at = NOW();

COMMIT;
