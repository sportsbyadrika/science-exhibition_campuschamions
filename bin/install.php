<?php
/**
 * One-time installer (run from the command line):
 *
 *   php bin/install.php --super-email=owner@example.org --super-pass='Secret123' \
 *                       [--admin-email=admin@example.org --admin-pass='Secret123' --admin-name='Event Admin']
 *
 * Creates the tables from database/schema.sql, seeds default masters/settings and
 * creates the hidden Super Admin (and optionally the first Administrator).
 * Safe to re-run: existing tables/rows are kept. (Browser alternative: /install)
 */
if (PHP_SAPI !== 'cli') {
    exit("Run this script from the command line.\n");
}
require __DIR__ . '/../includes/bootstrap.php';

$opt = getopt('', ['super-email:', 'super-pass:', 'super-name::', 'admin-email::', 'admin-pass::', 'admin-name::']);

echo "Creating tables & seed data...\n";
install_schema();

foreach ([[ROLE_SUPERADMIN, 'super', 'Super Admin'], [ROLE_ADMIN, 'admin', 'Event Administrator']] as [$role, $prefix, $defaultName]) {
    if (empty($opt["$prefix-email"]) || empty($opt["$prefix-pass"])) {
        continue;
    }
    $err = install_user($role, $opt["$prefix-email"], $opt["$prefix-pass"], $opt["$prefix-name"] ?? $defaultName);
    echo $err ? "Skipped: $err\n" : ucfirst($role) . ' created: ' . $opt["$prefix-email"] . "\n";
}
if (!install_has_superadmin()) {
    echo "WARNING: no super admin exists. Re-run with --super-email and --super-pass.\n";
}
echo "Done.\n";
