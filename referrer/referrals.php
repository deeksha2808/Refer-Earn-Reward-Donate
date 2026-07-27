<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
require_once __DIR__ . '/../includes/customer_referrals.php';

$user = require_login('REFERRER');
$statement = db()->prepare('SELECT r.*, o.title AS opportunity_title, COALESCE(b.business_name, u.full_name) AS business_name FROM customer_referrals r JOIN referral_opportunities o ON o.id = r.opportunity_id JOIN users u ON u.id = r.business_id LEFT JOIN business_profiles b ON b.user_id = r.business_id WHERE r.referrer_id = ? ORDER BY r.submitted_at DESC');
$statement->execute([$user['id']]); $referrals = $statement->fetchAll();
$pageTitle = 'My referrals | ' . APP_NAME; require_once __DIR__ . '/../includes/header.php';
?>
<main class="opportunity-page"><div class="container py-5"><div class="opportunity-hero"><div><span class="eyebrow"><i class="bi bi-people"></i> Referrer workspace</span><h1>My referrals</h1><p>Follow the review progress of every customer introduction.</p></div><a class="btn btn-primary" href="<?= e(url('referrer/opportunities.php')) ?>"><i class="bi bi-search"></i> Browse opportunities</a></div><section class="opportunity-list-card mt-4"><?php if (!$referrals): ?><div class="empty-state"><span><i class="bi bi-person-plus"></i></span><h3>No referrals submitted yet</h3><p>Browse active opportunities to make your first introduction.</p></div><?php else: ?><div class="table-responsive"><table class="table opportunity-table align-middle"><thead><tr><th>Referral</th><th>Business</th><th>Campaign</th><th>Selected Product</th><th>Commission %</th><th>Current Status</th><th>Submitted</th><th class="text-end">Action</th></tr></thead><tbody><?php foreach ($referrals as $referral): ?><tr><td><strong>#<?= (int) $referral['id'] ?></strong><small><?= e($referral['customer_name']) ?></small></td><td><?= e($referral['business_name'] ?: 'Business') ?></td><td><?= e($referral['opportunity_title']) ?></td><td><?= e($referral['product_name'] ?: 'Legacy referral') ?></td><td><?= $referral['commission_percentage'] !== null ? e($referral['commission_percentage']) . '%' : '—' ?></td><td><span class="role-badge"><?= e($referral['status']) ?></span></td><td><?= e(date('d M Y', strtotime($referral['submitted_at']))) ?></td><td class="text-end"><a class="btn btn-sm btn-light border" href="<?= e(url('referrer/referral_view.php?id=' . $referral['id'])) ?>">View details</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section></div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
