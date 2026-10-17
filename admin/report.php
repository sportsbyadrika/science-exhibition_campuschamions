<?php
/**
 * Admin PDF reports: /admin/report?type=institutions|funds|events|food|teams|application&id=
 */
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$status = (string) input('status');
$r = match ((string) input('type')) {
    'institutions' => report_institutions(in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : ''),
    'funds' => report_funds(in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : ''),
    'events' => report_event_participation(),
    'food' => report_food_summary(),
    'teams' => report_all_teams(input_int('event_id'), input_int('category_id'), in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : ''),
    'application' => db_value('SELECT id FROM institutions WHERE id = ?', [input_int('id')]) ? report_institution_application(input_int('id')) : abort(404),
    default => abort(404),
};
log_activity('report_generated', $r['title']);
pdf_stream(report_pdf($r), $r['filename'], !isset($_GET['download']));
