<?php
/**
 * Centralized bootstrap file for AIC Kalyet Church Management System.
 * Include this at the top of every page instead of calling session_start()
 * and requiring database.php individually.
 */

// Start session with secure settings if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Regenerate session ID periodically to prevent fixation
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

require_once __DIR__ . '/database.php';

/**
 * Escape a value for safe HTML output.
 */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Get or create the CSRF token for the current session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a submitted CSRF token.
 */
function csrf_verify(?string $token): bool
{
    if (!is_string($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Render a hidden CSRF input field.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . e(csrf_token()) . '">';
}

/**
 * Require the user to be logged in. Redirects to login if not.
 */
function require_login(): void
{
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        header('Location: ' . app_url('login.php'));
        exit;
    }
}

/**
 * Require the user to have one of the given roles.
 */
function require_role(array $allowedRoles): void
{
    require_login();

    $role = strtolower(trim($_SESSION['role'] ?? ''));

    if (!in_array($role, array_map('strtolower', $allowedRoles), true)) {
        http_response_code(403);
        exit('Access denied. You do not have permission to view this page.');
    }
}

/**
 * Redirect back with a flash message.
 */
function flash_set(string $key, string $message, string $type = 'success'): void
{
    $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
}

/**
 * Get and clear a flash message.
 */
function flash_get(string $key): ?array
{
    if (isset($_SESSION['flash'][$key])) {
        $data = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $data;
    }
    return null;
}

/**
 * Build an absolute-ish URL relative to the app root.
 */
function app_url(string $path = ''): string
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    // If the script is inside a subdirectory like admin/, go up one level.
    if (preg_match('#/(admin|assets_officer|chairperson|christian_education|pastor|projects_officer|secretary|treasurer)(/|$)#', $_SERVER['SCRIPT_NAME'] ?? '')) {
        $base = dirname($base);
    }
    return $base . '/' . ltrim($path, '/');
}

/**
 * Log an error message to a file (and optionally error_log).
 */
function app_log(string $context, Throwable $e): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $line = sprintf(
        "[%s] %s: %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $context,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    @file_put_contents($logDir . '/app.log', $line, FILE_APPEND);
    error_log($line);
}

/**
 * Safe number formatting.
 */
function format_money($amount, string $prefix = 'KSh '): string
{
    return $prefix . number_format((float)$amount, 2);
}

/**
 * Format a date string safely.
 */
function format_date(?string $date, string $format = 'd M Y', string $fallback = '—'): string
{
    if (empty($date) || $date === '0000-00-00') {
        return $fallback;
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : $fallback;
}