<?php
declare(strict_types=1);

const ROLE_SUPERADMIN  = 'superadmin';
const ROLE_ADMIN       = 'admin';
const ROLE_INSTITUTION = 'institution';

/** The signed-in user (re-read from the DB on every request so deactivation is immediate). */
function current_user(): ?array
{
    static $cache = [];
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id === 0) {
        return null;
    }
    if (!array_key_exists($id, $cache)) {
        $cache[$id] = db_one('SELECT * FROM users WHERE id = ? AND status = "active"', [$id]);
        if (!$cache[$id]) {
            unset($_SESSION['user_id']);
        }
    }
    return $cache[$id];
}

function is_admin_role(?array $user = null): bool
{
    $user ??= current_user();
    return $user && in_array($user['role'], [ROLE_SUPERADMIN, ROLE_ADMIN], true);
}

function is_superadmin(?array $user = null): bool
{
    $user ??= current_user();
    return $user && $user['role'] === ROLE_SUPERADMIN;
}

function home_for(array $user): string
{
    return $user['role'] === ROLE_INSTITUTION ? 'institution/dashboard' : 'admin/dashboard';
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $_SESSION['intended'] = current_path();
        flash('warning', 'Please sign in to continue.');
        redirect('');
    }
    return $user;
}

/** Admin area guard. Super admin passes every admin check. */
function require_admin(): array
{
    $user = require_login();
    if (!is_admin_role($user)) {
        abort(403);
    }
    return $user;
}

function require_superadmin(): array
{
    $user = require_login();
    if (!is_superadmin($user)) {
        abort(403);
    }
    return $user;
}

/** Institution area guard. Returns [user, institution]. */
function require_institution(): array
{
    $user = require_login();
    if ($user['role'] !== ROLE_INSTITUTION || !$user['institution_id']) {
        abort(403);
    }
    $inst = db_one('SELECT * FROM institutions WHERE id = ?', [$user['institution_id']]);
    if (!$inst) {
        abort(403);
    }
    // A rejected (or no longer approved) institution loses access immediately, even mid-session
    if ($inst['status'] === 'rejected' || ($inst['status'] === 'pending' && !auto_approve_institutions())) {
        logout();
        flash('error', $inst['status'] === 'rejected'
            ? 'Your participation request has been rejected by the secretariat.' . ($inst['status_remarks'] ? ' Reason: ' . $inst['status_remarks'] : '')
            : 'Your participation request is pending approval by the Sahodaya secretariat.');
        redirect('');
    }
    return [$user, $inst];
}

/**
 * Attempt login. Returns error string or null on success.
 */
function attempt_login(string $email, string $password): ?string
{
    $user = db_one('SELECT * FROM users WHERE email = ?', [mb_strtolower($email)]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        log_activity('login_failed', $email);
        return 'Invalid login ID or password.';
    }
    if ($user['status'] !== 'active') {
        return 'Your account has been deactivated. Please contact the event secretariat.';
    }
    if ($user['role'] === ROLE_INSTITUTION) {
        $inst = db_one('SELECT status, status_remarks FROM institutions WHERE id = ?', [$user['institution_id']]);
        if (!$inst || $inst['status'] === 'rejected') {
            return 'Your participation request was not approved.' . ($inst['status_remarks'] ?? '' ? ' Reason: ' . $inst['status_remarks'] : '');
        }
        if ($inst['status'] === 'pending' && !auto_approve_institutions()) {
            return 'Your participation request is pending approval by the Sahodaya secretariat. You will receive an email once it is approved.';
        }
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    db_exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
    log_activity('login', $user['email']);
    return null;
}

function logout(): void
{
    log_activity('logout');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    // Start a fresh, empty session (new id + cookie) so a flash message can follow the sign-out
    session_id(session_create_id());
    session_start();
}

function password_problem(string $password, string $confirm): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must contain both letters and numbers.';
    }
    if ($password !== $confirm) {
        return 'Passwords do not match.';
    }
    return null;
}
