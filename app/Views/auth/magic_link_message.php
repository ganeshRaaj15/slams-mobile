<?= $this->extend('layouts/main_user'); ?>
<?= $this->section('content'); ?>




<div class="auth-wrapper">
    <div class="auth-card">

        <h3 class="auth-title mb-3">Check Your Email</h3>
        <p class="text-secondary small mb-4">
            If an account matches what you entered, we sent a secure one-time sign-in link to the registered email address.
            The link expires in <?= esc((string) ceil(setting('Auth.magicLinkLifetime') / MINUTE)) ?> minutes.
        </p>

        <i class="bi bi-envelope-check auth-icon"></i>

        <p class="mt-4 small">
            <a href="<?= url_to('login') ?>">Back to login</a>
        </p>

    </div>
</div>

<?= $this->endSection(); ?>






