<?php
class Invoice {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * UPGRADED: Gets latest invoices, handling the new schema.
     */
    public function getLatest($limit = 50) {
        $sql = "SELECT
                    i.id,
                    i.invoice_no,
                    i.invoice_date,
                    COALESCE(p.name, 'N/A') as party_name,
                    COALESCE(i.total_amount, 0.00) as total_amount
                FROM invoices i
                LEFT JOIN parties p ON i.party_id = p.id
                ORDER BY i.invoice_date DESC, i.id DESC
                LIMIT :limit";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * UPGRADED: Gets a single invoice by ID, fetching the description from invoice_items.
     */
    public function getById($invoice_id) {
        $sql = "SELECT i.id, i.invoice_no, i.invoice_date, p.name as party_name
                FROM invoices i
                JOIN parties p ON i.party_id = p.id
                WHERE i.id = :invoice_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':invoice_id' => $invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) return null;

        // The key change is here: we select `description` from `invoice_items` itself.
        $item_sql = "SELECT ii.description as name, ii.quantity, ii.rate
                     FROM invoice_items ii
                     WHERE ii.invoice_id = :invoice_id";
        $item_stmt = $this->conn->prepare($item_sql);
        $item_stmt->execute([':invoice_id' => $invoice_id]);
        $invoice['items'] = $item_stmt->fetchAll(PDO::FETCH_ASSOC);

        return $invoice;
    }

    /**
     * UPGRADED: Creates an invoice, saving the description directly.
     */
    public function create($data) {
        if (empty($data['party_name']) || empty($data['invoice_date']) || empty($data['items'])) {
            return false;
        }

        $this->conn->beginTransaction();
        try {
            $party_id = $this->getOrCreateEntity('parties', 'name', $data['party_name']);
            
            $sql = "INSERT INTO invoices (invoice_date, party_id, invoice_no) VALUES (:invoice_date, :party_id, :invoice_no)";
            $stmt = $this->conn->prepare($sql);
            $invoice_no = 'INV-' . time();
            $stmt->execute([':invoice_date' => $data['invoice_date'], ':party_id' => $party_id, ':invoice_no' => $invoice_no]);
            $invoice_id = $this->conn->lastInsertId();

            // The key change is here: we insert `description` into `invoice_items`.
            $item_sql = "INSERT INTO invoice_items (invoice_id, item_id, description, quantity, rate) VALUES (:invoice_id, :item_id, :description, :quantity, :rate)";
            $item_stmt = $this->conn->prepare($item_sql);
            foreach ($data['items'] as $item) {
                // We still get or create the item to maintain relational integrity
                $item_id = $this->getOrCreateEntity('items', 'name', $item['name']); 
                $item_stmt->execute([
                    ':invoice_id' => $invoice_id,
                    ':item_id' => $item_id,
                    ':description' => $item['name'], // The item's name is its description now
                    ':quantity' => $item['quantity'],
                    ':rate' => $item['rate']
                ]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log('Invoice Creation Failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * UPGRADED: Updates an invoice, saving the description directly.
     */
    public function update($invoice_id, $data) {
        if (empty($data['party_name']) || empty($data['invoice_date']) || empty($data['items'])) return false;

        $this->conn->beginTransaction();
        try {
            $party_id = $this->getOrCreateEntity('parties', 'name', $data['party_name']);

            $sql = "UPDATE invoices SET invoice_date = :invoice_date, party_id = :party_id WHERE id = :invoice_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':invoice_date' => $data['invoice_date'], ':party_id' => $party_id, ':invoice_id' => $invoice_id]);

            $delete_stmt = $this->conn->prepare("DELETE FROM invoice_items WHERE invoice_id = :invoice_id");
            $delete_stmt->execute([':invoice_id' => $invoice_id]);

            $item_sql = "INSERT INTO invoice_items (invoice_id, item_id, description, quantity, rate) VALUES (:invoice_id, :item_id, :description, :quantity, :rate)";
            $item_stmt = $this->conn->prepare($item_sql);
            foreach ($data['items'] as $item) {
                $item_id = $this->getOrCreateEntity('items', 'name', $item['name']);
                $item_stmt->execute([
                    ':invoice_id' => $invoice_id,
                    ':item_id' => $item_id,
                    ':description' => $item['name'],
                    ':quantity' => $item['quantity'],
                    ':rate' => $item['rate']
                ]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log('Invoice Update Failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes an invoice and all its associated items. (No changes needed here)
     */
    public function delete($invoice_id) {
        $this->conn->beginTransaction();
        try {
            $item_stmt = $this->conn->prepare("DELETE FROM invoice_items WHERE invoice_id = :invoice_id");
            $item_stmt->execute([':invoice_id' => $invoice_id]);

            $invoice_stmt = $this->conn->prepare("DELETE FROM invoices WHERE id = :invoice_id");
            $invoice_stmt->execute([':invoice_id' => $invoice_id]);

            if ($invoice_stmt->rowCount() === 0) {
                $this->conn->rollBack();
                return false;
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log('Invoice Deletion Failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * UPGRADED: Helper to get or create an entity (party or item) by name.
     * Now targets `name` column in `items` table.
     */
    private function getOrCreateEntity($table, $column, $name) {
        // Your new `items` table uses `name` not `canonical_name`
        $actual_column = ($table === 'items') ? 'name' : $column;

        $stmt = $this->conn->prepare("SELECT id FROM {$table} WHERE {$actual_column} = :name");
        $stmt->execute([':name' => $name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            return $result['id'];
        } else {
            $insert_sql = "INSERT INTO {$table} ({$actual_column}) VALUES (:name)";
            $insert_stmt = $this->conn->prepare($insert_sql);
            $insert_stmt->execute([':name' => $name]);
            return $this->conn->lastInsertId();
        }
    }
}
