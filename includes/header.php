<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
if ($user) {
    require_once __DIR__ . '/notifications.php';
    $unreadNotifications = unread_notification_count((int) $user['id']);
    $latestNotifications = latest_notifications_for_user((int) $user['id']);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A trusted platform for business referrals, commissions, rewards, and giving.">
    <title><?= e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/business-profile.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/business-category.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/referrer-profile.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/reports.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/notifications.css')) ?>">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light sticky-top site-nav">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url()) ?>"><span class="brand-mark"><i class="bi bi-link-45deg"></i></span><span><?= e(APP_NAME) ?></span></a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Open navigation"><i class="bi bi-list fs-3"></i></button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <?php if (!$user): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('#how-it-works')) ?>">How it works</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('#features')) ?>">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('#faq')) ?>">FAQs</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('auth/login.php')) ?>">Sign in</a></li>
                    <li class="nav-item"><a class="btn btn-primary px-3" href="<?= e(url('auth/register.php')) ?>">Get started <i class="bi bi-arrow-right ms-1"></i></a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url(dashboard_path($user['role']))) ?>">Dashboard</a></li>
                    <?php if (canonical_role($user['role']) === 'BUSINESS'): ?><li class="nav-item"><a class="nav-link" href="<?= e(url('business/opportunities.php')) ?>">Opportunities</a></li><?php endif; ?>
                    <?php if (canonical_role($user['role']) === 'BUSINESS'): ?><li class="nav-item"><a class="nav-link" href="<?= e(url('business/referrals.php')) ?>">Referrals</a></li><?php endif; ?>
                    <?php if (canonical_role($user['role']) === 'BUSINESS'): ?><li class="nav-item"><a class="nav-link" href="<?= e(url('business/reports.php')) ?>">Reports</a></li><?php endif; ?>
                    <?php if (canonical_role($user['role']) === 'REFERRER'): ?><li class="nav-item"><a class="nav-link" href="<?= e(url('referrer/opportunities.php')) ?>">Opportunities</a></li><li class="nav-item"><a class="nav-link" href="<?= e(url('referrer/referrals.php')) ?>">My referrals</a></li><li class="nav-item"><a class="nav-link" href="<?= e(url('referrer/wallet.php')) ?>">Wallet</a></li><li class="nav-item"><a class="nav-link" href="<?= e(url('referrer/donate.php')) ?>">Donate</a></li><?php endif; ?>
                    <li class="nav-item dropdown notification-menu"><a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications"><i class="bi bi-bell fs-5"></i><?php if ($unreadNotifications > 0): ?><span class="notification-badge"><?= $unreadNotifications > 99 ? '99+' : (int) $unreadNotifications ?></span><?php endif; ?></a><div class="dropdown-menu dropdown-menu-end notification-dropdown p-0"><div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom"><strong>Notifications</strong><span class="small text-muted"><?= (int) $unreadNotifications ?> unread</span></div><?php if (!$latestNotifications): ?><p class="text-muted small text-center mb-0 px-3 py-4">No notifications yet.</p><?php else: ?><?php foreach ($latestNotifications as $notification): ?><a class="dropdown-item notification-preview <?= (int) $notification['is_read'] === 0 ? 'is-unread' : '' ?>" href="<?= e(url('notifications.php')) ?>"><span class="notification-dot" aria-hidden="true"></span><span><strong><?= e($notification['title']) ?></strong><small><?= e($notification['message']) ?></small></span></a><?php endforeach; ?><?php endif; ?><a class="dropdown-item text-center fw-semibold border-top py-2" href="<?= e(url('notifications.php')) ?>">View All</a></div></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('activity_logs.php')) ?>">Activity</a></li>
                    <li class="nav-item"><a class="btn btn-outline-primary px-3" href="<?= e(url('auth/logout.php')) ?>">Sign out</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<?php $flash = pull_flash(); if ($flash): ?>
<div class="toast-container position-fixed top-0 end-0 p-3"><div class="toast align-items-center border-0 text-bg-<?= e($flash['type']) ?>" role="alert" data-bs-delay="5000"><div class="d-flex"><div class="toast-body"><?= e($flash['message']) ?></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div></div></div>
<?php endif; ?>
