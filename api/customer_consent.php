<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/customer_referrals.php';
require_once __DIR__ . '/../includes/referral_opportunities.php';
require_once __DIR__ . '/../includes/notifications.php';

$token = trim((string) ($_GET['token'] ?? ''));
$action = trim((string) ($_GET['action'] ?? ''));

if ($token === '' || !in_array($action, ['approve', 'decline'], true)) {
    $pageTitle = 'Invalid Link | ' . APP_NAME;
    require_once __DIR__ . '/../includes/header.php';
    echo '<main class="auth-page"><div class="container"><div class="row justify-content-center"><div class="col-md-6"><div class="auth-card text-center"><i class="bi bi-exclamation-triangle fs-1 text-warning"></i><h1 class="mt-3">Invalid or Expired Link</h1><p>This approval link is invalid, has expired, or has already been used.</p></div></div></div></div></main>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$referral = validate_approval_token($token);
if (!$referral) {
    $pageTitle = 'Link Expired | ' . APP_NAME;
    require_once __DIR__ . '/../includes/header.php';
    echo '<main class="auth-page"><div class="container"><div class="row justify-content-center"><div class="col-md-6"><div class="auth-card text-center"><i class="bi bi-clock-history fs-1 text-muted"></i><h1 class="mt-3">Link Expired</h1><p>This approval link has expired or has already been used. No action was taken.</p></div></div></div></div></main>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if ($action === 'approve') {
    $success = approve_referral_by_customer($token);
    $pageTitle = 'Contact Sharing Approved | ' . APP_NAME;
    require_once __DIR__ . '/../includes/header.php';
    if ($success) {
        echo '<main class="auth-page"><div class="container"><div class="row justify-content-center"><div class="col-md-6"><div class="auth-card text-center"><i class="bi bi-check-circle-fill fs-1 text-success"></i><h1 class="mt-3">Thank You!</h1><p>You have approved sharing your contact details with <strong>' . e($referral['business_name']) . '</strong> for the opportunity: <strong>' . e($referral['opportunity_title']) . '</strong>.</p><p class="text-muted">Referral ID: ' . e($referral['referral_code']) . '</p></div></div></div></div></main>';
    } else {
        echo '<main class="auth-page"><div class="container"><div class="row justify-content-center"><div class="col-md-6"><div class="auth-card text-center"><i class="bi bi-exclamation-triangle fs-1 text-warning"></i><h1 class="mt-3">Could Not Process</h1><p>We could not process your approval. The link may have already been used.</p></div></div></div></div></main>';
    }
} else {
    $success = decline_referral_by_customer($token);
    $pageTitle = 'Contact Sharing Declined | ' . APP_NAME;
    require_once __DIR__ . '/../includes/header.php';
    if ($success) {
        echo '<main class="auth-page"><div class="container"><div class="row justify-content-center"><div class="col-md-6"><div class="auth-card text-center"><i class="bi bi-shield-check fs-1 text-primary"></i><h1 class="mt-3">Declined</h1><p>You have declined sharing your contact details. Your information will remain private.</p><p class="text-muted">Referral ID: ' . e($referral['referral_code']) . '</p></div></div></div></div></main>';
    } else {
        echo '<main class="auth-page"><div class="container"><div class="row justify-content-center"><div class="col-md-6"><div class="auth-card text-center"><i class="bi bi-exclamation-triangle fs-1 text-warning"></i><h1 class="mt-3">Could Not Process</h1><p>We could not process your response. The link may have already been used.</p></div></div></div></div></main>';
    }
}
require_once __DIR__ . '/../includes/footer.php';
