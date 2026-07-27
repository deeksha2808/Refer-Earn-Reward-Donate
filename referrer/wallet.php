<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/referrer_profile.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/razorpay_service.php';

$user = require_login('REFERRER');
$profile = referrer_profile((int) $user['id']);
if (!$profile || (int) $profile['is_profile_completed'] !== 1) redirect('referrer/profile.php');
$wallet = referrer_wallet((int) $user['id']);
$withdrawalStats = referrer_withdrawal_stats((int) $user['id']);
$withdrawals = referrer_withdrawals((int) $user['id']);

$feeStmt = db()->prepare("SELECT COALESCE(SUM(platform_fee), 0) FROM customer_referrals WHERE referrer_id = ? AND status = 'Completed'");
$feeStmt->execute([$user['id']]);
$totalPlatformFees = (float) $feeStmt->fetchColumn();

$hasBank = !empty($profile['bank_account_number']) && !empty($profile['ifsc_code']);
$hasUpi = !empty($profile['upi_id']);

$pageTitle = 'Wallet | ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<main class="opportunity-page"><div class="container py-5"><div class="opportunity-hero"><div><span class="eyebrow"><i class="bi bi-wallet2"></i> Referrer wallet</span><h1>Wallet</h1><p>Track earned rewards, withdrawals, and your current balance.</p></div><div class="d-flex gap-2"><a class="btn btn-light border" href="<?= e(url('referrer/dashboard.php')) ?>">Dashboard</a><a class="btn btn-primary" href="<?= e(url('referrer/donate.php')) ?>"><i class="bi bi-heart"></i> Donate</a></div></div>

<div class="row g-4 mt-1">
<?php foreach ([
    ['Current Balance', $wallet['current_balance'], 'bi-wallet2'],
    ['Lifetime Earnings', $wallet['total_earned'], 'bi-cash-stack'],
    ['Platform Fees Deducted', $totalPlatformFees, 'bi-percent'],
    ['Total Withdrawn', $withdrawalStats['total_withdrawn'], 'bi-bank'],
    ['Total Donated', $wallet['total_donated'], 'bi-heart'],
    ['Pending Withdrawals', $withdrawalStats['pending_amount'], 'bi-hourglass-split'],
] as [$label, $value, $icon]): ?>
<div class="col-sm-6 col-lg-4"><div class="stat-card"><i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span><strong><?= e(opportunity_money($value)) ?></strong></div></div>
<?php endforeach; ?>
</div>

<!-- Withdrawal Section -->
<section class="dashboard-panel mt-4"><div class="panel-heading"><div><h2>Withdraw via Razorpay</h2><p>Transfer your wallet balance to your bank account or UPI. Minimum ₹100.</p></div></div>
<div id="withdrawal-feedback" aria-live="polite"></div>
<?php if (!$hasBank && !$hasUpi): ?>
<div class="alert alert-warning mt-3">Please update your <a href="<?= e(url('referrer/profile.php')) ?>">profile</a> with bank account or UPI details before withdrawing.</div>
<?php else: ?>
<form id="withdrawal-form" class="row g-3 mt-2"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<div class="col-md-4"><label class="form-label" for="withdraw_amount">Amount</label><div class="input-group"><span class="input-group-text">₹</span><input class="form-control" id="withdraw_amount" name="amount" type="number" min="100" max="<?= e((string) $wallet['current_balance']) ?>" step="0.01" required></div><div class="form-text">Available: <?= e(opportunity_money($wallet['current_balance'])) ?></div></div>
<div class="col-md-4"><label class="form-label">Payout Destination</label>
<div class="d-flex gap-3">
<?php if ($hasBank): ?><label class="d-flex align-items-center gap-2"><input type="radio" name="payout_mode" value="bank" checked class="form-check-input"> <span>Bank Account<small class="d-block text-muted"><?= e(str_repeat('•', max(0, strlen($profile['bank_account_number']) - 4)) . substr($profile['bank_account_number'], -4)) ?></small></span></label><?php endif; ?>
<?php if ($hasUpi): ?><label class="d-flex align-items-center gap-2"><input type="radio" name="payout_mode" value="upi" <?= !$hasBank ? 'checked' : '' ?> class="form-check-input"> <span>UPI<small class="d-block text-muted"><?= e($profile['upi_id']) ?></small></span></label><?php endif; ?>
</div></div>
<div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary px-4" type="submit" <?= (float) $wallet['current_balance'] < 100 ? 'disabled' : '' ?>><i class="bi bi-bank"></i> Withdraw</button></div>
</form>
<?php endif; ?>
</section>

<!-- Wallet Actions -->
<section class="dashboard-panel mt-4"><div class="panel-heading"><div><h2>Wallet actions</h2></div><i class="bi bi-grid"></i></div><div class="quick-actions"><?php foreach ([['bi-gift','Reward history','referrer/reward_history.php'],['bi-receipt','Transactions','referrer/transactions.php'],['bi-heart-pulse','Donations','referrer/donations.php'],['bi-heart','Donate now','referrer/donate.php']] as [$icon,$label,$path]): ?><a href="<?= e(url($path)) ?>"><i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span><i class="bi bi-arrow-up-right"></i></a><?php endforeach; ?></div></section>

<!-- Withdrawal History -->
<?php if ($withdrawals): ?>
<section class="opportunity-list-card mt-4"><div class="list-card-heading"><div><h2>Withdrawal History</h2><p><?= count($withdrawals) ?> withdrawal<?= count($withdrawals) !== 1 ? 's' : '' ?></p></div></div>
<div class="table-responsive"><table class="table opportunity-table align-middle"><thead><tr><th>Date</th><th>Amount</th><th>Mode</th><th>Reference</th><th>UTR</th><th>Status</th></tr></thead><tbody><?php foreach ($withdrawals as $w): ?><tr><td><?= e(date('d M Y, h:i A', strtotime($w['created_at']))) ?></td><td><?= e(opportunity_money($w['amount'])) ?></td><td><?= e(strtoupper($w['payout_mode'] ?? $w['payment_method'] ?? '—')) ?></td><td><?= e($w['reference_number'] ?: '—') ?></td><td><?= e($w['utr_number'] ?: '—') ?></td><td><span class="role-badge"><?= e(ucfirst($w['status'])) ?></span><?php if ($w['failure_reason']): ?><small class="d-block text-danger"><?= e($w['failure_reason']) ?></small><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php endif; ?>
</div></main>

<script>
document.getElementById('withdrawal-form')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const btn = form.querySelector('[type="submit"]');
  const feedback = document.getElementById('withdrawal-feedback');
  const original = btn.innerHTML;
  btn.disabled = true; btn.textContent = 'Processing payout…';
  feedback.innerHTML = '';
  try {
    const response = await fetch('<?= e(url('api/withdrawal.php')) ?>', {
      method: 'POST', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body: new FormData(form)
    });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Withdrawal failed.');
    feedback.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
    setTimeout(() => window.location.reload(), 2500);
  } catch (err) {
    feedback.innerHTML = '<div class="alert alert-danger">' + err.message + '</div>';
    btn.disabled = false; btn.innerHTML = original;
  }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
