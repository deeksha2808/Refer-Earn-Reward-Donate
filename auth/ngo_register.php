<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/ngos.php';

$user = require_login();
$values = ['name' => '', 'email' => '', 'phone' => '', 'address' => '', 'website' => ''];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $values = array_map('trim', array_merge($values, $_POST));
        if ($values['name'] === '' || mb_strlen($values['name']) > 191) $errors['name'] = 'Enter a name for the NGO.';
        if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
        if (!$errors) {
            $id = register_ngo($values);
            set_flash('success', 'NGO registered successfully.');
            redirect('auth/ngo_register.php');
        }
    } catch (Throwable $e) { app_log('NGO registration failed: ' . $e->getMessage()); $errors['general'] = 'Could not register NGO.'; }
}
$pageTitle = 'Register NGO | ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<main class="auth-page"><div class="container"><div class="row justify-content-center"><div class="col-md-8"><div class="auth-card"><div class="d-flex justify-content-between align-items-start gap-3 mb-4"><div><span class="eyebrow"><i class="bi bi-building"></i> NGO registration</span><h1 class="mt-3 mb-1">Register an NGO</h1><p class="mb-0">Add a new NGO to receive donations.</p></div></div><?php if (isset($errors['general'])): ?><div class="alert alert-danger"><?= e($errors['general']) ?></div><?php endif; ?><form method="post" class="needs-validation" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="row g-3"><div class="col-12"><label class="form-label" for="name">NGO name</label><input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e($values['name']) ?>" required maxlength="191"><div class="invalid-feedback"><?= e($errors['name'] ?? 'Enter NGO name.') ?></div></div><div class="col-md-6"><label class="form-label" for="email">Email <span class="text-muted fw-normal">(optional)</span></label><input class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" type="email" value="<?= e($values['email']) ?>"><div class="invalid-feedback"><?= e($errors['email'] ?? '') ?></div></div><div class="col-md-6"><label class="form-label" for="phone">Phone <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" id="phone" name="phone" value="<?= e($values['phone']) ?>" maxlength="50"></div><div class="col-12"><label class="form-label" for="address">Address <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" id="address" name="address" value="<?= e($values['address']) ?>" maxlength="255"></div><div class="col-12"><label class="form-label" for="website">Website <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" id="website" name="website" value="<?= e($values['website']) ?>" maxlength="255"></div><div class="col-12"><button class="btn btn-primary" type="submit">Register NGO</button></div></div></form></div></div></div></div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
