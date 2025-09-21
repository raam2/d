<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GST Accounting - Invoices</title>
    <base href="/app/">
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- 1. Link the local Tom Select CSS file -->
    <link rel="stylesheet" href="assets/css/tom-select.css">
</head>
<body>

    <div id="view-section">
        <h1>Recent Invoices</h1>
        <div class="toolbar">
            <button onclick="showNewInvoiceForm()">+ New Invoice</button>
            <button onclick="loadLatestInvoices()">Refresh</button>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Invoice Number</th>
                    <th>Party Name</th>
                    <th>Date</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody id="invoice-table-body">
                <!-- Data loaded by JS -->
            </tbody>
        </table>
    </div>

    <div id="form-section" class="hidden">
        <h1>Create New Invoice</h1>
        <form id="invoice-form">
            <div class="form-grid">
                <label for="party_name">Party Name:</label>
                <div>
                    <!-- 2. This input is now just a placeholder for Tom Select -->
                    <input type="text" id="party_name" name="party_name" placeholder="Search for a party...">
                    <a href="#" class="add-new-link">(Add New Party)</a>
                </div>
                
                <label for="invoice_date">Invoice Date:</label>
                <input type="date" id="invoice_date" name="invoice_date" required>
            </div>

            <!-- 3. The <datalist> tags have been removed -->

            <h2>Invoice Items</h2>
            <table id="invoice-items-table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Quantity</th>
                        <th>Rate</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Item rows will be added here by JS -->
                </tbody>
            </table>
            <button type="button" onclick="addInvoiceItemRow()">+ Add Item</button>
            
            <hr>
            
            <div class="toolbar">
                <button type="submit">Save Invoice</button>
                <button type="button" onclick="hideNewInvoiceForm()">Cancel</button>
            </div>
        </form>
    </div>

    <!-- 4. Load Tom Select JS *before* your main script -->
    <script src="assets/js/tom-select.js"></script>
    <script src="/app/assets/js/main.js"></script>
</body>
</html>
