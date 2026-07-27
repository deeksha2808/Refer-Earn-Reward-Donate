<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/referrer_profile.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';

$user = require_login('REFERRER');
$profile = referrer_profile((int) $user['id']);
if (!$profile || (int) $profile['is_profile_completed'] !== 1) redirect('referrer/profile.php');
$statement = db()->prepare("SELECT r.id, r.customer_name, r.status, r.product_name, r.sale_amount, r.commission_percentage, r.calculated_commission, r.reward_amount, o.title AS opportunity_title, COALESCE(b.business_name, u.full_name) AS business_name, wt.created_at AS credited_at FROM customer_referrals r JOIN referral_opportunities o ON o.id = r.opportunity_id JOIN users u ON u.id = r.business_id LEFT JOIN business_profiles b ON b.user_id = r.business_id LEFT JOIN wallet_transactions wt ON wt.referral_id = r.id AND wt.transaction_type = 'Reward Credit' WHERE r.referrer_id = ? ORDER BY COALESCE(wt.created_at, r.submitted_at) DESC");
$statement->execute([$user['id']]);
$rewards = $statement->fetchAll();
$pageTitle = 'Reward history | ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<main class="opportunity-page"><div class="container py-5"><div class="opportunity-hero"><div><span class="eyebrow"><i class="bi bi-gift"></i> Rewards</span><h1>Reward history</h1><p>See each referral commission and when it was credited.</p></div><a class="btn btn-light border" href="<?= e(url('referrer/wallet.php')) ?>">Back to wallet</a></div><section class="opportunity-list-card mt-4"><?php if (!$rewards): ?><div class="empty-state"><span><i class="bi bi-gift"></i></span><h3>No rewards yet</h3><p>Completed referrals and credited rewards will appear here.</p></div><?php else: ?><div class="table-responsive"><table class="table opportunity-table align-middle"><thead><tr><th>Campaign</th><th>Product</th><th>Sale Amount</th><th>Commission %</th><th>Commission Amount</th><th>Status</th><th>Credited Date</th></tr></thead><tbody><?php foreach ($rewards as $reward): ?><tr><td><strong><?= e($reward['opportunity_title']) ?></strong><small>#<?= (int) $reward['id'] ?> · <?= e($reward['customer_name']) ?></small></td><td><?= e($reward['product_name'] ?: 'Legacy referral') ?></td><td><?= $reward['sale_amount'] !== null ? e(opportunity_money($reward['sale_amount'])) : '—' ?></td><td><?= $reward['commission_percentage'] !== null ? e($reward['commission_percentage']) . '%' : '—' ?></td><td><?= e(opportunity_money($reward['calculated_commission'] ?? $reward['reward_amount'])) ?></td><td><span class="role-badge"><?= e($reward['status']) ?></span></td><td><?= $reward['credited_at'] ? e(date('d M Y, h:i A', strtotime($reward['credited_at']))) : 'Not credited yet' ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section></div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
