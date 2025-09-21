document.addEventListener('DOMContentLoaded', function() {
    // --- Centralized Event Listeners ---
    document.getElementById('new-invoice-btn').addEventListener('click', () => showNewInvoiceForm());
    document.getElementById('add-item-btn').addEventListener('click', () => addInvoiceItemRow());
    document.getElementById('cancel-invoice-btn').addEventListener('click', () => hideNewInvoiceForm());
    document.getElementById('delete-invoice-btn').addEventListener('click', handleDeleteInvoice);
    document.getElementById('invoice-form').addEventListener('submit', handleFormSubmit);

    const tableBody = document.getElementById('invoice-table-body');
    if (tableBody) {
        loadLatestInvoices();
        tableBody.addEventListener('click', function(event) {
            const row = event.target.closest('tr');
            if (row && row.dataset.invoiceId) {
                loadInvoiceForEditing(row.dataset.invoiceId);
            }
        });
    }
    
    initializePartySelect('party_name');
    
    const itemsTable = document.getElementById('invoice-items-table');
    if (itemsTable) {
        itemsTable.addEventListener('click', function(event) {
            if (event.target.classList.contains('remove-item-btn')) {
                event.target.closest('tr').remove();
            }
        });
    }
});

/**
 * Initializes the Tom Select dropdown for the Party Name field.
 */
function initializePartySelect(elementId) {
    const el = document.getElementById(elementId);
    if (el.tomselect) {
        el.tomselect.destroy();
    }
    return new TomSelect(`#${elementId}`, {
        create: true,
        valueField: 'text',
        labelField: 'text',
        searchField: 'text',
        preload: 'focus',
        load: function(query, callback) {
            const url = `/app/api/index.php?action=get_parties&q=${encodeURIComponent(query)}&page=${this.page || 1}`;
            fetch(url).then(r => r.json()).then(j => callback(j.items, j)).catch(() => callback([], {}));
        },
        render: {
            option_create: (data, escape) => `<div class="create">Add <strong>${escape(data.input)}</strong>&hellip;</div>`,
            no_results: () => '<div class="no-results">No parties found.</div>',
        },
        pagination: true,
        perPage: 25,
    });
}

/**
 * Initializes a Tom Select dropdown for an item row.
 */
function initializeItemSelect(elementId, data = null) {
    const el = document.getElementById(elementId);
    if (el.tomselect) { el.tomselect.destroy(); }
    const ts = new TomSelect(`#${elementId}`, {
        create: true,
        valueField: 'text',
        labelField: 'text',
        searchField: 'text',
        preload: 'focus',
        load: function(query, callback) {
            const url = `/app/api/index.php?action=get_items&q=${encodeURIComponent(query)}&page=${this.page || 1}`;
            fetch(url).then(r => r.json()).then(j => callback(j.items, j)).catch(() => callback([], {}));
        },
        render: {
            option_create: (data, escape) => `<div class="create">Add <strong>${escape(data.input)}</strong>&hellip;</div>`,
            no_results: () => '<div class="no-results">No items found.</div>',
        },
        pagination: true,
        perPage: 25,
    });
    if (data) {
        ts.addOption({ text: data.name, value: data.name });
        ts.setValue(data.name);
    }
    return ts;
}

/**
 * Adds a new row to the invoice items table.
 */
function addInvoiceItemRow(item = null) {
    const itemsTableBody = document.querySelector('#invoice-items-table tbody');
    const newId = `item-select-${Date.now()}-${Math.random()}`;
    const row = document.createElement('tr');
    
    row.innerHTML = `
        <td><input type="text" id="${newId}" placeholder="Search or add an item..." required></td>
        <td><input type="number" name="quantity[]" value="${item ? item.quantity : 1}" min="0.01" step="0.01" required class="quantity-input"></td>
        <td><input type="number" name="rate[]" step="0.01" value="${item ? item.rate : ''}" placeholder="0.00" required class="rate-input"></td>
        <td><button type="button" class="remove-item-btn">Remove</button></td>
    `;
    itemsTableBody.appendChild(row);
    initializeItemSelect(newId, item ? { name: item.name } : null);
}

/**
 * Fetches and displays the list of the latest invoices.
 */
function loadLatestInvoices() {
    const tableBody = document.getElementById('invoice-table-body');
    if (!tableBody) return;
    tableBody.innerHTML = '<tr><td colspan="4">Loading...</td></tr>';
    fetch('/app/api/index.php?action=get_latest_invoices')
        .then(response => response.json())
        .then(result => {
            tableBody.innerHTML = '';
            if (result.success && result.data.length > 0) {
                result.data.forEach(invoice => {
                    const row = document.createElement('tr');
                    row.dataset.invoiceId = invoice.id;
                    row.className = 'invoice-row';
                    row.innerHTML = `
                        <td>${invoice.invoice_no}</td>
                        <td>${invoice.party_name || 'N/A'}</td>
                        <td>${invoice.invoice_date}</td>
                        <td>${parseFloat(invoice.total_amount).toFixed(2)}</td>
                    `;
                    tableBody.appendChild(row);
                });
            } else {
                 tableBody.innerHTML = `<tr><td colspan="4">${result.success ? 'No invoices found.' : (result.message || 'Failed to load data.')}</td></tr>`;
            }
        })
        .catch(error => {
            console.error('Error fetching invoices:', error);
            tableBody.innerHTML = '<tr><td colspan="4">Failed to load data. See console for details.</td></tr>';
        });
}

