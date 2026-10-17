<?php
/**
 * Institution PDF reports: /institution/report?type=application|teams|food|payments
 */
require __DIR__ . '/../includes/bootstrap.php';
[$user, $inst] = require_institution();

$id = (int) $inst['id'];
$r = match ((string) input('type')) {
    'application' => report_institution_application($id),
    'teams' => report_institution_teams($id),
    'food' => report_institution_food($id),
    'payments' => report_institution_payments($id),
    default => abort(404),
};
log_activity('report_generated', $r['title']);
pdf_stream(report_pdf($r), $r['filename'], !isset($_GET['download']));
