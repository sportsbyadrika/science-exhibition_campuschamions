<?php
declare(strict_types=1);

/**
 * Lightweight schema migrations for existing installations.
 * Each entry upgrades the database by one version; the current version is stored
 * in settings.schema_version. Runs automatically on the first DB connection of a
 * request when the stored version is behind. Statements must be idempotent.
 */
const SCHEMA_MIGRATIONS = [
    1 => [
        "CREATE TABLE IF NOT EXISTS results (
           id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
           team_id     INT UNSIGNED NOT NULL UNIQUE,
           position    TINYINT UNSIGNED NULL COMMENT '1=First 2=Second 3=Third 4=Special Mention',
           grade       VARCHAR(10) NULL,
           remarks     VARCHAR(255) NULL,
           updated_by  INT UNSIGNED NULL,
           created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
           updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
           CONSTRAINT fk_result_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('results_published', '0')",
    ],
    2 => [
        ['add_column', 'institutions', 'logo_path', "VARCHAR(255) NULL AFTER name"],
    ],
];

/** ALTER TABLE ... ADD COLUMN only when the column does not exist yet (keeps migrations re-runnable). */
function migration_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    if (!(int) $stmt->fetchColumn()) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function schema_target_version(): int
{
    return max(array_keys(SCHEMA_MIGRATIONS));
}

function run_migrations(PDO $pdo): void
{
    try {
        $current = (int) $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'schema_version'")->fetchColumn();
    } catch (PDOException $e) {
        return; // tables not installed yet - the installer creates the latest schema
    }
    if ($current >= schema_target_version()) {
        return;
    }
    foreach (SCHEMA_MIGRATIONS as $version => $statements) {
        if ($version <= $current) {
            continue;
        }
        foreach ($statements as $sql) {
            if (is_array($sql) && $sql[0] === 'add_column') {
                migration_add_column($pdo, $sql[1], $sql[2], $sql[3]);
            } else {
                $pdo->exec($sql);
            }
        }
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
            ->execute([(string) $version]);
        error_log("[migrations] database upgraded to schema version $version");
    }
}
