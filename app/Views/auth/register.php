<?= $this->extend('layouts/main_user'); ?>
<?= $this->section('content'); ?>




<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-logo">
            <i class="bi bi-person-plus"></i>
        </div>
        <h3 class="auth-title mb-1">FKMP Smart Lab</h3>
        <p class="auth-subtitle">Create your account to start booking equipment</p>

        <!-- SHOW ERRORS -->
        <?php if (session()->has('errors')): ?>
            <div class="alert alert-danger">
                <?php foreach (session('errors') as $error): ?>
                    <?= esc($error) ?><br>
                <?php endforeach ?>
            </div>
        <?php endif; ?>

        <form action="<?= url_to('register') ?>" method="post">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Username</label>
                <input type="text" name="username"
                       class="form-control form-control-lg"
                       placeholder="Choose a username" required>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Email Address</label>
                <input type="email" name="email"
                       class="form-control form-control-lg"
                       placeholder="you@example.com" required>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password"
                           class="form-control form-control-lg"
                           placeholder="Create a password" required>
                    <button type="button" class="toggle-password" id="togglePassword" aria-label="Show password">
                        <i class="bi bi-eye-slash"></i>
                    </button>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Confirm Password</label>
                <div class="password-wrapper">
                    <input type="password" id="pass_confirm" name="password_confirm"
                           class="form-control form-control-lg"
                           placeholder="Re-enter password" required>
                    <button type="button" class="toggle-password" id="togglePasswordConfirm" aria-label="Show password">
                        <i class="bi bi-eye-slash"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-fkmp-auth mb-3">
                <i class="bi bi-person-plus-fill me-1"></i> Create Account
            </button>

            <p class="auth-footer">
                Already have an account?
                <a href="<?= url_to(controller: 'login') ?>">Login here</a>
            </p>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    function setupPasswordToggle(toggleId, inputId) {
        const toggle = document.getElementById(toggleId);
        const input = document.getElementById(inputId);

        if (!toggle || !input) {
            return;
        }

        toggle.addEventListener("click", function () {
            const isHidden = input.type === "password";
            input.type = isHidden ? "text" : "password";
            toggle.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
            toggle.querySelector("i").classList.toggle("bi-eye", isHidden);
            toggle.querySelector("i").classList.toggle("bi-eye-slash", !isHidden);
            input.focus();
        });
    }

    setupPasswordToggle("togglePassword", "password");
    setupPasswordToggle("togglePasswordConfirm", "pass_confirm");
});
</script>

<?= $this->endSection(); ?>






