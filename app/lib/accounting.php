<?php
/**
 * Core Accounting Functions
 * Memory-efficient functions for double-entry bookkeeping
 */

class AccountingCore {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get account balance as of a specific date
     */
    public function getAccountBalance($account_code, $as_of_date = null) {
        if (!$as_of_date) {
            $as_of_date = date('Y-m-d');
        }
        
        $sql = "SELECT 
                    SUM(CASE WHEN a.account_type IN ('ASSET', 'EXPENSE') 
                        THEN COALESCE(jl.debit_amount, 0) - COALESCE(jl.credit_amount, 0) 
                        ELSE COALESCE(jl.credit_amount, 0) - COALESCE(jl.debit_amount, 0) END) as balance
                FROM journal_lines jl
                JOIN journal_entries je ON jl.entry_id = je.id
                JOIN accounts a ON jl.account_code = a.code
                WHERE jl.account_code = ? AND je.entry_date <= ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$account_code, $as_of_date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['balance'] ?? 0;
    }
    
    /**
     * Get trial balance as of a specific date
     */
    public function getTrialBalance($as_of_date = null) {
        if (!$as_of_date) {
            $as_of_date = date('Y-m-d');
        }
        
        $sql = "SELECT 
                    a.code,
                    a.name,
                    a.account_type,
                    SUM(COALESCE(jl.debit_amount, 0)) as total_debits,
                    SUM(COALESCE(jl.credit_amount, 0)) as total_credits,
                    SUM(CASE WHEN a.account_type IN ('ASSET', 'EXPENSE') 
                        THEN COALESCE(jl.debit_amount, 0) - COALESCE(jl.credit_amount, 0) 
                        ELSE COALESCE(jl.credit_amount, 0) - COALESCE(jl.debit_amount, 0) END) as balance
                FROM accounts a
                LEFT JOIN journal_lines jl ON a.code = jl.account_code
                LEFT JOIN journal_entries je ON jl.entry_id = je.id
                WHERE a.is_active = 1 AND (je.entry_date <= ? OR je.entry_date IS NULL)
                GROUP BY a.code, a.name, a.account_type
                HAVING total_debits > 0 OR total_credits > 0 OR a.account_type IS NOT NULL
                ORDER BY a.code";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$as_of_date]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create a new journal entry
     */
    public function createJournalEntry($entry_date, $description, $reference, $journal_lines) {
        try {
            $this->db->beginTransaction();
            
            // Validate that debits equal credits
            $total_debits = array_sum(array_column($journal_lines, 'debit_amount'));
            $total_credits = array_sum(array_column($journal_lines, 'credit_amount'));
            
            if (abs($total_debits - $total_credits) > 0.01) {
                throw new Exception("Debits ($total_debits) must equal credits ($total_credits)");
            }
            
            // Create journal entry header
            $sql = "INSERT INTO journal_entries (entry_date, description, amount, reference_no, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$entry_date, $description, $total_debits, $reference]);
            $journal_entry_id = $this->db->lastInsertId();
            
            // Create journal lines
            $sql = "INSERT INTO journal_lines (entry_id, account_code, account_type, debit_amount, credit_amount) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            
            foreach ($journal_lines as $line) {
                $stmt->execute([
                    $journal_entry_id,
                    $line['account_code'],
                    'ledger', // Default account type
                    $line['debit_amount'] ?? 0,
                    $line['credit_amount'] ?? 0
                ]);
            }
            
            $this->db->commit();
            return $journal_entry_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get account ledger for a date range
     */
    public function getAccountLedger($account_code, $from_date = null, $to_date = null) {
        if (!$from_date) {
            $from_date = date('Y-m-01'); // First day of current month
        }
        if (!$to_date) {
            $to_date = date('Y-m-d'); // Today
        }
        
        $sql = "SELECT 
                    je.entry_date,
                    je.description,
                    je.reference_no as reference,
                    je.description as line_description,
                    COALESCE(jl.debit_amount, 0) as debit_amount,
                    COALESCE(jl.credit_amount, 0) as credit_amount,
                    jl.id as line_id
                FROM journal_lines jl
                JOIN journal_entries je ON jl.entry_id = je.id
                WHERE jl.account_code = ? 
                AND je.entry_date BETWEEN ? AND ?
                ORDER BY je.entry_date, je.id, jl.id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$account_code, $from_date, $to_date]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Post invoice to accounting
     */
    public function postInvoiceToAccounting($invoice_id) {
        try {
            // Check if already posted
            $check_sql = "SELECT id FROM journal_entries WHERE source_type = 'INVOICE' AND source_id = ?";
            $stmt = $this->db->prepare($check_sql);
            $stmt->execute([$invoice_id]);
            if ($stmt->fetch()) {
                throw new Exception("Invoice already posted to accounting");
            }
            
            // Get invoice details (assuming invoices table exists)
            $invoice_sql = "SELECT * FROM invoices WHERE id = ?";
            $stmt = $this->db->prepare($invoice_sql);
            $stmt->execute([$invoice_id]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invoice) {
                throw new Exception("Invoice not found");
            }
            
            $this->db->beginTransaction();
            
            // Create journal entry for invoice
            $description = "Sales Invoice #" . $invoice['invoice_number'];
            $reference = $invoice['invoice_number'];
            
            // Prepare journal lines
            $journal_lines = [];
            
            // Debit: Accounts Receivable
            $journal_lines[] = [
                'account_code' => '1100',
                'description' => $description,
                'debit_amount' => $invoice['total_amount'],
                'credit_amount' => 0
            ];
            
            // Credit: Sales Revenue
            $journal_lines[] = [
                'account_code' => '4100',
                'description' => $description,
                'debit_amount' => 0,
                'credit_amount' => $invoice['taxable_amount']
            ];
            
            // Credit: GST Output accounts
            if ($invoice['cgst_amount'] > 0) {
                $journal_lines[] = [
                    'account_code' => '2301',
                    'description' => 'CGST on ' . $description,
                    'debit_amount' => 0,
                    'credit_amount' => $invoice['cgst_amount']
                ];
            }
            
            if ($invoice['sgst_amount'] > 0) {
                $journal_lines[] = [
                    'account_code' => '2302',
                    'description' => 'SGST on ' . $description,
                    'debit_amount' => 0,
                    'credit_amount' => $invoice['sgst_amount']
                ];
            }
            
            if ($invoice['igst_amount'] > 0) {
                $journal_lines[] = [
                    'account_code' => '2303',
                    'description' => 'IGST on ' . $description,
                    'debit_amount' => 0,
                    'credit_amount' => $invoice['igst_amount']
                ];
            }
            
            // Create the journal entry
            $journal_entry_id = $this->createJournalEntry($invoice['invoice_date'], $description, $reference, $journal_lines);
            
            // Update journal entry with source information
            $update_sql = "UPDATE journal_entries SET source_type = 'INVOICE', source_id = ? WHERE id = ?";
            $stmt = $this->db->prepare($update_sql);
            $stmt->execute([$invoice_id, $journal_entry_id]);
            
            $this->db->commit();
            return $journal_entry_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get financial statements data
     */
    public function getProfitLossStatement($from_date, $to_date) {
        $sql = "SELECT 
                    a.code,
                    a.name,
                    a.account_type,
                    SUM(CASE WHEN a.account_type = 'INCOME' 
                        THEN COALESCE(jl.credit_amount, 0) - COALESCE(jl.debit_amount, 0) 
                        ELSE COALESCE(jl.debit_amount, 0) - COALESCE(jl.credit_amount, 0) END) as amount
                FROM accounts a
                LEFT JOIN journal_lines jl ON a.code = jl.account_code
                LEFT JOIN journal_entries je ON jl.entry_id = je.id
                WHERE a.account_type IN ('INCOME', 'EXPENSE') 
                AND a.is_active = 1
                AND (je.entry_date BETWEEN ? AND ? OR je.entry_date IS NULL)
                GROUP BY a.code, a.name, a.account_type
                HAVING amount != 0
                ORDER BY a.account_type, a.code";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$from_date, $to_date]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getBalanceSheet($as_of_date) {
        $sql = "SELECT 
                    a.code,
                    a.name,
                    a.account_type,
                    SUM(CASE WHEN a.account_type IN ('ASSET', 'EXPENSE') 
                        THEN COALESCE(jl.debit_amount, 0) - COALESCE(jl.credit_amount, 0) 
                        ELSE COALESCE(jl.credit_amount, 0) - COALESCE(jl.debit_amount, 0) END) as balance
                FROM accounts a
                LEFT JOIN journal_lines jl ON a.code = jl.account_code
                LEFT JOIN journal_entries je ON jl.entry_id = je.id
                WHERE a.account_type IN ('ASSET', 'LIABILITY', 'EQUITY') 
                AND a.is_active = 1
                AND (je.entry_date <= ? OR je.entry_date IS NULL)
                GROUP BY a.code, a.name, a.account_type
                HAVING balance != 0
                ORDER BY a.account_type, a.code";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$as_of_date]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Utility functions
 */
function formatAmount($amount) {
    return number_format($amount, 2);
}

function formatCurrency($amount) {
    return '₹ ' . number_format($amount, 2);
}

function getAccountTypeClass($account_type) {
    return 'account-' . strtolower($account_type);
}
?>