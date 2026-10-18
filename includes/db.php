<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'] ?? 3306, $c['name'], $c['charset'] ?? 'utf8mb4');
        try {
            $pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('[db] ' . $e->getMessage());
            $hint = match ((int) $e->getCode()) {
                1045 => 'the username or password was rejected (check DB_USERNAME / DB_PASSWORD, and that the user is added to the database with ALL PRIVILEGES)',
                1049 => 'the database "' . $c['name'] . '" does not exist (check DB_DATABASE - cPanel names start with the account prefix, e.g. sportsby_...)',
                1044 => 'the user has no access to this database (cPanel > MySQL Databases > Add User To Database)',
                2002, 2003, 2005 => 'the database server could not be reached (check DB_HOST - usually "localhost" on cPanel)',
                default => 'error code ' . $e->getCode(),
            };
            throw new AppSetupException('Cannot connect to the database: ' . $hint . '. Update the DB_* values in .env.');
        }
        $pdo->exec("SET time_zone = '" . date('P') . "'");
        run_migrations($pdo);
    }
    return $pdo;
}

function db_exec(string $sql, array $params = []): PDOStatement
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02' && !str_starts_with(current_path(), 'install')) {
            error_log('[db] ' . $e->getMessage());
            throw new AppSetupException("The database tables are not installed yet.\nOpen " . absolute_url('install')
                . ' in the browser (set INSTALL_KEY in .env first) or run bin/install.php from the cPanel Terminal.');
        }
        throw $e;
    }
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

/** Settings that can be pinned from .env (env wins when set). */
const ENV_SETTING_OVERRIDES = [
    'mail_enabled'    => 'MAIL_ENABLED',
    'smtp_host'       => 'MAIL_HOST',
    'smtp_port'       => 'MAIL_PORT',
    'smtp_secure'     => 'MAIL_ENCRYPTION',
    'smtp_username'   => 'MAIL_USERNAME',
    'smtp_password'   => 'MAIL_PASSWORD',
    'mail_from_email' => 'MAIL_FROM_ADDRESS',
    'mail_from_name'  => 'MAIL_FROM_NAME',
];

/** True when the SMTP settings are controlled by .env (MAIL_HOST set). */
function mail_from_env(): bool
{
    return env('MAIL_HOST') !== null;
}

/** True when a setting's value is pinned by .env and cannot be edited in Admin > Settings. */
function setting_locked(string $key): bool
{
    return isset(ENV_SETTING_OVERRIDES[$key])
        && ($key === 'mail_enabled' || mail_from_env())
        && env(ENV_SETTING_OVERRIDES[$key]) !== null;
}

function setting(string $key, string $default = ''): string
{
    if (setting_locked($key)) {
        $v = env(ENV_SETTING_OVERRIDES[$key]);
        return is_bool($v) ? ($v ? '1' : '0') : (string) $v;
    }
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
