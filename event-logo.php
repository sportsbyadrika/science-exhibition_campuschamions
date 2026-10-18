<?php
/**
 * Public event logo (uploaded in Admin > Settings): /event-logo
 */
require __DIR__ . '/includes/bootstrap.php';

$file = upload_path(setting('event_logo_path'));
if (!$file || !is_file($file)) {
    abort(404);
}
header('Content-Type: image/png');
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=604800');
readfile($file);
