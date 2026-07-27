<footer class="site-footer">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-5"><a class="footer-brand" href="<?= e(url()) ?>">Refer<span>.</span></a><p class="mt-3 mb-0">A trusted place to create meaningful connections, reward successful introductions, and build growth together.</p></div>
            <div class="col-6 col-lg-3"><h2>Platform</h2><a href="<?= e(url('#how-it-works')) ?>">How it works</a><a href="<?= e(url('#features')) ?>">Features</a><a href="<?= e(url('#faq')) ?>">FAQs</a></div>
            <div class="col-6 col-lg-3"><h2>Account</h2><a href="<?= e(url('auth/register.php')) ?>">Create account</a><a href="<?= e(url('auth/login.php')) ?>">Sign in</a></div>
        </div>
        <div class="footer-bottom mt-5 pt-4"><span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?></span><span>Built for trusted connections.</span></div>
    </div>
</footer>
<div class="page-loader" aria-hidden="true"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading</span></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<script src="<?= e(url('assets/js/pincode-lookup.js')) ?>"></script>
</body>
</html>
