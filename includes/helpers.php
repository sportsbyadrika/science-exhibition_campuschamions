<?php
declare(strict_types=1);

/* ---------------------------------------------------------------------
 * Output & URLs
 * ------------------------------------------------------------------- */

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Path portion of the configured app URL, e.g. "" or "/sciencefair". */
function base_path(): string
{
    static $path = null;
    if ($path === null) {
        $path = rtrim((string) parse_url((string) config('app.url', ''), PHP_URL_PATH), '/');
    }
    return $path;
}

/** Build an extension-less application URL: url('admin/events', ['id' => 3]). */
function url(string $path = '', array $query = []): string
{
    $path = ltrim($path, '/');
    $u = base_path() . '/' . $path;
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

function absolute_url(string $path = '', array $query = []): string
{
    $origin = preg_replace('#^(https?://[^/]+).*$#', '$1', (string) config('app.url'));
    return $origin . url($path, $query);
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 0;
    return base_path() . '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function redirect(string $path, array $query = []): never
{
    header('Location: ' . url($path, $query));
    exit;
}

function redirect_back(string $fallback = ''): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = parse_url((string) config('app.url'), PHP_URL_HOST);
    if ($ref && parse_url($ref, PHP_URL_HOST) === $host) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

/** Current request path relative to the app, without extension, e.g. "admin/events". */
function current_path(): string
{
    $p = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (base_path() !== '' && str_starts_with($p, base_path())) {
        $p = substr($p, strlen(base_path()));
    }
    $p = trim(preg_replace('/\.php$/', '', $p), '/');
    return $p === 'index' ? '' : $p;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, mixed $default = ''): mixed
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function input_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? $_GET[$key] ?? null;
    return is_numeric($v) ? (int) $v : $default;
}

/* ---------------------------------------------------------------------
 * Flash messages & old input
 * ------------------------------------------------------------------- */

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function remember_input(array $data): void
{
    unset($data['password'], $data['password_confirm'], $data['_csrf'], $data['captcha']);
    $_SESSION['_old'] = $data;
}

function old(string $key, string $default = ''): string
{
    return (string) ($_SESSION['_old'][$key] ?? $default);
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

/* ---------------------------------------------------------------------
 * CSRF
 * ------------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        flash('error', 'Your session expired. Please try again.');
        redirect_back();
    }
}

/* ---------------------------------------------------------------------
 * Formatting
 * ------------------------------------------------------------------- */

/** Indian number grouping: 1234567.5 => "12,34,567.50" (decimals hidden when zero). */
function inr_number(float|int|string|null $amount, bool $forceDecimals = false): string
{
    $amount = (float) $amount;
    $neg = $amount < 0;
    [$int, $dec] = explode('.', number_format(abs($amount), 2, '.', ''));
    if (strlen($int) > 3) {
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($int, 0, -3));
        $int = $rest . ',' . substr($int, -3);
    }
    $out = $int;
    if ($forceDecimals || $dec !== '00') {
        $out .= '.' . $dec;
    }
    return ($neg ? '-' : '') . $out;
}

function inr(float|int|string|null $amount, bool $forceDecimals = false): string
{
    return '₹ ' . inr_number($amount, $forceDecimals);
}

function fmt_date(?string $date, string $format = 'd M Y'): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function fmt_datetime(?string $date): string
{
    return fmt_date($date, 'd M Y, h:i A');
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/* ---------------------------------------------------------------------
 * Validation
 * ------------------------------------------------------------------- */

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function valid_phone(string $phone): bool
{
    return (bool) preg_match('/^[6-9]\d{9}$/', $phone);
}

/* ---------------------------------------------------------------------
 * Uploads (stored outside the web root under storage/uploads)
 * ------------------------------------------------------------------- */

/**
 * Save a base64 data-URL image (from the client side cropper) as JPEG.
 * Returns the relative path under storage/uploads or throws on invalid input.
 */
function save_data_url_image(string $dataUrl, string $folder, int $maxKb, string $format = 'jpg'): string
{
    if (!preg_match('#^data:image/(jpeg|png|webp);base64,(.+)$#', $dataUrl, $m)) {
        throw new RuntimeException('Invalid photo data.');
    }
    $binary = base64_decode($m[2], true);
    if ($binary === false || strlen($binary) > $maxKb * 1024) {
        throw new RuntimeException('Photo is too large or corrupted.');
    }
    $info = @getimagesizefromstring($binary);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new RuntimeException('Unsupported photo format.');
    }
    // Re-encode through GD to strip any embedded payload / metadata.
    $img = @imagecreatefromstring($binary);
    if (!$img) {
        throw new RuntimeException('Unable to read photo.');
    }
    $name = $folder . '/' . date('Y/m') . '/' . bin2hex(random_bytes(12)) . ($format === 'png' ? '.png' : '.jpg');
    $full = STORAGE_PATH . '/uploads/' . $name;
    if (!is_dir(dirname($full))) {
        mkdir(dirname($full), 0775, true);
    }
    if ($format === 'png') {
        imagesavealpha($img, true); // keep transparent logo backgrounds
        imagepng($img, $full, 6);
    } else {
        imagejpeg($img, $full, 88);
    }
    imagedestroy($img);
    return $name;
}

/**
 * Validate and move an uploaded file ($_FILES entry) into storage/uploads/{folder}.
 * Allowed: JPG, PNG, PDF.
 */
function save_uploaded_file(array $file, string $folder, int $maxKb): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please attach the file.');
    }
    if ($file['size'] > $maxKb * 1024) {
        throw new RuntimeException('File exceeds the maximum size of ' . round($maxKb / 1024, 1) . ' MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    if (!isset($map[$mime])) {
        throw new RuntimeException('Only JPG, PNG or PDF files are allowed.');
    }
    $name = $folder . '/' . date('Y/m') . '/' . bin2hex(random_bytes(12)) . '.' . $map[$mime];
    $full = STORAGE_PATH . '/uploads/' . $name;
    if (!is_dir(dirname($full))) {
        mkdir(dirname($full), 0775, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $full)) {
        throw new RuntimeException('Could not store the uploaded file.');
    }
    return $name;
}

function upload_path(?string $relative): ?string
{
    if (!$relative) {
        return null;
    }
    $full = realpath(STORAGE_PATH . '/uploads/' . $relative);
    $root = realpath(STORAGE_PATH . '/uploads');
    return ($full && $root && str_starts_with($full, $root . DIRECTORY_SEPARATOR)) ? $full : null;
}

function delete_upload(?string $relative): void
{
    $p = upload_path($relative);
    if ($p && is_file($p)) {
        @unlink($p);
    }
}

/** URL to view a protected upload through the media controller. */
function media_url(string $type, int $id, array $extra = []): string
{
    return url('media', ['t' => $type, 'id' => $id] + $extra);
}

/* ---------------------------------------------------------------------
 * Misc
 * ------------------------------------------------------------------- */

function log_activity(string $action, string $details = ''): void
{
    try {
        db_exec(
            'INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?,?,?,?)',
            [$_SESSION['user_id'] ?? null, $action, mb_substr($details, 0, 500), client_ip()]
        );
    } catch (Throwable) {
        // logging must never break the request
    }
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    $title = $code === 404 ? 'Page not found' : ($code === 403 ? 'Access denied' : 'Something went wrong');
    $message = $message ?: ($code === 404 ? 'The page you requested does not exist.' : 'You are not allowed to access this page.');
    require APP_ROOT . '/includes/layout/error.php';
    exit;
}
