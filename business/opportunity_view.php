<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
$user = require_login('BUSINESS');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$opportunity = business_opportunity($id, (int) $user['id']);
if (!$opportunity) { set_flash('danger', 'That referral opportunity was not found.'); redirect('business/opportunities.php'); }
$products = opportunity_products((int) $opportunity['id']);
$pageTitle = e($opportunity['title']) . ' | ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<main class="opportunity-page"><div class="container py-5"><div class="opportunity-hero"><div><span class="eyebrow"><i class="bi bi-megaphone"></i> Referral opportunity</span><h1><?= e($opportunity['title']) ?></h1><p><?= e($opportunity['category']) ?> · <?= e($opportunity['service_location']) ?></p></div><div class="d-flex gap-2"><a class="btn btn-light border" href="<?= e(url('business/opportunities.php')) ?>">Back</a><a class="btn btn-primary" href="<?= e(url('business/opportunity_form.php?id=' . $opportunity['id'])) ?>"><i class="bi bi-pencil"></i> Edit</a></div></div><div class="row g-4 mt-1"><div class="col-lg-8"><section class="detail-card"><span class="opportunity-status <?= strtolower(e($opportunity['status'])) ?>"><?= e($opportunity['status']) ?></span><h2>About this opportunity</h2><p class="detail-description"><?= nl2br(e($opportunity['description'])) ?></p></section><section class="detail-card mt-4"><h2>Products and commissions</h2><dl class="detail-list"><?php foreach ($products as $product): ?><div><dt><?= e($product['product_name']) ?></dt><dd><?= e($product['commission_percentage']) ?>%</dd></div><?php endforeach; ?></dl></section></div><div class="col-lg-4"><section class="detail-card"><h2>At a glance</h2><dl class="detail-list"><div><dt>Products / services</dt><dd><?= count($products) ?></dd></div><div><dt>Valid Until</dt><dd><?= e(date('d M Y', strtotime($opportunity['valid_until']))) ?></dd></div><div><dt>Location</dt><dd><?= e($opportunity['service_location']) ?></dd></div></dl></section></div></div></div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
