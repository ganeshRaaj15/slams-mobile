<?= $this->extend('layouts/main_admin') ?>
<?= $this->section('content') ?>

<?php
$isEdit = isset($lab) && !empty($lab);
$title = $isEdit ? 'Edit Laboratory' : 'Add Laboratory';
$action = $isEdit ? '/admin/labs/update/' . $lab['id'] : '/admin/labs/store';
$errors = session()->getFlashdata('errors') ?? [];
?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= esc($title) ?></h1>
            <p class="text-muted mb-0">Define laboratory ownership, capacity, safety context, and availability notes.</p>
        </div>
        <a href="/admin/labs" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-2"></i>Back</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger border-0 shadow-sm">
            <ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('warning')): ?>
        <div class="alert alert-warning border-0 shadow-sm"><?= esc(session()->getFlashdata('warning')) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-1">Laboratory Record</h5></div>
                <div class="card-body">
                    <form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="row g-3">
                        <?= csrf_field() ?>
                        <div class="col-md-6">
                            <label class="form-label">Laboratory Name</label>
                            <input type="text" name="name" class="form-control" value="<?= esc(old('name', $lab['name'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Room</label>
                            <input type="text" name="room" class="form-control" value="<?= esc(old('room', $lab['room'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Capacity</label>
                            <input type="number" min="1" name="capacity" class="form-control" value="<?= esc(old('capacity', $lab['capacity'] ?? '')) ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Availability Note</label>
                            <input type="text" name="availability_note" class="form-control" value="<?= esc(old('availability_note', $lab['availability_note'] ?? '')) ?>" placeholder="Weekday access, booking restrictions, shared use note...">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4"><?= esc(old('description', $lab['description'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Safety Note</label>
                            <textarea name="safety_note" class="form-control" rows="3" placeholder="PPE rules, access warnings, required supervision... "><?= esc(old('safety_note', $lab['safety_note'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">PIC Name</label>
                            <input type="text" name="pic_name" class="form-control" value="<?= esc(old('pic_name', $lab['pic_name'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">PIC Email</label>
                            <input type="email" name="pic_email" class="form-control" value="<?= esc(old('pic_email', $lab['pic_email'] ?? '')) ?>">
                            <div class="form-text">Use the email address of an existing user with the PIC role so approvals and PIC dashboard scoping work correctly.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">PIC Phone</label>
                            <input type="text" name="pic_phone" class="form-control" value="<?= esc(old('pic_phone', $lab['pic_phone'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Laboratory Image</label>
                            <input type="file" name="image" class="form-control">
                            <?php if ($isEdit && !empty($lab['image_url'])): ?>
                                <div class="mt-3"><img src="<?= esc($lab['image_url']) ?>" alt="Laboratory image" style="width:180px;border-radius:12px;"></div>
                                <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_image" id="remove_image"><label class="form-check-label text-danger small" for="remove_image">Remove current image</label></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">PIC Image</label>
                            <input type="file" name="pic_image" class="form-control">
                            <?php if ($isEdit && !empty($lab['pic_image_url'])): ?>
                                <div class="mt-3"><img src="<?= esc($lab['pic_image_url']) ?>" alt="PIC image" style="width:120px;height:120px;object-fit:cover;border-radius:999px;"></div>
                                <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_pic_image" id="remove_pic_image"><label class="form-check-label text-danger small" for="remove_pic_image">Remove current PIC image</label></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2">
                            <a href="/admin/labs" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Laboratory' ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-1">Why This Matters</h6></div>
                <div class="card-body small text-muted">
                    Laboratory metadata supports booking decisions, maintenance escalation, and clearer ownership whenever equipment faults are reported.
                    PIC email is validated after save and should match a registered PIC user account.
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
