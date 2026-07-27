<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/notifications.php';
if (is_logged_in()) redirect(dashboard_path(current_user()['role']));

$errors = []; $values = ['full_name' => '', 'email' => '', 'phone' => '', 'role' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { verify_csrf(); } catch (RuntimeException $exception) { $errors['general'] = $exception->getMessage(); }
    if (!isset($errors['general'])) {
    foreach ($values as $key => $default) $values[$key] = trim((string) ($_POST[$key] ?? $default));
    $password = (string) ($_POST['password'] ?? ''); $confirm = (string) ($_POST['confirm_password'] ?? '');
    if (mb_strlen($values['full_name']) < 2 || mb_strlen($values['full_name']) > 100) $errors['full_name'] = 'Enter a name between 2 and 100 characters.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 150) $errors['email'] = 'Enter a valid email address.';
    if (!preg_match('/^[0-9+() .-]{7,25}$/', $values['phone'])) $errors['phone'] = 'Enter a valid phone number.';
    $values['role'] = match ($values['role']) {
        'business' => 'BUSINESS',
        'referrer' => 'REFERRER',
        default => $values['role'],
    };
    if (!in_array($values['role'], ['BUSINESS', 'REFERRER'], true)) $errors['role'] = 'Choose a valid account type.';
    if (strlen($password) < 8) $errors['password'] = 'Use at least 8 characters.';
    if ($password !== $confirm) $errors['confirm_password'] = 'Passwords do not match.';
    if (!$errors) {
        try { $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1'); $stmt->execute([$values['email']]);
            if ($stmt->fetch()) $errors['email'] = 'An account already exists with this email.';
            else { $stmt = db()->prepare('INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)'); $stmt->execute([$values['full_name'], $values['email'], $values['phone'], password_hash($password, PASSWORD_DEFAULT), $values['role']]); $userId = (int) db()->lastInsertId(); ActivityLogService::logActivity($userId, $values['role'], 'Authentication', $values['role'] . ' Registration', 'User', $userId, 'Account registered.'); try { $values['role'] === 'BUSINESS' ? NotificationService::businessWelcome($userId, $values['full_name'], $values['email']) : NotificationService::referrerWelcome($userId, $values['full_name']); } catch (Throwable $notificationException) { app_log('Registration welcome email failed: ' . $notificationException->getMessage()); } set_flash('success', 'Account created. Please sign in.'); redirect('auth/login.php'); }
        } catch (Throwable $exception) {
            app_log('Registration failed: ' . $exception->getMessage());
            $errors['general'] = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Your account could not be created. Review the highlighted fields and try again.';
        }
    }
    }
}
$pageTitle = 'Create your account | ' . APP_NAME; require_once __DIR__ . '/../includes/header.php';
?>
<main class="auth-page"><div class="container"><div class="row justify-content-center"><div class="col-lg-9 col-xl-8"><div class="auth-card"><div class="auth-intro"><span class="brand-mark"><i class="bi bi-link-45deg"></i></span><h1>Join the platform</h1><p>Choose your role and start building better connections.</p></div><?php if (isset($errors['general'])): ?><div class="alert alert-danger"><?= e($errors['general']) ?></div><?php endif; ?><form method="post" class="needs-validation" novalidate autocomplete="off"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="row g-3"><div class="col-md-6"><label for="full_name" class="form-label">Full name</label><input id="full_name" name="full_name" class="form-control no-autofill <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" required maxlength="100" autocomplete="off" readonly><div class="invalid-feedback"><?= e($errors['full_name'] ?? 'Please enter your full name.') ?></div></div><div class="col-md-6"><label for="phone" class="form-label">Phone</label><input id="phone" name="phone" type="tel" class="form-control no-autofill <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" required maxlength="25" autocomplete="off" readonly><div class="invalid-feedback"><?= e($errors['phone'] ?? 'Please enter a valid phone number.') ?></div></div><div class="col-12"><label for="email" class="form-label">Email address</label><input id="email" name="email" type="email" class="form-control no-autofill <?= isset($errors['email']) ? 'is-invalid' : '' ?>" required maxlength="150" autocomplete="off" readonly><div class="invalid-feedback"><?= e($errors['email'] ?? 'Please enter a valid email address.') ?></div></div><div class="col-md-6"><label for="password" class="form-label">Password</label><input id="password" name="password" type="password" class="form-control no-autofill <?= isset($errors['password']) ? 'is-invalid' : '' ?>" required minlength="8" autocomplete="new-password" readonly><div class="invalid-feedback"><?= e($errors['password'] ?? 'Use at least 8 characters.') ?></div></div><div class="col-md-6"><label for="confirm_password" class="form-label">Confirm password</label><input id="confirm_password" name="confirm_password" type="password" class="form-control no-autofill <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" required autocomplete="new-password" readonly><div class="invalid-feedback"><?= e($errors['confirm_password'] ?? 'Passwords must match.') ?></div></div><div class="col-12"><label class="form-label required-label d-block">I am joining as</label><div class="role-select"><input type="radio" name="role" value="business" id="business" required><label for="business"><i class="bi bi-buildings"></i><span>Business<small>Create referral opportunities</small></span></label><input type="radio" name="role" value="referrer" id="referrer"><label for="referrer"><i class="bi bi-person-heart"></i><span>Referrer<small>Make genuine introductions</small></span></label></div></div></div><button class="btn btn-primary w-100 mt-4 py-3" type="submit">Create account <i class="bi bi-arrow-right ms-1"></i></button></form><p class="auth-switch">Already have an account? <a href="<?= e(url('auth/login.php')) ?>">Sign in</a></p></div></div></div></div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
