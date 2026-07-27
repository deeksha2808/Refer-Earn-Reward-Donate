<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
require_once __DIR__ . '/../includes/customer_referrals.php';

require_login('REFERRER');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$opportunity = active_opportunity($id);
if (!$opportunity) { set_flash('danger', 'That active opportunity was not found.'); redirect('referrer/opportunities.php'); }
$products = opportunity_products((int) $opportunity['id']);
$pageTitle = $opportunity['title'] . ' | ' . APP_NAME; require_once __DIR__ . '/../includes/header.php';
?>
<main class="opportunity-page"><div class="container py-5"><div class="opportunity-hero"><div><span class="eyebrow"><i class="bi bi-briefcase"></i> <?= e($opportunity['category']) ?></span><h1><?= e($opportunity['title']) ?></h1><p><?= e($opportunity['business_name']) ?> · <?= e($opportunity['service_location']) ?></p></div><div class="d-flex gap-2"><a class="btn btn-light border" href="<?= e(url('referrer/opportunities.php')) ?>">Back</a><a class="btn btn-primary" href="<?= e(url('referrer/referral_form.php?id=' . $opportunity['id'])) ?>">Submit referral</a></div></div><div class="row g-4 mt-1"><div class="col-lg-8"><section class="detail-card mb-4"><h2>Campaign Information</h2><dl class="detail-list"><div><dt>Main category</dt><dd><?= e($opportunity['category']) ?></dd></div><div><dt>Campaign status</dt><dd><?= e($opportunity['status']) ?></dd></div><div><dt>Expiry date</dt><dd><?= e(date('d M Y', strtotime($opportunity['valid_until']))) ?></dd></div></dl></section><section class="detail-card mb-4"><h2>Description</h2><p class="detail-description"><?= nl2br(e($opportunity['description'] ?: 'No description provided.')) ?></p></section><section class="detail-card"><h2>Products / Services</h2><dl class="detail-list"><?php foreach ($products as $product): ?><div><dt><?= e($product['product_name']) ?></dt><dd><?= e($product['commission_percentage']) ?>%</dd></div><?php endforeach; ?></dl></section></div><div class="col-lg-4"><section class="detail-card"><h2>Business Information</h2><dl class="detail-list"><div><dt>Business</dt><dd><?= e($opportunity['business_name']) ?></dd></div><div><dt>Contact</dt><dd><?= e($opportunity['business_email'] ?: 'Not provided') ?></dd></div><div><dt>Phone</dt><dd><?= e($opportunity['business_phone'] ?: 'Not provided') ?></dd></div><div><dt>Address</dt><dd><?= e($opportunity['business_address'] ?: $opportunity['business_city'] ?: $opportunity['service_location']) ?></dd></div></dl></section></div></div></div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
