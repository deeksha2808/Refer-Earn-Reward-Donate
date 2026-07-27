<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';

$auditUser = current_user();
if ($auditUser) ActivityLogService::logActivity((int) $auditUser['id'], canonical_role((string) $auditUser['role']), 'Authentication', canonical_role((string) $auditUser['role']) . ' Logout', 'User', (int) $auditUser['id'], 'User signed out.');

if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    session_unset();
    session_destroy();
}

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
}

session_start();
set_flash('success', 'You have been signed out.');
redirect('auth/login.php');
