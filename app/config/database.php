<?php
class Database {
    private static $host = '127.0.0.1';
    private static $db_name = 'gst_accounting';
    private static $username = 'gstwork'; // Replace with your user
    private static $password = 'gstwork@123'; // Replace with your password
    private static $conn;

    public static function getConnection() {
        self::$conn = null;
        try {
            self::$conn = new PDO('mysql:host=' . self::$host . ';dbname=' . self::$db_name, self::$username, self::$password);
            self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return self::$conn;
    }
}
?>
