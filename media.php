<?php
/**
 * Serves protected uploads (student photos, payment proofs) after an ownership check.
 *   /media?t=photo&id={team_member_id}
 *   /media?t=payment&id={payment_id}
 *   /media?t=logo&id={institution_id}
 */
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$type = (string) ($_GET['t'] ?? '');
$id = input_int('id');

$row = match ($type) {
    'photo' => db_one('SELECT m.photo_path path, t.institution_id FROM team_members m JOIN teams t ON t.id = m.team_id WHERE m.id = ?', [$id]),
    'payment' => db_one('SELECT proof_path path, institution_id FROM payments WHERE id = ?', [$id]),
    'logo' => db_one('SELECT logo_path path, id institution_id FROM institutions WHERE id = ?', [$id]),
    default => null,
};
if (!$row || (!is_admin_role($user) && (int) $row['institution_id'] !== (int) $user['institution_id'])) {
    abort(404);
}
$file = upload_path($row['path']);
if (!$file || !is_file($file)) {
    abort(404, 'File not found.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=3600');
header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . basename($file) . '"');
readfile($file);
