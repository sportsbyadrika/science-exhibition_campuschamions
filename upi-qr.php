<?php
/**
 * Uploaded UPI QR code image (Admin > Settings), for signed-in users: /upi-qr
 */
require __DIR__ . '/includes/bootstrap.php';
require_login();

$file = upload_path(setting('upi_qr_path'));
if (!$file || !is_file($file)) {
    abort(404);
}
header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($file));
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=3600');
readfile($file);
