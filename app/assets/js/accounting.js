/**
 * GST Accounting System - Vanilla JavaScript
 * Memory-efficient, no external dependencies
 */

// DOM utility functions
function $(selector) {
    return document.querySelector(selector);
}

function $$(selector) {
    return document.querySelectorAll(selector);
}

// Form validation utilities
function validateForm(formElement) {
    const requiredFields = formElement.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showFieldError(field, 'This field is required');
            isValid = false;
        } else {
            clearFieldError(field);
        }
    });
    
    return isValid;
}

function showFieldError(field, message) {
    clearFieldError(field);
    field.style.borderColor = 'var(--accent-red)';
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.style.color = 'var(--accent-red)';
    errorDiv.style.fontSize = '0.875rem';
    errorDiv.style.marginTop = '0.25rem';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
}

function clearFieldError(field) {
    field.style.borderColor = '';
    const errorDiv = field.parentNode.querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
    }
}

// Number formatting
function formatNumber(num) {
    return new Intl.NumberFormat('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(num);
}

function formatCurrency(num) {
    return '₹ ' + formatNumber(num);
}

// Journal Entry helpers
function addJournalLine() {
    const tbody = $('#journal-lines-tbody');
    if (!tbody) return;
    
    const rowCount = tbody.children.length + 1;
    const row = document.createElement('tr');
    
    row.innerHTML = `
        <td>
            <select name="lines[${rowCount}][account_code]" class="form-control" required>
                <option value="">Select Account</option>
                ${window.accountOptions || ''}
            </select>
        </td>
        <td>
            <input type="text" name="lines[${rowCount}][description]" class="form-control" placeholder="Line description">
        </td>
        <td>
            <input type="number" name="lines[${rowCount}][debit_amount]" class="form-control amount-input" 
                   step="0.01" min="0" placeholder="0.00" onchange="calculateTotals()">
        </td>
        <td>
            <input type="number" name="lines[${rowCount}][credit_amount]" class="form-control amount-input" 
                   step="0.01" min="0" placeholder="0.00" onchange="calculateTotals()">
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-danger" onclick="removeJournalLine(this)">Remove</button>
        </td>
    `;
    
    tbody.appendChild(row);
    calculateTotals();
}

function removeJournalLine(button) {
    const row = button.closest('tr');
    row.remove();
    calculateTotals();
}

function calculateTotals() {
    const debitInputs = $$('input[name*="[debit_amount]"]');
    const creditInputs = $$('input[name*="[credit_amount]"]');
    
    let totalDebits = 0;
    let totalCredits = 0;
    
    debitInputs.forEach(input => {
        const value = parseFloat(input.value) || 0;
        totalDebits += value;
    });
    
    creditInputs.forEach(input => {
        const value = parseFloat(input.value) || 0;
        totalCredits += value;
    });
    
    // Update total displays
    const debitTotal = $('#total-debits');
    const creditTotal = $('#total-credits');
    const difference = $('#difference');
    
    if (debitTotal) debitTotal.textContent = formatNumber(totalDebits);
    if (creditTotal) creditTotal.textContent = formatNumber(totalCredits);
    
    const diff = totalDebits - totalCredits;
    if (difference) {
        difference.textContent = formatNumber(Math.abs(diff));
        
        // Color coding for balance
        if (Math.abs(diff) < 0.01) {
            difference.style.color = 'var(--accent-green)';
        } else {
            difference.style.color = 'var(--accent-red)';
        }
    }
    
    // Update submit button state
    const submitBtn = $('#journal-submit-btn');
    if (submitBtn) {
        const isBalanced = Math.abs(diff) < 0.01 && totalDebits > 0;
        submitBtn.disabled = !isBalanced;
        submitBtn.textContent = isBalanced ? 'Save Journal Entry' : 'Balance Required';
    }
}

// Date helpers
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN');
}

function setTodayDate() {
    const today = new Date().toISOString().split('T')[0];
    const dateInputs = $$('input[type="date"]');
    dateInputs.forEach(input => {
        if (!input.value) {
            input.value = today;
        }
    });
}

// Modal/Dialog helpers
function showConfirmDialog(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    // Insert at top of main content
    const mainContent = $('.main-content');
    if (mainContent) {
        mainContent.insertBefore(alertDiv, mainContent.firstChild);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
}

// AJAX helper
function sendRequest(url, data, callback, errorCallback) {
    const xhr = new XMLHttpRequest();
    xhr.open(data ? 'POST' : 'GET', url);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                callback(response);
            } catch (e) {
                // If not JSON, treat as plain text
                callback(xhr.responseText);
            }
        } else {
            if (errorCallback) {
                errorCallback('Request failed: ' + xhr.status);
            }
        }
    };
    
    xhr.onerror = function() {
        if (errorCallback) {
            errorCallback('Network error');
        }
    };
    
    if (data) {
        const formData = new URLSearchParams(data).toString();
        xhr.send(formData);
    } else {
        xhr.send();
    }
}

// Table helpers
function sortTable(table, columnIndex, isNumeric = false) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aVal = a.children[columnIndex].textContent.trim();
        const bVal = b.children[columnIndex].textContent.trim();
        
        if (isNumeric) {
            return parseFloat(aVal.replace(/[^\d.-]/g, '')) - parseFloat(bVal.replace(/[^\d.-]/g, ''));
        } else {
            return aVal.localeCompare(bVal);
        }
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// Account selector helper
function createAccountSelector(selectElement, selectedCode = '') {
    if (!window.accounts) return;
    
    selectElement.innerHTML = '<option value="">Select Account</option>';
    
    window.accounts.forEach(account => {
        const option = document.createElement('option');
        option.value = account.code;
        option.textContent = `${account.code} - ${account.name}`;
        option.selected = account.code === selectedCode;
        selectElement.appendChild(option);
    });
}

// Print helper
function printReport() {
    window.print();
}

// Export helper (simple CSV)
function exportTableToCSV(tableId, filename) {
    const table = $(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tr');
    const csvContent = Array.from(rows).map(row => {
        const cells = row.querySelectorAll('th, td');
        return Array.from(cells).map(cell => {
            return '"' + cell.textContent.replace(/"/g, '""') + '"';
        }).join(',');
    }).join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
}

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    // Set today's date for date inputs
    setTodayDate();
    
    // Initialize journal entry calculations
    if ($('#journal-lines-tbody')) {
        calculateTotals();
    }
    
    // Add click handlers for common actions
    const addLineBtn = $('#add-journal-line-btn');
    if (addLineBtn) {
        addLineBtn.addEventListener('click', addJournalLine);
    }
    
    // Form validation on submit
    $$('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(form)) {
                e.preventDefault();
                showAlert('Please fill in all required fields', 'error');
            }
        });
    });
    
    // Amount input formatting
    $$('.amount-input').forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value) {
                const num = parseFloat(this.value);
                if (!isNaN(num)) {
                    this.value = num.toFixed(2);
                }
            }
        });
    });
    
    // Auto-calculate on amount changes
    $$('input[name*="amount"]').forEach(input => {
        input.addEventListener('input', calculateTotals);
    });
});

// Global functions for inline usage
window.addJournalLine = addJournalLine;
window.removeJournalLine = removeJournalLine;
window.calculateTotals = calculateTotals;
window.showConfirmDialog = showConfirmDialog;
window.printReport = printReport;
window.exportTableToCSV = exportTableToCSV;