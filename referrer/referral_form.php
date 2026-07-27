<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/business_profile.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
require_once __DIR__ . '/../includes/customer_referrals.php';
require_once __DIR__ . '/../includes/notifications.php';
$user = require_login('REFERRER');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$opportunity = active_opportunity($id);
if (!$opportunity) { set_flash('danger', 'That active opportunity was not found.'); redirect('referrer/opportunities.php'); }
$products = opportunity_products((int) $opportunity['id']); $values = referral_defaults(); $errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf(); [$values, $errors] = validate_customer_referral($_POST);
        $productId = filter_var($values['opportunity_product_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $product = null; foreach ($products as $candidate) if ((int) $candidate['id'] === $productId) $product = $candidate;
        if (!$product) $errors['opportunity_product_id'] = 'Choose a valid product or service.';
        if (!$errors) {
            $pdo = db(); $pdo->beginTransaction();
            $tempCode = 'REF-' . date('Ymd') . '-' . bin2hex(random_bytes(4));
            $stmt = $pdo->prepare('INSERT INTO customer_referrals (referral_code, opportunity_id, opportunity_product_id, business_id, referrer_id, customer_name, customer_phone, customer_email, customer_address, customer_city, customer_state, customer_notes, product_name, commission_percentage, reward_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)');
            $stmt->execute([$tempCode, $opportunity['id'], $product['id'], $opportunity['business_id'], $user['id'], $values['customer_name'], $values['customer_phone'], $values['customer_email'], $values['customer_address'], $values['customer_city'], $values['customer_state'], $values['customer_notes'] ?: null, $product['product_name'], $product['commission_percentage']]);
            $referralId = (int) $pdo->lastInsertId();
            $referralCode = generate_referral_code($referralId, date('Y-m-d H:i:s'));
            $pdo->prepare('UPDATE customer_referrals SET referral_code = ? WHERE id = ?')->execute([$referralCode, $referralId]);
            add_referral_history($referralId, 'Submitted', 'Referral submitted by referrer.');
            $pdo->commit();
            ActivityLogService::logActivity((int) $user['id'], 'REFERRER', 'Referral', 'Referral Submitted', 'Referral', $referralId, 'Submitted referral for ' . $opportunity['title'] . '.');
            try { NotificationService::referralSubmitted((int) $opportunity['business_id'], ['id'=>$referralId,'customer_name'=>$values['customer_name'],'product'=>$product['product_name'],'campaign'=>$opportunity['title'],'submitted_at'=>date('d M Y, h:i A')], $user['full_name']); }
            catch (Throwable $notificationException) { app_log('Referral submission notification failed: ' . $notificationException->getMessage()); }
            set_flash('success', 'Customer referral submitted for review.'); redirect('referrer/referrals.php');
        }
    } catch (Throwable $exception) { if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); app_log('Customer referral submission failed: ' . $exception->getMessage()); $errors['general'] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Your referral could not be submitted.'; }
}
$pageTitle = 'Submit referral | ' . APP_NAME; require_once __DIR__ . '/../includes/header.php';
?>
<main class="auth-page"><div class="container"><div class="row justify-content-center"><div class="col-lg-9"><div class="auth-card"><h1>Submit a Referral</h1><p>For <?= e($opportunity['title']) ?> · <?= e($opportunity['category']) ?></p><?php if (isset($errors['general'])): ?><div class="alert alert-danger"><?= e($errors['general']) ?></div><?php endif; ?><form method="post" class="needs-validation" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="row g-3"><div class="col-12"><label class="form-label" for="opportunity_product_id">Product / Service</label><select class="form-select <?= isset($errors['opportunity_product_id']) ? 'is-invalid' : '' ?>" id="opportunity_product_id" name="opportunity_product_id" required><option value="">Choose a product or service</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>" <?= (string) $values['opportunity_product_id'] === (string) $product['id'] ? 'selected' : '' ?>><?= e($product['product_name']) ?> — <?= e($product['commission_percentage']) ?>%</option><?php endforeach; ?></select><div class="invalid-feedback"><?= e($errors['opportunity_product_id'] ?? 'Choose a product or service.') ?></div></div><div class="col-md-6"><label class="form-label" for="customer_name">Customer Name</label><input class="form-control" id="customer_name" name="customer_name" required maxlength="150" value="<?= e($values['customer_name']) ?>"></div><div class="col-md-6"><label class="form-label" for="customer_phone">Customer Phone</label><input class="form-control" id="customer_phone" name="customer_phone" required maxlength="25" value="<?= e($values['customer_phone']) ?>"></div><div class="col-md-6"><label class="form-label" for="customer_email">Customer Email</label><input class="form-control <?= isset($errors['customer_email']) ? 'is-invalid' : '' ?>" id="customer_email" name="customer_email" type="email" required maxlength="150" value="<?= e($values['customer_email']) ?>"><div class="invalid-feedback"><?= e($errors['customer_email'] ?? 'Enter a valid customer email address.') ?></div></div><div class="col-md-6"><label class="form-label" for="customer_city">Customer City</label><input class="form-control <?= isset($errors['customer_city']) ? 'is-invalid' : '' ?>" id="customer_city" name="customer_city" required maxlength="100" value="<?= e($values['customer_city']) ?>"><div class="invalid-feedback"><?= e($errors['customer_city'] ?? 'Enter the customer city.') ?></div></div><div class="col-md-6"><label class="form-label" for="customer_state">Customer State</label><input class="form-control <?= isset($errors['customer_state']) ? 'is-invalid' : '' ?>" id="customer_state" name="customer_state" required maxlength="100" value="<?= e($values['customer_state']) ?>"><div class="invalid-feedback"><?= e($errors['customer_state'] ?? 'Enter the customer state.') ?></div></div><div class="col-12"><label class="form-label" for="customer_address">Customer Address</label><input class="form-control <?= isset($errors['customer_address']) ? 'is-invalid' : '' ?>" id="customer_address" name="customer_address" required maxlength="255" value="<?= e($values['customer_address']) ?>"><div class="invalid-feedback"><?= e($errors['customer_address'] ?? 'Enter the customer address.') ?></div></div><div class="col-12"><label class="form-label" for="customer_notes">Remarks <span class="text-muted fw-normal">(optional)</span></label><textarea class="form-control" id="customer_notes" name="customer_notes" rows="4" maxlength="5000"><?= e($values['customer_notes']) ?></textarea></div></div><button class="btn btn-primary w-100 mt-4" type="submit">Submit Referral</button></form></div></div></div></div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
