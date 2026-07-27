<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/referrer_profile.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
require_once __DIR__ . '/../includes/customer_referrals.php';
require_once __DIR__ . '/../includes/wallet.php';

$user = require_login('REFERRER');
$profile = referrer_profile((int) $user['id']);
if (!$profile || (int) $profile['is_profile_completed'] !== 1) redirect('referrer/profile.php');
try {
    $statement = db()->query("SELECT COUNT(*) FROM referral_opportunities WHERE status = 'Active' AND valid_until >= CURDATE()");
    $activeOpportunities = (int) $statement->fetchColumn();
    $statement = db()->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(status = 'Completed'), 0) AS completed, COALESCE(SUM(status IN ('Submitted', 'Under Review', 'Processing', 'Accepted')), 0) AS pending FROM customer_referrals WHERE referrer_id = ?");
    $statement->execute([$user['id']]);
    $referralStats = $statement->fetch() ?: [];
    $walletStats = referrer_wallet_stats((int) $user['id']);
} catch (PDOException $exception) {
    app_log('Referrer dashboard referral statistics failed: ' . $exception->getMessage());
    $activeOpportunities = 0; $referralStats = ['total' => 0, 'completed' => 0, 'pending' => 0];
    $walletStats = ['wallet' => wallet_empty(), 'todays_earnings' => 0, 'pending_rewards' => 0, 'total_referrals' => 0, 'successful_referrals' => 0, 'pending_referrals' => 0];
}
    $wallet = $walletStats['wallet'];
$pageTitle = 'Referrer dashboard | ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
$status = (string) $profile['verification_status'];
?>
<main class="dashboard-page referrer-dashboard"><div class="container py-5"><div class="dashboard-welcome"><div><span class="eyebrow"><i class="bi bi-person-heart"></i> Referrer workspace</span><h1>Welcome back, <?= e($profile['full_name']) ?>.</h1><p>Discover opportunities, track your referrals, and watch your impact grow.</p></div><div class="dashboard-profile"><?php if (!empty($profile['profile_photo'])): ?><img src="<?= e(url((string) $profile['profile_photo'])) ?>" alt="<?= e($profile['full_name']) ?>"><?php else: ?><span class="welcome-icon" aria-hidden="true"><i class="bi bi-person-circle"></i></span><?php endif; ?><a href="<?= e(url('referrer/profile.php')) ?>" class="btn btn-light border">Edit profile</a></div></div>
<div class="row g-4 mt-1"><?php foreach ([['bi-wallet2','Wallet Balance',opportunity_money($wallet['current_balance']),'Available For Donation'],['bi-sun','Today\'s Earnings',opportunity_money($walletStats['todays_earnings']),'Credited Today'],['bi-cash-stack','Total Earnings',opportunity_money($wallet['total_earned']),'Lifetime Wallet Rewards'],['bi-award','Completed Referrals',(string) $walletStats['successful_referrals'],'Commission Credited'],['bi-hourglass-split','In Progress',(string) $walletStats['pending_referrals'],'Awaiting Completion'],['bi-heart','Donation Balance',opportunity_money($wallet['total_donated']),'Total Donated']] as [$icon,$label,$value,$hint]): ?><div class="col-sm-6 col-lg-4"><div class="stat-card"><i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span><strong><?= e($value) ?></strong><small><?= e($hint) ?></small></div></div><?php endforeach; ?></div>
<div class="row g-4 mt-1"><div class="col-lg-8"><section class="dashboard-panel"><div class="panel-heading"><div><h2>Quick Actions</h2><p>Everything You Need To Get Started.</p></div><i class="bi bi-lightning-charge"></i></div><div class="quick-actions"><?php foreach ([['bi-search','Browse Opportunities', 'referrer/opportunities.php'],['bi-people','My Referrals', 'referrer/referrals.php'],['bi-wallet2','Wallet', 'referrer/wallet.php'],['bi-gift','Rewards', 'referrer/reward_history.php'],['bi-receipt','Transactions', 'referrer/transactions.php'],['bi-heart','Donate', 'referrer/donate.php'],['bi-bar-chart-line','Analytics', 'referrer/analytics.php']] as [$icon,$label,$path]): ?><a href="<?= e(url($path)) ?>"><i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span><i class="bi bi-arrow-up-right"></i></a><?php endforeach; ?></div></section></div><div class="col-lg-4"><section class="dashboard-panel"><div class="panel-heading"><div><h2>Profile Status</h2><p>Your Account Is Ready For Review.</p></div><i class="bi bi-shield-check"></i></div><div class="completion-ring" style="--completion:<?= referrer_profile_completion($profile) ?>"><span><?= referrer_profile_completion($profile) ?>%</span></div><div class="status-block"><span class="status-dot <?= $status === 'Verified' ? 'verified' : '' ?>"></span><div><strong><?= e($status) ?> Verification</strong><small><?= $status === 'Verified' ? 'Your profile has been verified.' : 'We will notify you when review is complete.' ?></small></div></div></section></div></div>
<div class="row g-4 mt-1"><?php foreach ([['bi-wallet2','Wallet Summary','referrer/wallet.php'],['bi-cash-coin','Latest Earnings','referrer/reward_history.php'],['bi-receipt','Transaction History','referrer/transactions.php'],['bi-heart-pulse','Donation History','referrer/donations.php']] as [$icon,$title,$path]): ?><div class="col-md-6 col-xl-3"><a class="activity-placeholder text-decoration-none" href="<?= e(url($path)) ?>"><i class="bi <?= e($icon) ?>"></i><h2><?= e($title) ?></h2><p>Open Details</p></a></div><?php endforeach; ?></div></div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
