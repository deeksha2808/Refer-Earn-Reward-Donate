<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/password_reset.php';

$email = '';
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email address.');
        password_reset_request($email);
        $message = 'If an account matches that email address, a password-reset link has been sent.';
    } catch (Throwable $exception) {
        app_log('Password reset request failed: ' . $exception->getMessage());
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'We could not send a password-reset email. Please try again.';
    }
}
$pageTitle = 'Forgot password | ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<main class="auth-page"><div class="container"><div class="row justify-content-center"><div class="col-md-8 col-lg-6 col-xl-5"><div class="auth-card auth-card-compact"><div class="auth-intro"><span class="brand-mark"><i class="bi bi-key"></i></span><h1>Reset your password</h1><p>Enter your account email and we will send a secure reset link.</p></div><?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post" class="needs-validation" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="mb-4"><label class="form-label" for="email">Email address</label><input class="form-control" id="email" name="email" type="email" required maxlength="150" autocomplete="email" value="<?= e($email) ?>"></div><button class="btn btn-primary w-100 py-3">Send reset link</button></form><p class="auth-switch"><a href="<?= e(url('auth/login.php')) ?>">Back to sign in</a></p></div></div></div></div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
