<?php if (auth()->loggedIn()): ?>
    <?php header('Location: ' . site_url('/dashboard')); exit; ?>
<?php endif; ?>

<?= $this->extend('layouts/main_user'); ?>
<?= $this->section('content'); ?>

<div class="login-page">
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">
                <i class="bi bi-building-gear"></i>
            </div>
            <h1 class="login-title">FKMP Smart Lab</h1>
            <p class="login-subtitle">Login to access the laboratory booking system.</p>
        </div>

        <?php if (session()->has('error')): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= session('error') ?>
            </div>
        <?php endif; ?>

        <?php if (session()->has('errors')): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php foreach ((array) session('errors') as $error): ?>
                    <div><?= esc($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (session()->has('success')): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                <?= session('success') ?>
            </div>
        <?php endif; ?>

        <form action="<?= url_to('login') ?>" method="post" class="login-form">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control form-control-lg" placeholder="Enter your email address" required autofocus>
                <small class="text-muted mt-1 d-block">Use your institutional email.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="passwordInput" class="form-control form-control-lg" placeholder="Enter your password" required>
                    <button type="button" class="toggle-password" id="togglePassword" aria-label="Show password">
                        <i class="bi bi-eye-slash"></i>
                    </button>
                </div>
            </div>

            <div class="login-options">
                <div class="form-check">
                    <input type="checkbox" name="remember" id="remember" class="form-check-input">
                    <label for="remember" class="form-check-label">Remember me</label>
                </div>
                <a href="<?= url_to('magic-link') ?>" class="magic-link">
                    <i class="bi bi-key me-1"></i>Forgot password?
                </a>
            </div>

            <button type="submit" class="login-btn">
                <i class="bi bi-box-arrow-in-right me-2"></i>
                Login to Account
            </button>
        </form>

        <div class="auth-footer">
            <p class="mb-2">Do not have an account yet?</p>
            <a href="<?= url_to('register') ?>" class="register-btn">
                <i class="bi bi-person-plus me-1"></i>
                Create New Account
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const togglePassword = document.getElementById("togglePassword");
    const passwordInput = document.getElementById("passwordInput");

    if (!togglePassword || !passwordInput) {
        return;
    }

    togglePassword.addEventListener("click", function () {
        const isHidden = passwordInput.type === "password";
        passwordInput.type = isHidden ? "text" : "password";
        togglePassword.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
        togglePassword.querySelector("i").classList.toggle("bi-eye", isHidden);
        togglePassword.querySelector("i").classList.toggle("bi-eye-slash", !isHidden);
        passwordInput.focus();
    });
});
</script>

<?= $this->endSection(); ?>