/**
 * Fetches the details for a specific invoice and populates the form for editing.
 */
function loadInvoiceForEditing(invoiceId) {
    fetch(`/app/api/index.php?action=get_invoice_details&id=${invoiceId}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showNewInvoiceForm(true); // We are editing
                const invoice = result.data;
                document.getElementById('invoice_id').value = invoice.id;
                document.getElementById('invoice_date').value = invoice.invoice_date;

                const partySelect = document.getElementById('party_name').tomselect;
                partySelect.addOption({ text: invoice.party_name, value: invoice.party_name });
                partySelect.setValue(invoice.party_name);

                document.querySelector('#invoice-items-table tbody').innerHTML = '';
                invoice.items.forEach(item => addInvoiceItemRow(item));
                
                document.querySelector('#invoice-form button[type="submit"]').textContent = 'Update Invoice';
            } else {
                alert('Error: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error loading invoice details:', error);
            alert('Failed to load invoice details.');
        });
}

/**
 * Shows the invoice form and hides the main list view.
 */
function showNewInvoiceForm(isEditing = false) {
    document.getElementById('view-section').classList.add('hidden');
    document.getElementById('form-section').classList.remove('hidden');
    
    document.getElementById('delete-invoice-btn').classList.toggle('hidden', !isEditing);

    if (!isEditing) {
        document.getElementById('invoice-form').reset();
        document.getElementById('invoice_id').value = '';
        document.querySelector('#invoice-form button[type="submit"]').textContent = 'Save Invoice';
        document.querySelector('#invoice-items-table tbody').innerHTML = '';
        addInvoiceItemRow(); // Add one row for new invoices
    }
}

/**
 * Hides the invoice form and shows the main list view.
 */
function hideNewInvoiceForm() {
    document.getElementById('form-section').classList.add('hidden');
    document.getElementById('view-section').classList.remove('hidden');
    document.getElementById('invoice-form').reset();
    document.getElementById('invoice_id').value = '';
    document.getElementById('delete-invoice-btn').classList.add('hidden');
    document.querySelector('#invoice-items-table tbody').innerHTML = '';
    initializePartySelect('party_name'); // Re-initialize to clear it
}

/**
 * Handles the click of the "Delete Invoice" button.
 */
function handleDeleteInvoice() {
    const invoiceId = document.getElementById('invoice_id').value;
    if (!invoiceId) return;

    if (confirm('Are you sure you want to permanently delete this invoice? This action cannot be undone.')) {
        fetch('/app/api/index.php?action=delete_invoice', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ invoice_id: invoiceId })
        })
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            if (result.success) {
                hideNewInvoiceForm();
                loadLatestInvoices();
            }
        })
        .catch(error => {
            console.error('Error deleting invoice:', error);
            alert('An unexpected error occurred during deletion.');
        });
    }
}

/**
 * Handles the submission of the invoice form (for both creating and updating).
 */
function handleFormSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    const originalButtonText = submitButton.textContent;
    submitButton.textContent = 'Saving...';

    const invoiceId = document.getElementById('invoice_id').value;
    const partyName = document.getElementById('party_name').value;
    const invoiceDate = document.getElementById('invoice_date').value;
    
    const items = [];
    document.querySelectorAll('#invoice-items-table tbody tr').forEach(row => {
        const tomSelect = row.querySelector('.ts-wrapper').tomselect;
        const itemName = tomSelect ? tomSelect.getValue() : '';
        const quantity = row.querySelector('.quantity-input').value;
        const rate = row.querySelector('.rate-input').value;
        if (itemName && quantity && rate) {
            items.push({ name: itemName, quantity: parseFloat(quantity), rate: parseFloat(rate) });
        }
    });

    if (!partyName || !invoiceDate || items.length === 0) {
        alert('Please fill in party name, date, and at least one item.');
        submitButton.disabled = false;
        submitButton.textContent = originalButtonText;
        return;
    }

    const invoiceData = { party_name: partyName, invoice_date: invoiceDate, items: items };
    let apiUrl = '/app/api/index.php?action=create_invoice';
    
    if (invoiceId) {
        invoiceData.invoice_id = invoiceId;
        apiUrl = '/app/api/index.php?action=update_invoice';
    }

    fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(invoiceData)
    })
    .then(response => response.json())
    .then(result => {
        alert(result.message);
        if (result.success) {
            hideNewInvoiceForm();
            loadLatestInvoices();
        }
    })
    .catch(error => {
        console.error('Error saving invoice:', error);
        alert('An unexpected error occurred.');
    })
    .finally(() => {
        submitButton.disabled = false;
        submitButton.textContent = originalButtonText;
    });
}
