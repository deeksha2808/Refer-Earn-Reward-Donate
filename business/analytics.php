<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/business_profile.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
require_once __DIR__ . '/../includes/customer_referrals.php';
$user = require_login('BUSINESS', true);
$profile = business_profile((int) $user['id']);
if (!$profile || (int) $profile['is_profile_completed'] !== 1) {
    set_flash('warning', 'Complete your business profile before opening analytics.');
    redirect('business/profile.php');
}
try {
    $summaryStmt = db()->prepare('SELECT * FROM business_referral_summary WHERE business_id = ? LIMIT 1');
    $summaryStmt->execute([(int) $user['id']]);
    $summary = $summaryStmt->fetch() ?: [
        'total_opportunities' => 0,
        'active_opportunities' => 0,
        'inactive_opportunities' => 0,
        'total_referrals' => 0,
        'accepted_referrals' => 0,
        'completed_referrals' => 0,
        'total_referral_rewards' => 0.00,
    ];
    $opportunityStmt = db()->prepare('SELECT status, COUNT(*) AS count FROM referral_opportunities WHERE business_id = ? GROUP BY status');
    $opportunityStmt->execute([(int) $user['id']]);
    $opportunityStatus = $opportunityStmt->fetchAll();
    $recentStmt = db()->prepare('SELECT cr.id, cr.customer_name, cr.status, cr.reward_amount, cr.submitted_at, ro.title AS opportunity_title, r.full_name AS referrer_name FROM customer_referrals cr JOIN referral_opportunities ro ON ro.id = cr.opportunity_id JOIN users r ON r.id = cr.referrer_id WHERE cr.business_id = ? ORDER BY cr.submitted_at DESC LIMIT 12');
    $recentStmt->execute([(int) $user['id']]);
    $recentReferrals = $recentStmt->fetchAll();
} catch (PDOException $exception) {
    app_log('Business analytics query failed: ' . $exception->getMessage());
    $summary = [
        'total_opportunities' => 0,
        'active_opportunities' => 0,
        'inactive_opportunities' => 0,
        'total_referrals' => 0,
        'accepted_referrals' => 0,
        'completed_referrals' => 0,
        'total_referral_rewards' => 0.00,
    ];
    $opportunityStatus = [];
    $recentReferrals = [];
}
$pageTitle = 'Business Analytics | ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-page"><div class="container py-5"><section class="dashboard-welcome business-welcome"><div><span class="eyebrow"><i class="bi bi-bar-chart-line"></i> Analytics</span><h1>Business Analytics</h1><p>Review referral performance, opportunity status, and reward trends for your business.</p></div></section>
<div class="row g-4 mt-4"><div class="col-md-4"><div class="card p-3"><h6>Active opportunities</h6><strong><?= e((string) $summary['active_opportunities']) ?></strong></div></div><div class="col-md-4"><div class="card p-3"><h6>Inactive opportunities</h6><strong><?= e((string) $summary['inactive_opportunities']) ?></strong></div></div><div class="col-md-4"><div class="card p-3"><h6>Total referrals</h6><strong><?= e((string) $summary['total_referrals']) ?></strong></div></div></div>
<div class="row g-4 mt-3"><div class="col-md-4"><div class="card p-3"><h6>Accepted referrals</h6><strong><?= e((string) $summary['accepted_referrals']) ?></strong></div></div><div class="col-md-4"><div class="card p-3"><h6>Completed referrals</h6><strong><?= e((string) $summary['completed_referrals']) ?></strong></div></div><div class="col-md-4"><div class="card p-3"><h6>Total Gross Commission</h6><strong>₹ <?= number_format((float) $summary['total_referral_rewards'], 2) ?></strong></div></div></div>
<div class="row g-4 mt-3"><div class="col-md-4"><div class="card p-3"><h6>Platform Revenue (2% fees)</h6><strong>₹ <?= number_format((float) ($summary['total_platform_fees'] ?? 0), 2) ?></strong></div></div><div class="col-md-4"><div class="card p-3"><h6>Total Net Rewards Paid</h6><strong>₹ <?= number_format((float) ($summary['total_net_commission'] ?? 0), 2) ?></strong></div></div><div class="col-md-4"><div class="card p-3"><h6>Fee Rate</h6><strong>2%</strong></div></div></div>
<section class="mt-4"><div class="card"><div class="card-body"><h5>Opportunity status breakdown</h5><div class="row g-3 mt-3"><?php foreach ($opportunityStatus as $row): ?><div class="col-md-4"><div class="p-3 border rounded"><strong><?= e($row['count']) ?></strong><p class="mb-0"><?= e($row['status']) ?></p></div></div><?php endforeach; if (empty($opportunityStatus)): ?><p>No opportunity data available yet.</p><?php endif; ?></div></div></div></section>
<section class="mt-4"><div class="card"><div class="card-body"><h5>Recent referrals</h5><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Customer</th><th>Opportunity</th><th>Referrer</th><th>Reward</th><th>Status</th><th>Submitted</th></tr></thead><tbody><?php foreach ($recentReferrals as $ref): ?><tr><td><?= e($ref['customer_name']) ?></td><td><?= e($ref['opportunity_title']) ?></td><td><?= e($ref['referrer_name']) ?></td><td>₹ <?= number_format((float) $ref['reward_amount'], 2) ?></td><td><?= e($ref['status']) ?></td><td><?= e(date('d M Y', strtotime($ref['submitted_at']))) ?></td></tr><?php endforeach; if (empty($recentReferrals)): ?><tr><td colspan="6">No referrals submitted yet.</td></tr><?php endif; ?></tbody></table></div></div></div></section>
<div class="mt-4"><a class="btn btn-light border" href="<?= e(url('business/dashboard.php')) ?>">Back to dashboard</a></div></div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
