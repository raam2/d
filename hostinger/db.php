<?php // db.php
require __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    global $ENV, $config;
    $c = $config[$ENV];

    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['db']};charset={$c['charset']}";
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
function fetchAll(string $sql, array $params = []): array { return q($sql,$params)->fetchAll(); }
function fetchOne(string $sql, array $params = []): ?array { $r=q($sql,$params)->fetch(); return $r?:null; }
