<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/business_profile.php';
require_once __DIR__ . '/../includes/referrer_profile.php';
if (is_logged_in()) redirect(dashboard_path(current_user()['role']));
$error = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { verify_csrf(); } catch (RuntimeException $exception) { $error = $exception->getMessage(); }
    if ($error === '') {
    $email = trim((string) ($_POST['email'] ?? '')); $password = (string) ($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') $error = 'Enter your email address and password.';
    else {
        try { $stmt = db()->prepare('SELECT id, full_name, email, phone, password, role FROM users WHERE email = ? LIMIT 1'); $stmt->execute([$email]); $account = $stmt->fetch();
            if (!$account || !password_verify($password, $account['password'])) { $error = 'Invalid email address or password.'; }
            else { session_regenerate_id(true); $account['role'] = canonical_role((string) $account['role']); unset($account['password']); $_SESSION['user'] = $account; ActivityLogService::logActivity((int) $account['id'], $account['role'], 'Authentication', $account['role'] . ' Login', 'User', (int) $account['id'], 'User signed in.'); set_flash('success', 'Welcome back, ' . $account['full_name'] . '.'); if ($account['role'] === 'BUSINESS' && !business_profile_is_complete((int) $account['id'])) redirect('business/profile.php'); if ($account['role'] === 'REFERRER' && !referrer_profile_is_complete((int) $account['id'])) redirect('referrer/profile.php'); redirect(dashboard_path($account['role'])); }
        } catch (Throwable $exception) {
            app_log('Login failed: ' . $exception->getMessage());
            $error = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Your sign-in request could not be completed. Please try again.';
        }
    }
    }
}
$pageTitle = 'Sign in | ' . APP_NAME; require_once __DIR__ . '/../includes/header.php';
?>
<main class="auth-page"><div class="container"><div class="row justify-content-center"><div class="col-md-8 col-lg-6 col-xl-5"><div class="auth-card auth-card-compact"><div class="auth-intro"><span class="brand-mark"><i class="bi bi-link-45deg"></i></span><h1>Welcome back</h1><p>Sign in to continue to your dashboard.</p></div><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post" class="needs-validation" novalidate autocomplete="off"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="mb-3"><label for="email" class="form-label">Email address</label><input id="email" name="email" type="email" class="form-control no-autofill" required autocomplete="email"><div class="invalid-feedback">Please enter a valid email address.</div></div><div class="mb-2"><label for="password" class="form-label">Password</label><input id="password" name="password" type="password" class="form-control no-autofill" required autocomplete="current-password"><div class="invalid-feedback">Please enter your password.</div></div><div class="d-flex justify-content-end align-items-center mb-4"><a class="small-link" href="<?= e(url('auth/forgot_password.php')) ?>">Forgot password?</a></div><button class="btn btn-primary w-100 py-3" type="submit">Sign in <i class="bi bi-arrow-right ms-1"></i></button></form><p class="auth-switch">New to Refer? <a href="<?= e(url('auth/register.php')) ?>">Create an account</a></p></div></div></div></div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
