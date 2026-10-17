<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'] ?? 3306, $c['name'], $c['charset'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}

function db_exec(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_one(string $sql, array $params = []): ?array
{
    $row = db_exec($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    return db_exec($sql, $params)->fetchAll();
}

function db_value(string $sql, array $params = []): mixed
{
    $v = db_exec($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function db_insert_id(): int
{
    return (int) db()->lastInsertId();
}

/* ---------------------------------------------------------------------
 * Settings (key/value table, cached per request)
 * ------------------------------------------------------------------- */

function settings(): array
{
    static $cache = null;
    if ($cache === null || isset($GLOBALS['_settings_dirty'])) {
        unset($GLOBALS['_settings_dirty']);
        $cache = [];
        foreach (db_all('SELECT setting_key, setting_value FROM settings') as $r) {
            $cache[$r['setting_key']] = (string) $r['setting_value'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $s = settings();
    return ($s[$key] ?? '') !== '' ? $s[$key] : $default;
}

function save_setting(string $key, string $value): void
{
    db_exec(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [$key, $value]
    );
    $GLOBALS['_settings_dirty'] = true;
}
