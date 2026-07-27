<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';
$pageTitle = 'Access Denied | ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
http_response_code(403);
$backLink = is_logged_in() ? dashboard_path(current_user()['role']) : 'auth/login.php';
?>
<main class="dashboard-page"><div class="container py-5"><section class="dashboard-welcome"><div><span class="eyebrow text-danger"><i class="bi bi-x-circle"></i> Access denied</span><h1>Permission required</h1><p class="lead">You do not have permission to view that page. Please return to your dashboard or sign in with the appropriate account.</p></div></section>
<section class="mt-4"><div class="card p-4 text-center"><p class="mb-4">If you believe this is an error, please sign in with the correct Business or Referrer account.</p><a class="btn btn-primary" href="<?= e(url($backLink)) ?>">Go back</a></div></section></div></main>
<?php require_once __DIR__ . '/includes/footer.php';
