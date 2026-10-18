<?php
declare(strict_types=1);

/**
 * Shared installer used by bin/install.php (CLI) and /install (web).
 */

/** Create tables and seed data from database/schema.sql (safe to re-run). */
function install_schema(): void
{
    $sql = (string) file_get_contents(APP_ROOT . '/database/schema.sql');
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
        db()->exec($statement);
    }
}

function install_tables_exist(): bool
{
    return (bool) db_value("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'");
}

function install_has_superadmin(): bool
{
    return install_tables_exist() && (bool) db_value('SELECT id FROM users WHERE role = "superadmin" LIMIT 1');
}

/** Create a user; returns an error message or null. */
function install_user(string $role, string $email, string $pass, string $name): ?string
{
    $email = mb_strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email: $email";
    }
    if ($p = password_problem($pass, $pass)) {
        return "$email: $p";
    }
    if (db_value('SELECT id FROM users WHERE email = ?', [$email])) {
        return "User $email already exists.";
    }
    db_exec('INSERT INTO users (role, name, email, password_hash) VALUES (?,?,?,?)', [$role, trim($name) ?: ucfirst($role), $email, password_hash($pass, PASSWORD_DEFAULT)]);
    return null;
}
