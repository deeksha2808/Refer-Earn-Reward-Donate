<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/business_profile.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
require_once __DIR__ . '/../includes/customer_referrals.php';
$user = require_login('BUSINESS');
$profile = business_profile((int) $user['id']);
$complete = $profile !== null && (int) $profile['is_profile_completed'] === 1;
if (!$complete) {
    set_flash('warning', 'Complete your business profile before accessing the dashboard.');
    redirect('business/profile.php');
}

try {
    $opportunityStats = business_opportunity_stats((int) $user['id']);
    $referralStats = business_referral_stats((int) $user['id']);
} catch (PDOException $exception) {
    app_log('Business dashboard opportunity statistics failed: ' . $exception->getMessage());
    $opportunityStats = ['total' => 0, 'active' => 0, 'closed' => 0, 'paused' => 0];
    $referralStats = ['received' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0, 'completed' => 0, 'rewards_paid' => 0.0];
}
$completion = $profile ? business_profile_completion($profile) : 0;
$pageTitle = 'Business dashboard | ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-page"><div class="container py-5"><section class="dashboard-welcome business-welcome"><div><span class="eyebrow"><i class="bi bi-buildings"></i> Business workspace</span><h1><?= $complete ? e($profile['business_name']) : 'Welcome, ' . e($user['full_name']) ?></h1><p><?= $complete ? e($profile['business_category']) . ' · Profile ready for review.' : 'Complete your profile to unlock your business workspace.' ?></p></div><?php if ($complete && $profile['logo']): ?><img class="business-logo" src="<?= e(url($profile['logo'])) ?>" alt="<?= e($profile['business_name']) ?> logo"><?php else: ?><div class="welcome-icon"><i class="bi bi-buildings"></i></div><?php endif; ?></section>
<?php if (!$complete): ?><section class="profile-nudge mt-4"><div><span class="nudge-icon"><i class="bi bi-stars"></i></span><div><h2>Complete your business profile</h2><p>Add your business details and verification files to unlock upcoming referral features.</p></div></div><a class="btn btn-primary" href="<?= e(url('business/profile.php')) ?>">Complete profile <i class="bi bi-arrow-right"></i></a></section><?php endif; ?>
<section class="dashboard-panel opportunity-overview mt-4"><div class="panel-heading"><div><h2>Referral opportunity overview</h2><p>Monitor your opportunities and the referrals they generate.</p></div><a class="btn btn-primary btn-sm" href="<?= e(url('business/opportunity_form.php')) ?>"><i class="bi bi-plus-lg"></i> Create opportunity</a></div><div class="row g-3 mt-1"><?php foreach ([['Total rewards paid', opportunity_money($referralStats['rewards_paid']), 'bi-cash-coin'], ['Completed referrals', $referralStats['completed'], 'bi-award'], ['Pending referrals', $referralStats['pending'], 'bi-hourglass-split'], ['Accepted referrals', $referralStats['accepted'], 'bi-check2-circle'], ['Rejected referrals', $referralStats['rejected'], 'bi-x-circle'], ['Total opportunities', $opportunityStats['total'], 'bi-grid-1x2'], ['Active opportunities', $opportunityStats['active'], 'bi-broadcast']] as [$label, $value, $icon]): ?><div class="col-6 col-md-4 col-xl"><div class="opportunity-metric"><i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span><strong><?= e((string) $value) ?></strong></div></div><?php endforeach; ?></div><div class="mt-4 d-flex gap-4"><a class="small-link" href="<?= e(url('business/opportunities.php')) ?>">View my opportunities <i class="bi bi-arrow-right"></i></a><a class="small-link" href="<?= e(url('business/referrals.php')) ?>">Review referrals <i class="bi bi-arrow-right"></i></a><a class="small-link" href="<?= e(url('business/analytics.php')) ?>">Analytics <i class="bi bi-bar-chart-line"></i></a><a class="small-link" href="<?= e(url('business/reports.php')) ?>">Reports &amp; exports <i class="bi bi-clipboard-data"></i></a></div></section>
<div class="row g-4 mt-1"><div class="col-lg-6"><section class="dashboard-panel completion-panel"><div class="panel-heading"><div><h2>Profile completion</h2><p>Keep your profile complete and current.</p></div><i class="bi bi-pie-chart"></i></div><div class="completion-ring" style="--completion: <?= $completion ?>"><span><?= $completion ?>%</span></div><p class="text-center small mb-0"><?= $complete ? 'Your business profile is complete.' : 'Complete all five steps to unlock your workspace.' ?></p></section></div><div class="col-lg-6"><section class="dashboard-panel"><div class="panel-heading"><div><h2>Profile actions</h2><p>Review or update your business information.</p></div><i class="bi bi-pencil-square"></i></div><a class="btn btn-outline-primary w-100 mt-3" href="<?= e(url('business/profile.php')) ?>"><?= $complete ? 'Edit business profile' : 'Continue profile' ?></a></section></div></div>
<div class="row g-4 mt-1"><div class="col-lg-7"><section class="dashboard-panel"><div class="panel-heading"><div><h2>Recent activity</h2><p>Business updates and referral activity will appear here.</p></div><i class="bi bi-clock-history"></i></div><div class="empty-state"><span><i class="bi bi-inbox"></i></span><h3>No activity yet</h3><p>Your latest business activity will appear here in a future module.</p></div></section></div><div class="col-lg-5"><section class="dashboard-panel"><div class="panel-heading"><div><h2><?= $complete ? 'Business summary' : 'Locked features' ?></h2><p><?= $complete ? 'Your submitted business details.' : 'Available after profile completion.' ?></p></div><i class="bi <?= $complete ? 'bi-building' : 'bi-lock' ?>"></i></div><?php if ($complete): ?><dl class="user-panel"><div><dt>Category</dt><dd><?= e($profile['business_category']) ?></dd></div><div><dt>Email</dt><dd><?= e($profile['business_email']) ?></dd></div><div><dt>Location</dt><dd><?= e($profile['city'] . ', ' . $profile['country']) ?></dd></div></dl><?php else: ?><ul class="locked-list"><li><i class="bi bi-lock-fill"></i> Referral opportunities</li><li><i class="bi bi-lock-fill"></i> Earnings and commissions</li><li><i class="bi bi-lock-fill"></i> Business insights</li></ul><?php endif; ?></section></div></div></div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
