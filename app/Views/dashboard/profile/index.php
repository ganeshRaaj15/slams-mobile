<?= $this->extend($layout ?? 'layouts/main_user') ?>
<?= $this->section('content') ?>

<div class="container">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">My Profile</h2>
            <p class="text-muted mb-0">Update your account details and contact information.</p>
        </div>
        <?php if (!empty($backUrl ?? null)): ?>
            <a href="<?= esc($backUrl) ?>" class="btn btn-outline-secondary mt-3 mt-md-0">
                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
            </a>
        <?php endif; ?>
    </div>

    <?php if (session()->getFlashdata('message')): ?>
        <div class="alert alert-success">
            <?= esc(session()->getFlashdata('message')) ?>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('errors')): ?>
        <div class="alert alert-danger">
            <div class="fw-semibold mb-2">Please fix the following:</div>
            <ul class="mb-0">
                <?php foreach (session()->getFlashdata('errors') as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-4">
                <div class="text-center">
                    <?php
                    $photo = $user->profile_photo ?? null;
                    $photoSrc = $photo ? '/' . ltrim($photo, '/') : '/images/assets/placeholder_asset.png';
                    ?>
                    <div class="mb-3">
                        <img src="<?= esc($photoSrc) ?>" alt="Profile photo" class="rounded-circle shadow-sm" style="width: 120px; height: 120px; object-fit: cover;">
                    </div>
                    <h5 class="fw-semibold mb-1"><?= esc($user->full_name ?? $user->username ?? 'User') ?></h5>
                    <p class="text-muted small mb-0"><?= esc($email ?? '') ?></p>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card p-4">
                <form action="/dashboard/profile/update" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?= esc($user->username ?? '') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= esc($email ?? '') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?= esc($user->full_name ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= esc($user->phone ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Faculty</label>
                            <select name="faculty_id" class="form-select">
                                <option value="">Select faculty</option>
                                <?php foreach ($faculties as $faculty): ?>
                                    <option value="<?= esc($faculty['id']) ?>" <?= ($user->faculty_id ?? null) == $faculty['id'] ? 'selected' : '' ?>>
                                        <?= esc($faculty['name_en'] ?? $faculty['name'] ?? 'Faculty') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" name="profile_photo" class="form-control" accept="image/png,image/jpeg,image/webp">
                            <div class="form-text">Optional. JPG, PNG, or WEBP.</div>
                        </div>

                        <div class="col-12 mt-2">
                            <h6 class="fw-semibold mb-2">Change Password</h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">New Password</label>
                            <input type="password" name="password" class="form-control" autocomplete="new-password">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="password_confirm" class="form-control" autocomplete="new-password">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

