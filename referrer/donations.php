<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/referrer_profile.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
require_once __DIR__ . '/../includes/wallet.php';
require_once __DIR__ . '/../includes/ngos.php';

$user = require_login('REFERRER');
$profile = referrer_profile((int) $user['id']);
if (!$profile || (int) $profile['is_profile_completed'] !== 1) redirect('referrer/profile.php');
$wallet = referrer_wallet((int) $user['id']);
$donations = [];
$reportsByNgo = [];
$reportsByDistrict = [];
$reportsByCategory = [];
$analytics = ['by_ngo' => [], 'by_district' => [], 'by_category' => [], 'monthly' => []];

if ((int) $wallet['id'] > 0) {
    $statement = db()->prepare('SELECT d.*, n.name AS ngo_name, n.district AS ngo_district, n.category AS ngo_category FROM donations d LEFT JOIN ngos n ON n.id = d.ngo_id WHERE d.wallet_id = ? ORDER BY d.created_at DESC, d.id DESC');
    $statement->execute([(int) $wallet['id']]);
    $donations = $statement->fetchAll();
    $reportsByNgo = donation_reports_by_ngo((int) $wallet['id']);
    $reportsByDistrict = donation_reports_by_district((int) $wallet['id']);
    $reportsByCategory = donation_reports_by_category((int) $wallet['id']);
    $analytics = donation_analytics((int) $wallet['id']);
}

$tab = in_array($_GET['tab'] ?? '', ['history', 'reports', 'analytics'], true) ? (string) $_GET['tab'] : 'history';
$pageTitle = 'Donation History | ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<main class="opportunity-page"><div class="container py-5"><div class="opportunity-hero"><div><span class="eyebrow"><i class="bi bi-heart-pulse"></i> Donations</span><h1>Donation Centre</h1><p>View your donation history, reports, and analytics.</p></div><div class="d-flex gap-2"><a class="btn btn-light border" href="<?= e(url('referrer/wallet.php')) ?>">Wallet</a><a class="btn btn-primary" href="<?= e(url('referrer/donate.php')) ?>">Donate now</a></div></div>

<div class="report-tabs mt-4"><a class="<?= $tab === 'history' ? 'active' : '' ?>" href="<?= e(url('referrer/donations.php?tab=history')) ?>"><i class="bi bi-clock-history"></i> History</a><a class="<?= $tab === 'reports' ? 'active' : '' ?>" href="<?= e(url('referrer/donations.php?tab=reports')) ?>"><i class="bi bi-clipboard-data"></i> Reports</a><a class="<?= $tab === 'analytics' ? 'active' : '' ?>" href="<?= e(url('referrer/donations.php?tab=analytics')) ?>"><i class="bi bi-bar-chart-line"></i> Analytics</a></div>

<?php if ($tab === 'history'): ?>
<section class="opportunity-list-card mt-4"><?php if (!$donations): ?><div class="empty-state"><span><i class="bi bi-heart"></i></span><h3>No donations yet</h3><p>Completed donations will appear here.</p></div><?php else: ?><div class="table-responsive"><table class="table opportunity-table align-middle"><thead><tr><th>Date</th><th>NGO</th><th>District</th><th>Category</th><th>Amount</th><th>Status</th><th>Message</th></tr></thead><tbody><?php foreach ($donations as $donation): ?><tr><td><?= e(date('d M Y, h:i A', strtotime($donation['created_at']))) ?></td><td><?= e($donation['ngo_name'] ?: $donation['cause_name']) ?></td><td><?= e($donation['ngo_district'] ?: '—') ?></td><td><?= e($donation['ngo_category'] ?: $donation['cause_name']) ?></td><td><?= e(opportunity_money($donation['donation_amount'])) ?></td><td><span class="role-badge"><?= e($donation['status']) ?></span></td><td><?= e($donation['message'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>

<?php elseif ($tab === 'reports'): ?>
<div class="row g-4 mt-1">
<div class="col-lg-6"><section class="dashboard-panel"><div class="panel-heading"><div><h2>NGO-wise Donations</h2><p>Top NGOs you have donated to.</p></div></div><?php if ($reportsByNgo): ?><div class="table-responsive"><table class="table table-sm"><thead><tr><th>NGO</th><th>District</th><th>Donations</th><th>Total</th></tr></thead><tbody><?php foreach ($reportsByNgo as $r): ?><tr><td><?= e($r['ngo_name']) ?></td><td><?= e($r['district']) ?></td><td><?= (int) $r['total_donations'] ?></td><td><?= e(opportunity_money($r['total_amount'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="text-muted small p-3">No NGO donations yet.</p><?php endif; ?></section></div>
<div class="col-lg-6"><section class="dashboard-panel"><div class="panel-heading"><div><h2>District-wise Donations</h2><p>Donations grouped by district.</p></div></div><?php if ($reportsByDistrict): ?><div class="table-responsive"><table class="table table-sm"><thead><tr><th>District</th><th>Donations</th><th>Total</th></tr></thead><tbody><?php foreach ($reportsByDistrict as $r): ?><tr><td><?= e($r['district']) ?></td><td><?= (int) $r['total_donations'] ?></td><td><?= e(opportunity_money($r['total_amount'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="text-muted small p-3">No district data yet.</p><?php endif; ?></section></div>
<div class="col-lg-12"><section class="dashboard-panel"><div class="panel-heading"><div><h2>Category-wise Donations</h2><p>Donations grouped by NGO category.</p></div></div><?php if ($reportsByCategory): ?><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Category</th><th>Donations</th><th>Total</th></tr></thead><tbody><?php foreach ($reportsByCategory as $r): ?><tr><td><?= e($r['category']) ?></td><td><?= (int) $r['total_donations'] ?></td><td><?= e(opportunity_money($r['total_amount'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="text-muted small p-3">No category data yet.</p><?php endif; ?></section></div>
</div>

<?php elseif ($tab === 'analytics'): ?>
<div class="row g-4 mt-1">
<div class="col-lg-6"><div class="dashboard-panel chart-card"><h2>Top NGOs by Donations</h2><canvas id="ngoChart"></canvas></div></div>
<div class="col-lg-6"><div class="dashboard-panel chart-card"><h2>Donations by District</h2><canvas id="districtChart"></canvas></div></div>
<div class="col-lg-6"><div class="dashboard-panel chart-card"><h2>Donations by Category</h2><canvas id="categoryChart"></canvas></div></div>
<div class="col-lg-6"><div class="dashboard-panel chart-card"><h2>Monthly Donations</h2><canvas id="monthlyChart"></canvas></div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const analytics=<?= json_encode($analytics, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const draw=(id,data,label,type='bar')=>{ if(!data||!data.length)return; new Chart(document.getElementById(id),{type,data:{labels:data.map(x=>x.label),datasets:[{label,data:data.map(x=>Number(x.value)),backgroundColor:['#2457d6','#16a085','#f39c12','#8e44ad','#e74c3c','#3498db','#2ecc71','#d35400','#1abc9c','#9b59b6']}]},options:{responsive:true,plugins:{legend:{display:type==='doughnut'||type==='pie'}}}});};
draw('ngoChart',analytics.by_ngo,'Amount (₹)');
draw('districtChart',analytics.by_district,'Amount (₹)','doughnut');
draw('categoryChart',analytics.by_category,'Amount (₹)','pie');
draw('monthlyChart',analytics.monthly,'Amount (₹)','line');
</script>
<?php endif; ?>
</div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
