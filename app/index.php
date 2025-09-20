<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GST Accounting App</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
</head>
<body>

    <header>
        <h1>GST Accounting</h1>
    </header>

    <main class="container">

        <section id="view-section">
            <div class="section-header">
                <h2>Latest Invoices</h2>
                <button id="new-invoice-btn" class="btn btn-primary">New Invoice</button>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice No</th>
                            <th>Party Name</th>
                            <th>Date</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody id="invoice-table-body">
                        <!-- Invoice rows will be loaded here by JavaScript -->
                    </tbody>
                </table>
            </div>
        </section>

        <section id="form-section" class="hidden">
            <div class="section-header">
                <h2 id="form-title">Create Invoice</h2>
            </div>
            
            <form id="invoice-form">
                <input type="hidden" id="invoice_id" name="invoice_id" value="">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="party_name">Party Name</label>
                        <input type="text" id="party_name" name="party_name" required placeholder="Select or search for a party...">
                    </div>
                    <div class="form-group">
                        <label for="invoice_date">Invoice Date</label>
                        <input type="date" id="invoice_date" name="invoice_date" required>
                    </div>
                </div>

                <h3>Items</h3>
                <div class="table-container">
                    <table id="invoice-items-table">
                        <thead>
                            <tr>
                                <th class="item-col">Item Description</th>
                                <th class="quantity-col">Quantity</th>
                                <th class="rate-col">Rate</th>
                                <th class="actions-col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Item rows will be added here by JavaScript -->
                        </tbody>
                    </table>
                </div>
                <!-- THIS IS THE FIX: onclick="addInvoiceItemRow()" -->
                <button type="button" id="add-item-btn" class="btn btn-secondary">Add Item</button>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Invoice</button>
                    <button type="button" id="delete-invoice-btn" class="btn btn-danger hidden">Delete Invoice</button> 
                    <button type="button" id="cancel-invoice-btn" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </section>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>