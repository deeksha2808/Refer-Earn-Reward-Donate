<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/password_reset.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$validToken = false;
try {
    $validToken = password_reset_token_is_valid($token);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        reset_password_with_token($token, (string) ($_POST['password'] ?? ''), (string) ($_POST['confirm_password'] ?? ''));
        set_flash('success', 'Your password has been reset. You can now sign in.');
        redirect('auth/login.php');
    }
} catch (Throwable $exception) {
    app_log('Password reset failed: ' . $exception->getMessage());
    $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'We could not reset your password. Please request a new link.';
}
$pageTitle = 'Choose a new password | ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<main class="auth-page"><div class="container"><div class="row justify-content-center"><div class="col-md-8 col-lg-6 col-xl-5"><div class="auth-card auth-card-compact"><div class="auth-intro"><span class="brand-mark"><i class="bi bi-shield-lock"></i></span><h1>Choose a new password</h1><p>Use at least 10 characters, including uppercase, lowercase, and a number.</p></div><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><?php if (!$validToken || $error): ?><p class="auth-switch"><a href="<?= e(url('auth/forgot_password.php')) ?>">Request a new reset link</a></p><?php else: ?><form method="post" class="needs-validation" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="token" value="<?= e($token) ?>"><div class="mb-3"><label class="form-label" for="password">New password</label><input class="form-control" id="password" name="password" type="password" required minlength="10" autocomplete="new-password"></div><div class="mb-4"><label class="form-label" for="confirm_password">Confirm new password</label><input class="form-control" id="confirm_password" name="confirm_password" type="password" required minlength="10" autocomplete="new-password"></div><button class="btn btn-primary w-100 py-3">Reset password</button></form><?php endif; ?></div></div></div></div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
