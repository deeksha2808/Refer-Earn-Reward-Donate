<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/referrer_profile.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
require_once __DIR__ . '/../includes/customer_referrals.php';
$user = require_login('REFERRER', true);
$profile = referrer_profile((int) $user['id']);
if (!$profile || (int) $profile['is_profile_completed'] !== 1) {
    set_flash('warning', 'Complete your referrer profile before opening analytics.');
    redirect('referrer/profile.php');
}
try {
    $summaryStmt = db()->prepare('SELECT * FROM referrer_performance_summary WHERE referrer_id = ? LIMIT 1');
    $summaryStmt->execute([(int) $user['id']]);
    $summary = $summaryStmt->fetch() ?: [
        'total_referrals' => 0,
        'accepted_referrals' => 0,
        'completed_referrals' => 0,
        'total_rewards' => 0.00,
        'total_donations' => 0.00,
    ];
    $walletStats = referrer_wallet_stats((int) $user['id']);
    $recentStmt = db()->prepare('SELECT cr.id, cr.customer_name, cr.status, cr.reward_amount, cr.submitted_at, ro.title AS opportunity_title, b.full_name AS business_name FROM customer_referrals cr JOIN referral_opportunities ro ON ro.id = cr.opportunity_id JOIN users b ON b.id = cr.business_id WHERE cr.referrer_id = ? ORDER BY cr.submitted_at DESC LIMIT 12');
    $recentStmt->execute([(int) $user['id']]);
    $recentReferrals = $recentStmt->fetchAll();
} catch (PDOException $exception) {
    app_log('Referrer analytics query failed: ' . $exception->getMessage());
    $summary = [
        'total_referrals' => 0,
        'accepted_referrals' => 0,
        'completed_referrals' => 0,
        'total_rewards' => 0.00,
        'total_donations' => 0.00,
    ];
    $walletStats = ['wallet' => wallet_empty(), 'todays_earnings' => 0, 'pending_rewards' => 0, 'total_referrals' => 0, 'successful_referrals' => 0, 'pending_referrals' => 0];
    $recentReferrals = [];
}
$pageTitle = 'Referrer Analytics | ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-page referrer-dashboard"><div class="container py-5"><section class="dashboard-welcome"><div><span class="eyebrow"><i class="bi bi-graph-up-arrow"></i> Reporting</span><h1>Referrer Analytics</h1><p>Track your referral performance, earnings, and donations in one place.</p></div></section>
<div class="row g-4 mt-4"><div class="col-md-3"><div class="card p-3"><h6>Total referrals</h6><strong><?= e((string) $summary['total_referrals']) ?></strong></div></div><div class="col-md-3"><div class="card p-3"><h6>Accepted referrals</h6><strong><?= e((string) $summary['accepted_referrals']) ?></strong></div></div><div class="col-md-3"><div class="card p-3"><h6>Completed referrals</h6><strong><?= e((string) $summary['completed_referrals']) ?></strong></div></div><div class="col-md-3"><div class="card p-3"><h6>Wallet balance</h6><strong>₹ <?= number_format((float) $walletStats['wallet']['current_balance'], 2) ?></strong></div></div></div>
<div class="row g-4 mt-3"><div class="col-md-4"><div class="card p-3"><h6>Gross Earnings</h6><strong>₹ <?= number_format((float) $summary['total_rewards'], 2) ?></strong></div></div><div class="col-md-4"><div class="card p-3"><h6>Platform Fees (2%)</h6><strong>₹ <?= number_format((float) ($summary['total_platform_fees'] ?? 0), 2) ?></strong></div></div><div class="col-md-4"><div class="card p-3"><h6>Net Earnings</h6><strong>₹ <?= number_format((float) ($summary['total_net_commission'] ?? 0), 2) ?></strong></div></div></div>
<div class="row g-4 mt-3"><div class="col-md-6"><div class="card p-3"><h6>Total Donated</h6><strong>₹ <?= number_format((float) $summary['total_donations'], 2) ?></strong></div></div><div class="col-md-6"><div class="card p-3"><h6>Wallet Balance</h6><strong>₹ <?= number_format((float) $walletStats['wallet']['current_balance'], 2) ?></strong></div></div></div>
<section class="mt-4"><div class="card"><div class="card-body"><h5>Recent referrals</h5><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Customer</th><th>Opportunity</th><th>Business</th><th>Reward</th><th>Status</th><th>Submitted</th></tr></thead><tbody><?php foreach ($recentReferrals as $ref): ?><tr><td><?= e($ref['customer_name']) ?></td><td><?= e($ref['opportunity_title']) ?></td><td><?= e($ref['business_name']) ?></td><td>₹ <?= number_format((float) $ref['reward_amount'], 2) ?></td><td><?= e($ref['status']) ?></td><td><?= e(date('d M Y', strtotime($ref['submitted_at']))) ?></td></tr><?php endforeach; if (empty($recentReferrals)): ?><tr><td colspan="6">No referrals submitted yet.</td></tr><?php endif; ?></tbody></table></div></div></section>
<div class="mt-4"><a class="btn btn-light border" href="<?= e(url('referrer/dashboard.php')) ?>">Back to dashboard</a></div></div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
