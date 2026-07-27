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

// Handle donation submission
$values = ['cause_name' => '', 'donation_amount' => '', 'message' => '', 'ngo_id' => ''];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        [$values, $errors] = validate_donation($_POST, $wallet);
        if (!$errors) {
            create_donation((int) $user['id'], $values);
            set_flash('success', 'Donation completed successfully. Thank you for your generosity!');
            redirect('referrer/donations.php');
        }
    } catch (Throwable $exception) {
        app_log('Donation failed: ' . $exception->getMessage());
        $errors['general'] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Donation could not be completed.';
    }
}

// Filters
$search = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 100);
$district = trim((string) ($_GET['district'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$selectedNgo = filter_var($_GET['ngo_id'] ?? $_POST['ngo_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

$ngos = get_ngos_filtered($search, $district, $category);
$districts = ngo_districts();
$categories = ngo_categories();
$ngoDetail = $selectedNgo ? get_ngo($selectedNgo) : null;

$pageTitle = 'Donate | ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<main class="opportunity-page"><div class="container py-5">
<div class="opportunity-hero"><div><span class="eyebrow"><i class="bi bi-heart"></i> NGO Directory</span><h1>Donate from Wallet</h1><p>Browse NGOs across Dakshina Kannada &amp; Udupi. Choose an NGO and make a difference.</p></div><div class="d-flex gap-2"><a class="btn btn-light border" href="<?= e(url('referrer/wallet.php')) ?>">Wallet: <?= e(opportunity_money($wallet['current_balance'])) ?></a><a class="btn btn-light border" href="<?= e(url('referrer/donations.php')) ?>">History</a></div></div>

<?php if ($ngoDetail && !$errors): ?>
<!-- DONATION FORM for selected NGO -->
<section class="opportunity-list-card mt-4"><div class="list-card-heading"><div><h2>Donate to <?= e($ngoDetail['name']) ?></h2><p><?= e($ngoDetail['category']) ?> · <?= e($ngoDetail['city']) ?>, <?= e($ngoDetail['district']) ?></p></div><a class="btn btn-light border" href="<?= e(url('referrer/donate.php')) ?>"><i class="bi bi-arrow-left"></i> Back to NGOs</a></div>
<?php if (isset($errors['general'])): ?><div class="alert alert-danger mt-3"><?= e($errors['general']) ?></div><?php endif; ?>
<form method="post" class="needs-validation mt-3" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="ngo_id" value="<?= (int) $ngoDetail['id'] ?>"><input type="hidden" name="cause_name" value="<?= e($ngoDetail['category']) ?>">
<div class="row g-3"><div class="col-md-6"><label class="form-label" for="donation_amount">Donation Amount</label><div class="input-group"><span class="input-group-text">₹</span><input class="form-control <?= isset($errors['donation_amount']) ? 'is-invalid' : '' ?>" id="donation_amount" name="donation_amount" type="number" min="1" max="<?= e((string) $wallet['current_balance']) ?>" step="0.01" value="<?= e($values['donation_amount']) ?>" required></div><div class="form-text">Available balance: <?= e(opportunity_money($wallet['current_balance'])) ?></div><?php if (isset($errors['donation_amount'])): ?><div class="text-danger small mt-1"><?= e($errors['donation_amount']) ?></div><?php endif; ?></div>
<div class="col-md-6"><label class="form-label">NGO Details</label><dl class="detail-list mb-0"><div><dt>Category</dt><dd><?= e($ngoDetail['category']) ?></dd></div><div><dt>Location</dt><dd><?= e($ngoDetail['city']) ?>, <?= e($ngoDetail['district']) ?></dd></div><?php if ($ngoDetail['website']): ?><div><dt>Website</dt><dd><a href="<?= e($ngoDetail['website']) ?>" target="_blank" rel="noopener"><?= e($ngoDetail['website']) ?></a></dd></div><?php endif; ?></dl></div>
<div class="col-12"><label class="form-label" for="message">Message <span class="text-muted fw-normal">(optional)</span></label><textarea class="form-control" id="message" name="message" rows="3" maxlength="500"><?= e($values['message']) ?></textarea></div></div>
<button class="btn btn-primary w-100 mt-4 py-3" type="submit" <?= (float) $wallet['current_balance'] <= 0 ? 'disabled' : '' ?>>Confirm Donation <i class="bi bi-heart-fill ms-1"></i></button></form></section>

<?php else: ?>
<!-- NGO DIRECTORY BROWSE -->
<section class="opportunity-list-card mt-4"><div class="list-card-heading"><div><h2>NGO Directory</h2><p><?= count($ngos) ?> NGO<?= count($ngos) !== 1 ? 's' : '' ?> available</p></div></div>
<form class="row g-3 filter-panel" method="get"><div class="col-lg-4"><input class="form-control" name="search" value="<?= e($search) ?>" placeholder="Search NGO name or city" data-instant-search></div><div class="col-sm-6 col-lg-3"><select class="form-select" name="district"><option value="">All Districts</option><?php foreach ($districts as $d): ?><option value="<?= e($d) ?>" <?= $district === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?></select></div><div class="col-sm-6 col-lg-3"><select class="form-select" name="category"><option value="">All Categories</option><?php foreach ($categories as $c): ?><option value="<?= e($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div><div class="col-lg-2 d-flex gap-2"><button class="btn btn-outline-primary flex-grow-1">Filter</button><a class="btn btn-light border" href="<?= e(url('referrer/donate.php')) ?>">Clear</a></div></form>

<?php if (!$ngos): ?><div class="empty-state mt-4"><span><i class="bi bi-building"></i></span><h3>No NGOs found</h3><p>Try changing your search or filters.</p></div>
<?php else: ?><div class="row g-4 mt-1"><?php foreach ($ngos as $ngo): ?>
<div class="col-md-6 col-xl-4"><article class="detail-card h-100 d-flex flex-column">
<div class="d-flex align-items-center gap-2 mb-3"><?php if ($ngo['logo']): ?><img src="<?= e(url($ngo['logo'])) ?>" alt="" class="rounded" style="height:38px;width:38px;object-fit:cover"><?php else: ?><span class="brand-mark"><i class="bi bi-building-check"></i></span><?php endif; ?><div><strong class="d-block text-dark"><?= e($ngo['name']) ?></strong><small class="text-muted"><?= e($ngo['city']) ?>, <?= e($ngo['district']) ?></small></div></div>
<span class="eyebrow mb-2"><?= e($ngo['category']) ?></span>
<p class="small flex-grow-1"><?= e(mb_strimwidth($ngo['description'] ?: 'Supporting the community.', 0, 120, '…')) ?></p>
<?php if ($ngo['website']): ?><p class="small mb-2"><i class="bi bi-globe"></i> <a href="<?= e($ngo['website']) ?>" target="_blank" rel="noopener"><?= e(parse_url($ngo['website'], PHP_URL_HOST) ?: $ngo['website']) ?></a></p><?php endif; ?>
<a class="btn btn-primary w-100 mt-auto" href="<?= e(url('referrer/donate.php?ngo_id=' . $ngo['id'])) ?>"><i class="bi bi-heart"></i> Select &amp; Donate</a>
</article></div>
<?php endforeach; ?></div><?php endif; ?>
</section>
<?php endif; ?>
</div></main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
