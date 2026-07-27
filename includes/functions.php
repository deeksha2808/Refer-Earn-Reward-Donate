<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function absolute_url(string $path = ''): string
{
    $configuredUrl = trim((string) getenv('APP_URL'));
    $parts = $configuredUrl === '' ? false : parse_url($configuredUrl);
    if (
        $parts === false
        || !isset($parts['scheme'], $parts['host'])
        || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
    ) {
        throw new RuntimeException('APP_URL must be configured as an absolute http:// or https:// URL before email links can be sent.');
    }

    return rtrim($configuredUrl, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $expected = (string) ($_SESSION['csrf_token'] ?? '');
    $provided = (string) ($_POST['csrf_token'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        throw new RuntimeException('Your form session expired. Please try again.');
    }
}

function pull_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']['id']);
}

function canonical_role(string $role): string
{
    return match (strtolower($role)) {
        'business' => 'BUSINESS',
        'referrer' => 'REFERRER',
        default => $role,
    };
}

function dashboard_path(string $role): string
{
    $role = canonical_role($role);
    return match ($role) {
        'BUSINESS' => 'business/dashboard.php',
        'REFERRER' => 'referrer/dashboard.php',
        default => 'index.php',
    };
}

function require_login(?string $requiredRole = null, bool $denyOnRoleMismatch = false): array
{
    $user = current_user();
    if ($user === null) {
        set_flash('warning', 'Please sign in to access that page.');
        redirect('auth/login.php');
    }
    $user['role'] = canonical_role((string) $user['role']);
    $_SESSION['user']['role'] = $user['role'];
    if ($requiredRole !== null && $user['role'] !== canonical_role($requiredRole)) {
        set_flash('danger', 'You do not have permission to access that page.');
        if ($denyOnRoleMismatch) {
            redirect('access_denied.php');
        }
        redirect(dashboard_path($user['role']));
    }
    return $user;
}

function role_label(string $role): string
{
    $role = canonical_role($role);
    return match ($role) {
        'BUSINESS' => 'Business',
        'REFERRER' => 'Referrer',
        default => ucfirst($role),
    };
}
