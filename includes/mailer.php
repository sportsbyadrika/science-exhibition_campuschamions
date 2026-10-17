<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Send an email using PHPMailer with the SMTP settings managed in Admin > Settings.
 *
 * @param string|array $to          single address or list of addresses (or [email => name])
 * @param array        $attachments list of ['name' => 'file.pdf', 'data' => binary-string]
 */
function send_mail(string|array $to, string $subject, string $bodyHtml, array $attachments = [], ?string &$error = null): bool
{
    if (setting('mail_enabled', '0') !== '1') {
        $error = 'Email sending is disabled in settings.';
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        if (setting('smtp_host') !== '') {
            $mail->isSMTP();
            $mail->Host = setting('smtp_host');
            $mail->Port = (int) setting('smtp_port', '587');
            $secure = setting('smtp_secure', 'tls');
            if ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($secure === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            if (setting('smtp_username') !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = setting('smtp_username');
                $mail->Password = setting('smtp_password');
            }
        } else {
            $mail->isMail();
        }

        $mail->setFrom(setting('mail_from_email', 'no-reply@localhost'), setting('mail_from_name', 'Science Fair'));
        if (setting('support_email') !== '') {
            $mail->addReplyTo(setting('support_email'));
        }
        foreach ((array) $to as $k => $v) {
            is_string($k) ? $mail->addAddress($k, (string) $v) : $mail->addAddress((string) $v);
        }
        foreach ($attachments as $a) {
            $mail->addStringAttachment($a['data'], $a['name'], PHPMailer::ENCODING_BASE64, $a['type'] ?? 'application/pdf');
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = mail_layout($subject, $bodyHtml);
        $mail->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>', '</tr>'], "\n", $bodyHtml))));
        $mail->send();
        return true;
    } catch (MailException $ex) {
        $error = $mail->ErrorInfo ?: $ex->getMessage();
        error_log('[mail] ' . $error);
        return false;
    }
}

/** Branded HTML email wrapper (inline styles for email client compatibility). */
function mail_layout(string $title, string $content): string
{
    $org = e(setting('organisation_name', 'CBSE South Zone Sahodaya Complex'));
    $event = e(setting('event_title', 'Science Fair'));
    $support = e(setting('support_email'));
    $phone = e(setting('support_phone'));
    $portal = e(absolute_url());
    return <<<HTML
<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;color:#1e293b">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 0"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
<tr><td style="background:linear-gradient(90deg,#164e63,#134e4a,#0f172a);background-color:#164e63;padding:22px 28px;color:#ffffff">
<div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#67e8f9;font-weight:bold">{$org}</div>
<div style="font-size:20px;font-weight:800;margin-top:4px">{$event}</div></td></tr>
<tr><td style="padding:28px;font-size:14px;line-height:1.6">
<h2 style="margin:0 0 14px;font-size:17px;color:#0f172a">{$title}</h2>
{$content}
<p style="margin-top:24px"><a href="{$portal}" style="display:inline-block;background:#0891b2;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;font-size:13px">Open Registration Portal</a></p>
</td></tr>
<tr><td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 28px;font-size:11px;color:#64748b">
Support Desk: {$phone} &bull; {$support}<br>This is an automated message from the {$org} Science Fair portal.</td></tr>
</table></td></tr></table></body></html>
HTML;
}

/** Small helper to build a key/value table in emails. */
function mail_table(array $rows): string
{
    $html = '<table cellpadding="6" cellspacing="0" style="width:100%;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;margin:12px 0">';
    foreach ($rows as $k => $v) {
        $html .= '<tr><td style="color:#64748b;border-bottom:1px solid #f1f5f9;width:45%">' . e($k) . '</td><td style="font-weight:bold;border-bottom:1px solid #f1f5f9">' . e($v) . '</td></tr>';
    }
    return $html . '</table>';
}
