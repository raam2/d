<?php
// file: db.php
class DB {
    private static $pdo = null;

    public static function conn() {
        if (self::$pdo === null) {
            $dsn = "mysql:host=127.0.0.1;port=3306;dbname=gst_accounting_MICROSOFT;charset=utf8mb4";
            $user = "gstwork";
            $pass = "gstwork@123";
            try {
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                self::logError("DB Connection failed: ".$e->getMessage());
                die("Database connection failed. Check diagnostics.");
            }
        }
        return self::$pdo;
    }

    public static function logError($msg, $level="ERROR") {
        try {
            $pdo = self::conn();
            $stmt = $pdo->prepare("INSERT INTO diagnostics (level, message) VALUES (?, ?)");
            $stmt->execute([$level, $msg]);
        } catch (Exception $e) {
            error_log("Diagnostics logging failed: ".$e->getMessage());
        }
    }

    public static function getDiagnostics($limit=50) {
        $pdo = self::conn();
        $stmt = $pdo->prepare("SELECT * FROM diagnostics ORDER BY id DESC LIMIT ?");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

