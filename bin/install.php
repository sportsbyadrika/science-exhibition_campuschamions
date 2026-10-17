<?php
/**
 * One-time installer (run from the command line):
 *
 *   php bin/install.php --super-email=owner@example.org --super-pass='Secret123' \
 *                       [--admin-email=admin@example.org --admin-pass='Secret123' --admin-name='Event Admin']
 *
 * Creates the tables from database/schema.sql, seeds default masters/settings and
 * creates the hidden Super Admin (and optionally the first Administrator).
 * Safe to re-run: existing tables/rows are kept.
 */
if (PHP_SAPI !== 'cli') {
    exit("Run this script from the command line.\n");
}
require __DIR__ . '/../includes/bootstrap.php';

$opt = getopt('', ['super-email:', 'super-pass:', 'super-name::', 'admin-email::', 'admin-pass::', 'admin-name::']);

echo "Creating tables & seed data...\n";
$sql = file_get_contents(APP_ROOT . '/database/schema.sql');
$sql = preg_replace('/^--.*$/m', '', $sql);
foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
    db()->exec($statement);
}

function create_user(string $role, string $email, string $pass, string $name): void
{
    $email = mb_strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        exit("Invalid email: $email\n");
    }
    if (strlen($pass) < 8) {
        exit("Password for $email must be at least 8 characters.\n");
    }
    if (db_value('SELECT id FROM users WHERE email = ?', [$email])) {
        echo "User $email already exists - skipped.\n";
        return;
    }
    db_exec('INSERT INTO users (role, name, email, password_hash) VALUES (?,?,?,?)', [$role, $name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
    echo ucfirst($role) . " created: $email\n";
}

if (!empty($opt['super-email']) && !empty($opt['super-pass'])) {
    create_user(ROLE_SUPERADMIN, $opt['super-email'], $opt['super-pass'], $opt['super-name'] ?? 'Super Admin');
} elseif (!db_value('SELECT id FROM users WHERE role = "superadmin"')) {
    echo "WARNING: no super admin exists. Re-run with --super-email and --super-pass.\n";
}
if (!empty($opt['admin-email']) && !empty($opt['admin-pass'])) {
    create_user(ROLE_ADMIN, $opt['admin-email'], $opt['admin-pass'], $opt['admin-name'] ?? 'Event Administrator');
}
echo "Done.\n";
