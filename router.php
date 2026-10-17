<?php
/**
 * Development router for PHP's built-in server, mirroring the .htaccess rules:
 *   php -S localhost:8000 router.php
 */
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = __DIR__;

if (preg_match('#^/(includes|config|database|storage|vendor|bin|node_modules)(/|$)#', $path) || preg_match('#/\.#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
if (preg_match('#^(.*?)(?:/index)?\.php$#', $path, $m) && $path !== '/router.php') {
    header('Location: ' . ($m[1] === '' ? '/' : $m[1]) . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
    exit;
}
if ($path !== '/' && is_file($root . $path)) {
    return false; // static asset
}
$target = rtrim($path, '/');
$file = $target === '' ? $root . '/index.php' : (is_file($root . $target . '.php') ? $root . $target . '.php' : (is_file($root . $target . '/index.php') ? $root . $target . '/index.php' : null));
if ($file === null) {
    http_response_code(404);
    $file = $root . '/404.php';
}
$_SERVER['SCRIPT_NAME'] = substr($file, strlen($root));
chdir(dirname($file));
require $file;
