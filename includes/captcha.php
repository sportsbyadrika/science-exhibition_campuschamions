<?php
declare(strict_types=1);

/**
 * Self-hosted image captcha (no third-party service).
 * Codes are kept in the session per form key and are single-use.
 */

const CAPTCHA_CHARS = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
const CAPTCHA_LENGTH = 5;
const CAPTCHA_TTL = 900; // seconds

function captcha_key(string $key): string
{
    return preg_replace('/[^a-z0-9_-]/i', '', $key) ?: 'default';
}

function captcha_generate(string $key): string
{
    $code = '';
    for ($i = 0; $i < CAPTCHA_LENGTH; $i++) {
        $code .= CAPTCHA_CHARS[random_int(0, strlen(CAPTCHA_CHARS) - 1)];
    }
    $_SESSION['_captcha'][captcha_key($key)] = ['code' => $code, 'time' => time()];
    return $code;
}

function captcha_verify(string $key, string $input): bool
{
    $key = captcha_key($key);
    $stored = $_SESSION['_captcha'][$key] ?? null;
    unset($_SESSION['_captcha'][$key]); // single use
    if (!$stored || time() - $stored['time'] > CAPTCHA_TTL) {
        return false;
    }
    return hash_equals($stored['code'], strtoupper(trim($input)));
}

/** Emit the captcha as a PNG image (GD). Falls back to SVG if GD is unavailable. */
function captcha_output(string $key): never
{
    $code = captcha_generate($key);
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    if (!function_exists('imagecreatetruecolor')) {
        captcha_svg($code);
    }

    $w = 170; $h = 50;
    $img = imagecreatetruecolor($w, $h);
    // dark slate gradient background (matches the portal theme)
    for ($y = 0; $y < $h; $y++) {
        $c = imagecolorallocate($img, 15 + (int) ($y * 0.3), 23 + (int) ($y * 0.4), 42 + (int) ($y * 0.5));
        imageline($img, 0, $y, $w, $y, $c);
    }
    // noise: dots and arcs
    for ($i = 0; $i < 220; $i++) {
        $c = imagecolorallocatealpha($img, random_int(60, 160), random_int(120, 200), random_int(150, 220), random_int(40, 90));
        imagesetpixel($img, random_int(0, $w - 1), random_int(0, $h - 1), $c);
    }
    for ($i = 0; $i < 5; $i++) {
        $c = imagecolorallocatealpha($img, random_int(34, 120), random_int(160, 220), random_int(180, 240), random_int(50, 90));
        imagesetthickness($img, random_int(1, 2));
        imagearc($img, random_int(0, $w), random_int(0, $h), random_int(60, 200), random_int(30, 90), random_int(0, 360), random_int(0, 360), $c);
    }

    $font = APP_ROOT . '/vendor/dompdf/dompdf/lib/fonts/DejaVuSansMono-Bold.ttf';
    $x = 14;
    for ($i = 0; $i < strlen($code); $i++) {
        $c = imagecolorallocate($img, random_int(34, 120), random_int(200, 240), random_int(220, 255));
        if (is_file($font) && function_exists('imagettftext')) {
            imagettftext($img, random_int(20, 24), random_int(-22, 22), $x, random_int(33, 40), $c, $font, $code[$i]);
            $x += random_int(27, 31);
        } else {
            imagestring($img, 5, $x, random_int(12, 22), $code[$i], $c);
            $x += 28;
        }
    }
    // strike-through line (as in the template design)
    $c = imagecolorallocatealpha($img, 34, 211, 238, 40);
    imagesetthickness($img, 2);
    imageline($img, 8, random_int(20, 32), $w - 8, random_int(18, 34), $c);

    header('Content-Type: image/png');
    imagepng($img);
    imagedestroy($img);
    exit;
}

function captcha_svg(string $code): never
{
    header('Content-Type: image/svg+xml');
    $chars = '';
    foreach (str_split($code) as $i => $ch) {
        $chars .= sprintf(
            '<text x="%d" y="%d" transform="rotate(%d %d 25)" font-family="monospace" font-size="26" font-weight="700" fill="#22d3ee">%s</text>',
            16 + $i * 29, random_int(32, 38), random_int(-20, 20), 16 + $i * 29, $ch
        );
    }
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="170" height="50"><rect width="170" height="50" fill="#0f172a"/>' . $chars . '</svg>';
    exit;
}
