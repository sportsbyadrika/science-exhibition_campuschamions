<?php
/**
 * Application bootstrap: loads configuration, starts the session and pulls in helpers.
 * Every page starts with: require __DIR__ . '/includes/bootstrap.php';
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('STORAGE_PATH', APP_ROOT . '/storage');

require_once __DIR__ . '/env.php';
load_env(APP_ROOT . '/.env');
if (!is_file(APP_ROOT . '/.env') && getenv('DB_DATABASE') === false) {
    http_response_code(500);
    exit('Configuration missing. Copy .env.example to .env and update it.');
}
$GLOBALS['config'] = require APP_ROOT . '/config/config.php';

require_once APP_ROOT . '/vendor/autoload.php';

date_default_timezone_set(config('app.timezone', 'Asia/Kolkata'));

if (config('app.env') === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/captcha.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/pdf.php';
require_once __DIR__ . '/services.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/reports.php';
require_once __DIR__ . '/admin_actions.php';

if (PHP_SAPI !== 'cli') {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || str_starts_with((string) config('app.url'), 'https://');
    session_name('SFPORTAL');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() . '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/**
 * Read a value from config/config.php using dot notation.
 */
function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}
