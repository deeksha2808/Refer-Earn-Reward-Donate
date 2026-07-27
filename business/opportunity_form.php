<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/business_profile.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
require_once __DIR__ . '/../includes/notifications.php';

$user = require_login('BUSINESS');
if (!business_profile_is_complete((int) $user['id'])) { set_flash('warning', 'Complete your business profile before creating an opportunity.'); redirect('business/profile.php'); }
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$existing = $id ? business_opportunity($id, (int) $user['id']) : null;
if ($id && !$existing) { set_flash('danger', 'That referral opportunity was not found.'); redirect('business/opportunities.php'); }
$values = array_merge(opportunity_defaults(), $existing ?: []);
$values['valid_until'] = format_display_date($values['valid_until'] ?? '');
$values['products'] = $existing ? opportunity_products((int) $existing['id']) : [['product_name' => '', 'commission_percentage' => '']];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf(); [$values, $errors] = validate_opportunity($_POST);
        if ($errors) throw new RuntimeException('Please correct the highlighted fields.');
        $pdo = db(); $pdo->beginTransaction();
        if ($existing) {
            $stmt = $pdo->prepare('UPDATE referral_opportunities SET title=?, category=?, description=?, service_location=?, valid_until=?, status=? WHERE id=? AND business_id=?');
            $stmt->execute([$values['title'], $values['category'], $values['description'], $values['service_location'], $values['valid_until'], $values['status'], $existing['id'], $user['id']]);
            $opportunityId = (int) $existing['id']; $pdo->prepare('DELETE FROM opportunity_products WHERE opportunity_id=?')->execute([$opportunityId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO referral_opportunities (business_id,title,category,description,service_location,valid_until,status) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$user['id'], $values['title'], $values['category'], $values['description'], $values['service_location'], $values['valid_until'], $values['status']]);
            $opportunityId = (int) $pdo->lastInsertId();
        }
        $productStmt = $pdo->prepare('INSERT INTO opportunity_products (opportunity_id,product_name,commission_percentage) VALUES (?,?,?)');
        foreach ($values['products'] as $product) $productStmt->execute([$opportunityId, $product['name'], $product['rate']]);
        $pdo->commit();
        ActivityLogService::logActivity((int) $user['id'], 'BUSINESS', 'Business', $existing ? 'Opportunity Updated' : 'Opportunity Created', 'Opportunity', $opportunityId, ($existing ? 'Updated' : 'Created') . ' opportunity: ' . $values['title'] . '.');
        if (!$existing) {
            try { $profile = business_profile((int) $user['id']); NotificationService::opportunityCreated(['id'=>$opportunityId,'title'=>$values['title'],'category'=>$values['category'],'valid_until'=>$values['valid_until']], $values['products'], $profile['business_name'] ?? $user['full_name']); }
            catch (Throwable $notificationException) { app_log('Opportunity notification failed: ' . $notificationException->getMessage()); }
        }
        set_flash('success', $existing ? 'Opportunity updated successfully.' : 'Opportunity created successfully.'); redirect('business/opportunities.php');
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        app_log('Opportunity save failed: ' . $exception->getMessage()); $errors['general'] = $exception instanceof RuntimeException ? $exception->getMessage() : 'We could not save the opportunity.';
    }
}
$pageTitle = ($existing ? 'Edit' : 'Create') . ' opportunity | ' . APP_NAME; require_once __DIR__ . '/../includes/header.php';
?>
<main class="opportunity-page"><div class="container py-5"><div class="opportunity-hero"><div><span class="eyebrow"><i class="bi bi-box-seam"></i> Commission opportunity</span><h1><?= $existing ? 'Edit Opportunity' : 'Create Opportunity' ?></h1><p>Create products or services with their own commission percentages.</p></div><a class="btn btn-light border" href="<?= e(url('business/opportunities.php')) ?>">Back</a></div><?php if (isset($errors['general'])): ?><div class="alert alert-danger mt-4"><?= e($errors['general']) ?></div><?php endif; ?>
<form method="post" class="needs-validation mt-4" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><section class="form-section"><div class="row g-3"><div class="col-md-6"><label class="form-label" for="title">Opportunity Title</label><input class="form-control" id="title" name="title" required maxlength="150" value="<?= e($values['title']) ?>"></div><div class="col-md-6"><label class="form-label" for="category">Main Category</label><input class="form-control" id="category" name="category" list="category-list" required maxlength="100" placeholder="Choose or type a category" value="<?= e($values['category']) ?>"><datalist id="category-list"><?php foreach (REFERRAL_OPPORTUNITY_CATEGORIES as $category): ?><option value="<?= e($category) ?>"><?php endforeach; ?></datalist><div class="form-text">Search or type a new main category in this field.</div></div><div class="col-12"><label class="form-label" for="description">Description <span class="text-muted fw-normal">(optional, up to 5,000 characters)</span></label><textarea class="form-control" id="description" name="description" maxlength="5000" rows="5" placeholder="Describe the opportunity, ideal customer, and terms."><?= e($values['description']) ?></textarea></div><div class="col-md-6"><label class="form-label" for="service_location">Service Location</label><input class="form-control" id="service_location" name="service_location" required maxlength="150" value="<?= e($values['service_location']) ?>"></div><div class="col-md-3"><label class="form-label" for="valid_until">Valid Until</label><input class="form-control" id="valid_until" name="valid_until" required inputmode="numeric" placeholder="DD/MM/YYYY" pattern="\d{2}/\d{2}/\d{4}" value="<?= e($values['valid_until']) ?>"></div><div class="col-md-3"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status" required><?php foreach (REFERRAL_OPPORTUNITY_STATUSES as $status): ?><option value="<?= e($status) ?>" <?= $values['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div></div></section>
<section class="form-section mt-4"><div class="d-flex justify-content-between align-items-center"><div><h2 class="h4 mb-1">Products / Services</h2><p class="mb-0 small">Each product has its own commission percentage.</p></div><button class="btn btn-outline-primary btn-sm" type="button" id="add-product"><i class="bi bi-plus-lg"></i> Add Product</button></div><div id="product-rows" class="vstack gap-2 mt-3"><?php foreach ($values['products'] as $product): ?><div class="row g-2 product-row"><div class="col-md-7"><input class="form-control" name="product_name[]" required maxlength="150" placeholder="Product or service name" value="<?= e($product['product_name'] ?? $product['name'] ?? '') ?>"></div><div class="col-md-3"><div class="input-group"><input class="form-control" name="commission_percentage[]" required type="number" min="0.01" max="100" step="0.01" placeholder="Commission" value="<?= e((string) ($product['commission_percentage'] ?? $product['rate'] ?? '')) ?>"><span class="input-group-text">%</span></div></div><div class="col-md-2 d-grid"><button class="btn btn-outline-danger remove-product" type="button">Remove</button></div></div><?php endforeach; ?></div><?php if (isset($errors['products'])): ?><p class="text-danger small mt-2 mb-0"><?= e($errors['products']) ?></p><?php endif; ?></section><button class="btn btn-primary mt-4" type="submit">Save Opportunity</button></form></div></main>
<template id="product-template"><div class="row g-2 product-row"><div class="col-md-7"><input class="form-control" name="product_name[]" required maxlength="150" placeholder="Product or service name"></div><div class="col-md-3"><div class="input-group"><input class="form-control" name="commission_percentage[]" required type="number" min="0.01" max="100" step="0.01" placeholder="Commission"><span class="input-group-text">%</span></div></div><div class="col-md-2 d-grid"><button class="btn btn-outline-danger remove-product" type="button">Remove</button></div></div></template>
<script>document.addEventListener('DOMContentLoaded',()=>{const rows=document.querySelector('#product-rows');document.querySelector('#add-product').onclick=()=>rows.append(document.querySelector('#product-template').content.cloneNode(true));rows.addEventListener('click',e=>{if(e.target.closest('.remove-product')&&rows.children.length>1)e.target.closest('.product-row').remove()})});</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
