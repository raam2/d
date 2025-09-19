<?php
/**
 * Accounting Library
 * Core functions for double-entry bookkeeping
 */

require_once __DIR__ . '/database.php';

/**
 * Post a journal entry with validation
 */
function post_journal_entry($date, $description, $lines, $reference = null, $source_type = 'MANUAL', $source_id = null) {
    global $db;
    
    return db_transaction(function($db) use ($date, $description, $lines, $reference, $source_type, $source_id) {
        // Validate that debits equal credits
        $total_debit = 0;
        $total_credit = 0;
        
        foreach ($lines as $line) {
            $total_debit += $line['debit'] ?? 0;
            $total_credit += $line['credit'] ?? 0;
        }
        
        if (abs($total_debit - $total_credit) > 0.01) {
            throw new Exception("Journal entry is not balanced. Debits: ₹$total_debit, Credits: ₹$total_credit");
        }
        
        // Insert journal entry header
        $stmt = $db->prepare("INSERT INTO journal_entries (entry_date, reference, description, total_debit, total_credit, source_type, source_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$date, $reference, $description, $total_debit, $total_credit, $source_type, $source_id]);
        
        $entry_id = $db->lastInsertId();
        
        // Insert journal lines
        $line_stmt = $db->prepare("INSERT INTO journal_lines (entry_id, account_code, debit_amount, credit_amount, description, line_number) VALUES (?, ?, ?, ?, ?, ?)");
        
        $line_number = 1;
        foreach ($lines as $line) {
            $debit = $line['debit'] ?? 0;
            $credit = $line['credit'] ?? 0;
            $line_desc = $line['description'] ?? '';
            
            // Validate account exists
            $acc_stmt = $db->prepare("SELECT code FROM accounts WHERE code = ? AND is_active = 1");
            $acc_stmt->execute([$line['account']]);
            if (!$acc_stmt->fetch()) {
                throw new Exception("Account {$line['account']} not found or inactive");
            }
            
            $line_stmt->execute([$entry_id, $line['account'], $debit, $credit, $line_desc, $line_number]);
            $line_number++;
        }
        
        return $entry_id;
    });
}

/**
 * Get account balance
 */
function get_account_balance($account_code, $as_of_date = null) {
    global $db;
    
    $sql = "SELECT 
                COALESCE(SUM(l.debit_amount), 0) as total_debit,
                COALESCE(SUM(l.credit_amount), 0) as total_credit,
                a.account_type
            FROM accounts a
            LEFT JOIN journal_lines l ON a.code = l.account_code
            LEFT JOIN journal_entries e ON l.entry_id = e.id";
    
    $params = [$account_code];
    
    if ($as_of_date) {
        $sql .= " AND e.entry_date <= ?";
        $params[] = $as_of_date;
    }
    
    $sql .= " WHERE a.code = ? GROUP BY a.code, a.account_type";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(array_reverse($params)); // Reverse because we added account_code at beginning
    $result = $stmt->fetch();
    
    if (!$result) {
        return 0;
    }
    
    $debit = $result['total_debit'];
    $credit = $result['total_credit'];
    $type = $result['account_type'];
    
    // Calculate balance based on account type
    // Assets and Expenses: Debit positive, Credit negative
    // Liabilities, Equity, Income: Credit positive, Debit negative
    if (in_array($type, ['ASSET', 'EXPENSE'])) {
        return $debit - $credit;
    } else {
        return $credit - $debit;
    }
}

/**
 * Get trial balance
 */
function get_trial_balance($as_of_date = null) {
    global $db;
    
    $sql = "SELECT 
                a.code,
                a.name,
                a.account_type,
                COALESCE(SUM(l.debit_amount), 0) as total_debit,
                COALESCE(SUM(l.credit_amount), 0) as total_credit
            FROM accounts a
            LEFT JOIN journal_lines l ON a.code = l.account_code
            LEFT JOIN journal_entries e ON l.entry_id = e.id";
    
    $params = [];
    
    if ($as_of_date) {
        $sql .= " WHERE e.entry_date <= ?";
        $params[] = $as_of_date;
    }
    
    $sql .= " AND a.is_active = 1
             GROUP BY a.code, a.name, a.account_type
             HAVING (total_debit != 0 OR total_credit != 0)
             ORDER BY a.account_type, a.code";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $accounts = [];
    $total_debits = 0;
    $total_credits = 0;
    
    while ($row = $stmt->fetch()) {
        $debit = $row['total_debit'];
        $credit = $row['total_credit'];
        $type = $row['account_type'];
        
        // Calculate balance
        if (in_array($type, ['ASSET', 'EXPENSE'])) {
            $balance = $debit - $credit;
        } else {
            $balance = $credit - $debit;
        }
        
        // Show debits and credits in trial balance format
        $trial_debit = 0;
        $trial_credit = 0;
        
        if ($balance > 0) {
            if (in_array($type, ['ASSET', 'EXPENSE'])) {
                $trial_debit = $balance;
            } else {
                $trial_credit = $balance;
            }
        } elseif ($balance < 0) {
            if (in_array($type, ['ASSET', 'EXPENSE'])) {
                $trial_credit = abs($balance);
            } else {
                $trial_debit = abs($balance);
            }
        }
        
        if ($trial_debit != 0 || $trial_credit != 0) {
            $accounts[] = [
                'code' => $row['code'],
                'name' => $row['name'],
                'type' => $row['account_type'],
                'debit' => $trial_debit,
                'credit' => $trial_credit,
                'balance' => $balance
            ];
            
            $total_debits += $trial_debit;
            $total_credits += $trial_credit;
        }
    }
    
    return [
        'accounts' => $accounts,
        'total_debits' => $total_debits,
        'total_credits' => $total_credits,
        'is_balanced' => abs($total_debits - $total_credits) < 0.01
    ];
}

/**
 * Calculate GST split based on state codes
 */
function calculate_gst_split($taxable_amount, $gst_rate, $supplier_state, $recipient_state) {
    $gst_amount = round($taxable_amount * $gst_rate / 100, 2);
    
    if ($supplier_state === $recipient_state) {
        // Intra-state: CGST + SGST
        $cgst = round($gst_amount / 2, 2);
        $sgst = $gst_amount - $cgst; // Ensure exact total
        return [
            'cgst' => $cgst,
            'sgst' => $sgst,
            'igst' => 0,
            'total_gst' => $gst_amount
        ];
    } else {
        // Inter-state: IGST
        return [
            'cgst' => 0,
            'sgst' => 0,
            'igst' => $gst_amount,
            'total_gst' => $gst_amount
        ];
    }
}

/**
 * Post sales invoice to accounting
 */
function post_sales_invoice($invoice_id) {
    global $db;
    
    return db_transaction(function($db) use ($invoice_id) {
        // Get invoice details - try different possible table structures
        $invoice = null;
        $invoice_items = [];
        
        // Try the structure from the dump
        try {
            $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
            $stmt->execute([$invoice_id]);
            $invoice = $stmt->fetch();
            
            if ($invoice) {
                $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
                $stmt->execute([$invoice_id]);
                $invoice_items = $stmt->fetchAll();
            }
        } catch (Exception $e) {
            throw new Exception("Could not load invoice: " . $e->getMessage());
        }
        
        if (!$invoice) {
            throw new Exception("Invoice not found");
        }
        
        // Check if already posted
        $stmt = $db->prepare("SELECT id FROM journal_entries WHERE source_type = 'SALES_INVOICE' AND source_id = ?");
        $stmt->execute([$invoice_id]);
        if ($stmt->fetch()) {
            throw new Exception("Invoice already posted to accounting");
        }
        
        // Get company state from settings
        $company_state = get_setting('company_state', 'UP');
        
        // Calculate totals
        $total_taxable = 0;
        $total_cgst = 0;
        $total_sgst = 0;
        $total_igst = 0;
        
        foreach ($invoice_items as $item) {
            $taxable = $item['taxable_amount'] ?? ($item['quantity'] * $item['rate']);
            $cgst = $item['cgst_amount'] ?? 0;
            $sgst = $item['sgst_amount'] ?? 0;
            $igst = $item['igst_amount'] ?? 0;
            
            $total_taxable += $taxable;
            $total_cgst += $cgst;
            $total_sgst += $sgst;
            $total_igst += $igst;
        }
        
        $total_amount = $total_taxable + $total_cgst + $total_sgst + $total_igst;
        
        // Prepare journal lines
        $lines = [
            [
                'account' => '1100', // Accounts Receivable
                'debit' => $total_amount,
                'credit' => 0,
                'description' => 'Sales to customer'
            ],
            [
                'account' => '4100', // Sales Revenue
                'debit' => 0,
                'credit' => $total_taxable,
                'description' => 'Sales revenue'
            ]
        ];
        
        // Add GST accounts
        if ($total_cgst > 0) {
            $lines[] = [
                'account' => '2301', // CGST Output
                'debit' => 0,
                'credit' => $total_cgst,
                'description' => 'CGST collected'
            ];
        }
        
        if ($total_sgst > 0) {
            $lines[] = [
                'account' => '2302', // SGST Output
                'debit' => 0,
                'credit' => $total_sgst,
                'description' => 'SGST collected'
            ];
        }
        
        if ($total_igst > 0) {
            $lines[] = [
                'account' => '2303', // IGST Output
                'debit' => 0,
                'credit' => $total_igst,
                'description' => 'IGST collected'
            ];
        }
        
        // Post journal entry
        $invoice_number = $invoice['invoice_number'] ?? $invoice['no'] ?? $invoice['id'];
        $invoice_date = $invoice['invoice_date'] ?? $invoice['issue_date'] ?? date('Y-m-d');
        
        return post_journal_entry(
            $invoice_date,
            "Sales Invoice #$invoice_number",
            $lines,
            "INV-$invoice_number",
            'SALES_INVOICE',
            $invoice_id
        );
    });
}

/**
 * Get ledger for an account
 */
function get_account_ledger($account_code, $from_date = null, $to_date = null, $limit = 100, $offset = 0) {
    global $db;
    
    $sql = "SELECT 
                e.id as entry_id,
                e.entry_date,
                e.reference,
                e.description as entry_description,
                l.debit_amount,
                l.credit_amount,
                l.description as line_description
            FROM journal_lines l
            JOIN journal_entries e ON l.entry_id = e.id
            WHERE l.account_code = ?";
    
    $params = [$account_code];
    
    if ($from_date) {
        $sql .= " AND e.entry_date >= ?";
        $params[] = $from_date;
    }
    
    if ($to_date) {
        $sql .= " AND e.entry_date <= ?";
        $params[] = $to_date;
    }
    
    $sql .= " ORDER BY e.entry_date DESC, e.id DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}