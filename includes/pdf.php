<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Render a professional PDF report with a repeating header, footer and
 * "Page X of Y" numbering on every page.
 *
 * @param string $title body report title (shown in the header on every page)
 * @param string $body  HTML for the report body (use the helper CSS classes below)
 * @param array  $opt   orientation: portrait|landscape, subtitle: string, paper: A4
 */
function pdf_render(string $title, string $body, array $opt = []): string
{
    $orientation = $opt['orientation'] ?? 'portrait';
    $subtitle = $opt['subtitle'] ?? '';
    $user = current_user();

    $org = e(setting('organisation_name', 'CBSE South Zone Sahodaya Complex'));
    $event = e(setting('event_title', 'Science Fair'));
    $dates = e(setting('event_dates'));
    $venue = e(setting('event_venue', setting('event_host')));
    $support = e(trim(setting('support_phone') . '  |  ' . setting('support_email'), ' |'));
    $generated = date('d M Y, h:i A');
    $by = $user ? e($user['name']) : 'System';
    $logo = pdf_logo_data_uri();
    $titleE = e($title);
    $subtitleHtml = $subtitle !== '' ? '<div class="h-sub">' . e($subtitle) . '</div>' : '';

    $html = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
  @page { margin: 118px 34px 62px 34px; }
  * { box-sizing: border-box; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 8.6pt; color: #1e293b; line-height: 1.4; }
  header { position: fixed; top: -100px; left: 0; right: 0; height: 88px; }
  footer { position: fixed; bottom: -42px; left: 0; right: 0; height: 28px; border-top: 1px solid #cbd5e1; padding-top: 6px; font-size: 7pt; color: #64748b; }
  .h-table { width: 100%; border-collapse: collapse; }
  .h-table td { vertical-align: middle; }
  .h-logo { width: 54px; }
  .h-logo img { width: 46px; height: 46px; }
  .h-org { font-size: 7pt; letter-spacing: 1.5px; text-transform: uppercase; color: #0e7490; font-weight: bold; }
  .h-event { font-size: 13pt; font-weight: bold; color: #0f172a; margin-top: 1px; }
  .h-meta { font-size: 7pt; color: #64748b; }
  .h-right { text-align: right; width: 38%; }
  .h-title { font-size: 10.5pt; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: .5px; }
  .h-sub { font-size: 7.5pt; color: #475569; margin-top: 2px; }
  .h-bar { height: 4px; margin-top: 8px; background: #0891b2; border-bottom: 2px solid #0d9488; }
  .f-table { width: 100%; }
  .f-table td { font-size: 7pt; color: #64748b; }

  h2.section { font-size: 9.5pt; text-transform: uppercase; letter-spacing: .6px; color: #0e7490; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; margin: 16px 0 8px; }
  h2.section:first-child { margin-top: 0; }
  table.grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  table.grid thead { display: table-header-group; }
  table.grid th { background: #0f172a; color: #ffffff; font-size: 7.4pt; text-transform: uppercase; letter-spacing: .4px; padding: 6px 6px; text-align: left; border: 1px solid #0f172a; }
  table.grid td { padding: 5px 6px; border: 1px solid #e2e8f0; vertical-align: top; }
  table.grid tr { page-break-inside: avoid; }
  table.grid tbody tr:nth-child(even) td { background: #f8fafc; }
  table.grid tfoot td { background: #ecfeff; font-weight: bold; border-top: 2px solid #0891b2; }
  table.kv { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  table.kv td { padding: 5px 7px; border: 1px solid #e2e8f0; }
  table.kv td.k { width: 28%; background: #f8fafc; color: #475569; font-weight: bold; }
  table.kv.fee td.k { width: 60%; }
  table.cards { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px; }
  table.cards td { border: 1px solid #cbd5e1; border-top: 3px solid #0891b2; padding: 8px 10px; background: #f8fafc; width: 25%; }
  .card-label { font-size: 6.8pt; text-transform: uppercase; letter-spacing: .5px; color: #64748b; font-weight: bold; }
  .card-value { font-size: 13pt; font-weight: bold; color: #0f172a; margin-top: 2px; }
  .text-right { text-align: right; }
  .text-center { text-align: center; }
  .mono { font-family: "DejaVu Sans Mono", monospace; }
  .muted { color: #64748b; }
  .small { font-size: 7pt; }
  .bold { font-weight: bold; }
  .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 6.8pt; font-weight: bold; text-transform: uppercase; }
  .b-approved { background: #d1fae5; color: #047857; }
  .b-pending  { background: #fef3c7; color: #b45309; }
  .b-rejected { background: #ffe4e6; color: #be123c; }
  .b-submitted { background: #cffafe; color: #0e7490; }
  .b-draft { background: #e2e8f0; color: #475569; }
  .photo { width: 42px; height: 54px; border: 1px solid #cbd5e1; }
  .declaration { border: 1px solid #a7f3d0; background: #ecfdf5; padding: 8px 10px; margin-top: 12px; font-size: 8pt; }
  .sign { margin-top: 40px; width: 100%; }
  .sign td { width: 50%; font-size: 8pt; color: #475569; }
  .page-break { page-break-after: always; }
  .empty { padding: 14px; text-align: center; color: #94a3b8; border: 1px dashed #cbd5e1; }
</style></head>
<body>
<header>
  <table class="h-table"><tr>
    <td class="h-logo"><img src="{$logo}" alt=""></td>
    <td>
      <div class="h-org">{$org}</div>
      <div class="h-event">{$event}</div>
      <div class="h-meta">{$dates} &nbsp;•&nbsp; {$venue}</div>
    </td>
    <td class="h-right">
      <div class="h-title">{$titleE}</div>
      {$subtitleHtml}
    </td>
  </tr></table>
  <div class="h-bar"></div>
</header>
<footer>
  <table class="f-table"><tr>
    <td>{$org} &nbsp;•&nbsp; {$event} &nbsp;•&nbsp; {$support}<br>Generated on {$generated} by {$by}</td>
    <td style="width:90px"></td>
  </tr></table>
</footer>
<main>{$body}</main>
</body></html>
HTML;

    $options = new Options();
    $options->setChroot(APP_ROOT);
    $options->setIsRemoteEnabled(false);
    $options->setDefaultFont('DejaVu Sans');
    $options->setDpi(96);
    $tmp = STORAGE_PATH . '/cache';
    if (!is_dir($tmp)) {
        @mkdir($tmp, 0775, true);
    }
    $options->setTempDir($tmp);

    $dompdf = new Dompdf($options);
    $dompdf->setPaper($opt['paper'] ?? 'A4', $orientation);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();

    // Page X of Y on every page (bottom-right inside the footer band)
    $canvas = $dompdf->getCanvas();
    $metrics = $dompdf->getFontMetrics();
    $font = $metrics->getFont('DejaVu Sans', 'bold');
    $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';
    $width = $metrics->getTextWidth('Page 00 of 00', $font, 7.5);
    $canvas->page_text($canvas->get_width() - 34 - $width, $canvas->get_height() - 40, $text, $font, 7.5, [0.03, 0.57, 0.70]);

    return $dompdf->output();
}

/** Send a PDF to the browser. */
function pdf_stream(string $pdf, string $filename, bool $inline = true): never
{
    $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');
    echo $pdf;
    exit;
}

function pdf_badge(string $status): string
{
    $cls = match ($status) {
        'approved' => 'b-approved',
        'rejected' => 'b-rejected',
        'submitted' => 'b-submitted',
        'pending', 'reopened' => 'b-pending',
        default => 'b-draft',
    };
    return '<span class="badge ' . $cls . '">' . e(ucfirst($status)) . '</span>';
}

/** Event logo (atom mark) as a PNG data URI, drawn with GD so dompdf needs no SVG support. */
function pdf_logo_data_uri(): string
{
    static $uri = null;
    if ($uri !== null) {
        return $uri;
    }
    $custom = APP_ROOT . '/assets/img/logo.png';
    if (is_file($custom)) {
        return $uri = 'data:image/png;base64,' . base64_encode((string) file_get_contents($custom));
    }
    $s = 184;
    $img = imagecreatetruecolor($s, $s);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    // rounded gradient square
    for ($y = 0; $y < $s; $y++) {
        $t = $y / $s;
        $c = imagecolorallocate($img, (int) (8 + 0 * $t), (int) (145 - 30 * $t), (int) (178 - 60 * $t));
        imageline($img, 0, $y, $s, $y, $c);
    }
    $r = 34;
    $clear = imagecolorallocatealpha($img, 0, 0, 0, 127);
    foreach ([[0, 0], [$s - $r, 0], [0, $s - $r], [$s - $r, $s - $r]] as [$cx, $cy]) {
        for ($x = 0; $x < $r; $x++) {
            for ($y = 0; $y < $r; $y++) {
                $dx = ($cx === 0 ? $r - $x : $x) - 0.5;
                $dy = ($cy === 0 ? $r - $y : $y) - 0.5;
                if ($dx * $dx + $dy * $dy > $r * $r) {
                    imagesetpixel($img, $cx + $x, $cy + $y, $clear);
                }
            }
        }
    }
    $white = imagecolorallocate($img, 255, 255, 255);
    imagesetthickness($img, 6);
    $c = $s / 2;
    foreach ([0, 60, 120] as $deg) {
        $pts = [];
        for ($a = 0; $a <= 360; $a += 6) {
            $x = 62 * cos(deg2rad($a));
            $y = 24 * sin(deg2rad($a));
            $rad = deg2rad($deg);
            $pts[] = [$c + $x * cos($rad) - $y * sin($rad), $c + $x * sin($rad) + $y * cos($rad)];
        }
        for ($i = 1; $i < count($pts); $i++) {
            imageline($img, (int) $pts[$i - 1][0], (int) $pts[$i - 1][1], (int) $pts[$i][0], (int) $pts[$i][1], $white);
        }
    }
    imagefilledellipse($img, (int) $c, (int) $c, 22, 22, $white);
    ob_start();
    imagepng($img);
    imagedestroy($img);
    return $uri = 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
}
