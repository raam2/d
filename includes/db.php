<?php
class Database {
    private $host = "127.0.0.1";
    private $db_name = "gst_accounting";
    private $username = "gstwork";
    private $password = "gstwork@123";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::MYSQL_ATTR_LOCAL_INFILE => true,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $exception) {
            die("Connection error: " . htmlspecialchars($exception->getMessage()));
        }
        return $this->conn;
    }
}
?>

