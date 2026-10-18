<?php
/**
 * Application bootstrap: loads configuration, starts the session and pulls in helpers.
 * Every page starts with: require __DIR__ . '/includes/bootstrap.php';
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('STORAGE_PATH', APP_ROOT . '/storage');

/**
 * Raised for server set-up problems (missing .env, database, tables...).
 * Its message is safe to show to visitors and explains how to fix the problem.
 */
class AppSetupException extends RuntimeException
{
}

/** Render a plain set-up error page (kept PHP 7 compatible so it works before the version check). */
function setup_fail($message)
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Setup error: ' . $message . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Setup required</title></head><body style="font-family:system-ui,sans-serif;background:#f8fafc;color:#0f172a;padding:40px">'
        . '<div style="max-width:640px;margin:auto;background:#fff;border:1px solid #e2e8f0;border-top:4px solid #0891b2;border-radius:12px;padding:28px">'
        . '<h1 style="font-size:18px;margin:0 0 12px">Science Fair Portal &ndash; setup required</h1>'
        . '<p style="font-size:14px;line-height:1.6">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>'
        . '<p style="font-size:12px;color:#64748b">Details are written to <code>storage/logs/php-error.log</code>.</p></div></body></html>';
    exit;
}

// Log every error to storage/logs (also catches fatals that would otherwise be a blank HTTP 500)
ini_set('log_errors', '1');
if (is_dir(STORAGE_PATH . '/logs') && is_writable(STORAGE_PATH . '/logs')) {
    ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
}

if (PHP_VERSION_ID < 80100) {
    setup_fail('This portal needs PHP 8.1 or newer, but the website is running PHP ' . PHP_VERSION . ".\n"
        . 'In cPanel open "MultiPHP Manager", select this domain and choose PHP 8.1, 8.2 or 8.3.');
}
if (!is_file(APP_ROOT . '/.env') && getenv('DB_DATABASE') === false) {
    setup_fail("The .env configuration file is missing.\nCopy .env.example to .env in " . APP_ROOT . ' and fill in the database details.');
}
if (!is_file(APP_ROOT . '/vendor/autoload.php')) {
    setup_fail("PHP libraries (PHPMailer, Dompdf) are not installed - the vendor folder is missing.\n"
        . 'In cPanel Terminal run:  cd ' . APP_ROOT . ' && composer install --no-dev');
}

require_once __DIR__ . '/env.php';
load_env(APP_ROOT . '/.env');
$GLOBALS['config'] = require APP_ROOT . '/config/config.php';

require_once APP_ROOT . '/vendor/autoload.php';

date_default_timezone_set(config('app.timezone', 'Asia/Kolkata'));

if (config('app.env') === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

foreach (['/logs', '/cache', '/uploads/photos', '/uploads/payments', '/uploads/logos', '/uploads/qr'] as $dir) {
    if (!is_dir(STORAGE_PATH . $dir)) {
        @mkdir(STORAGE_PATH . $dir, 0755, true);
    }
}
if (!is_writable(STORAGE_PATH . '/logs') || !is_writable(STORAGE_PATH . '/uploads')) {
    setup_fail('The storage folder is not writable by PHP. Set permissions 755 on ' . STORAGE_PATH . ' and its sub-folders.');
}
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

set_exception_handler(function (Throwable $e) {
    error_log('[' . date('Y-m-d H:i:s') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    if ($e instanceof AppSetupException) {
        setup_fail($e->getMessage());
    }
    if (config('app.env') === 'development') {
        setup_fail(get_class($e) . ': ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
    }
    setup_fail('Something went wrong while loading this page. Please try again in a moment.');
});

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/migrations.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/captcha.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/pdf.php';
require_once __DIR__ . '/services.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/reports.php';
require_once __DIR__ . '/admin_actions.php';
require_once __DIR__ . '/installer.php';

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
