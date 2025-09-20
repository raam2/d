<?php
class User {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Authenticates a user.
     * @param string $username The username.
     * @param string $password The password.
     * @return mixed The user ID on success, false on failure.
     */
    public function login($username, $password) {
        $query = "SELECT id, password FROM " . $this->table_name . " WHERE username = :username LIMIT 0,1";
        $stmt = $this->conn->prepare($query);

        $username = htmlspecialchars(strip_tags($username));
        $stmt->bindParam(':username', $username);

        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $hashed_password = $row['password'];

            if (password_verify($password, $hashed_password)) {
                return $row['id'];
            }
        }

        return false;
    }
}