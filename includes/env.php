<?php
declare(strict_types=1);

/**
 * Minimal .env loader (KEY=VALUE per line, # comments, optional quotes).
 * Real environment variables (e.g. set by the web server) take precedence.
 */
function load_env(string $file): void
{
    if (!is_file($file) || !is_readable($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (str_starts_with($key, 'export ')) {
            $key = trim(substr($key, 7));
        }
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
            continue;
        }
        $q = $value[0] ?? '';
        if (($q === '"' || $q === "'") && str_ends_with($value, $q) && strlen($value) > 1) {
            $value = substr($value, 1, -1);
            if ($q === '"') {
                $value = str_replace(['\\n', '\\"', '\\\\'], ["\n", '"', '\\'], $value);
            }
        } else {
            $value = trim(preg_replace('/\s+#.*$/', '', $value)); // inline comment
        }
        if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
}

/** Read an environment value with a default; "true"/"false"/"null" are converted. */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return match (strtolower((string) $value)) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'null', '(null)' => null,
        default => $value,
    };
}
