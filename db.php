<?php // db.php
require __DIR__ . '/config.php';

/**
 * Shared PDO connection accessor.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $ENV, $config;
    $cfg = $config[$ENV];

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['port'],
        $cfg['db'],
        $cfg['charset']
    );

    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function fetchOne(string $sql, array $params = []): ?array
{
    $stmt = q($sql, $params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function fetchAll(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}
