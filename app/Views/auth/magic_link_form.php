<?= $this->extend('layouts/main_user'); ?>
<?= $this->section('content'); ?>




<div class="auth-wrapper">
    <div class="auth-card">

        <h3 class="auth-title mb-1">Find Your Account</h3>
        <p class="text-center text-secondary small mb-4">
            Enter your email or username. We will send a secure one-time sign-in link to the email on your account.
        </p>

        <?php if (session()->has('error')): ?>
            <div class="alert alert-danger"><?= esc(session('error')) ?></div>
        <?php endif; ?>

        <?php if (session()->has('errors')): ?>
            <div class="alert alert-danger">
                <?php foreach ((array) session('errors') as $error): ?>
                    <div><?= esc($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="<?= url_to('magic-link') ?>" method="post">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Email or Username</label>
                <input type="text" name="account" class="form-control form-control-lg"
                       value="<?= esc(old('account')) ?>"
                       placeholder="you@example.com" autocomplete="username" required>
            </div>

            <button type="submit" class="btn btn-fkmp-auth mb-3">
                <i class="bi bi-envelope-paper me-1"></i> Send Secure Link
            </button>

            <p class="text-center small">
                <a href="<?= url_to('login') ?>">Back to login</a>
            </p>
        </form>
    </div>
</div>

<?= $this->endSection(); ?>






