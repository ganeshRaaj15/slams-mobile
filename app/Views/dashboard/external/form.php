<?= $this->extend('layouts/main_user') ?>

<?= $this->section('content') ?>

<?php
$mode = $mode ?? 'create';
$requestRecord = $requestRecord ?? [];
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? '/dashboard/external/request/update/' . (int) ($requestRecord['id'] ?? 0) : '/dashboard/external/request/store';
$requestModel = $requestModel ?? null;
$currentStatus = (string) ($requestRecord['status'] ?? 'submitted');
?>

<div class="dashboard-header">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-0"><?= $isEdit ? 'Update External Request' : 'Request Lab Access' ?></h2>
            <p class="text-muted small mb-0">This form creates a request for review. It does not directly reserve the laboratory.</p>
        </div>
        <a href="/dashboard/external" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Requests
        </a>
    </div>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<?php if ($isEdit && !empty($requestRecord['review_notes'])): ?>
    <div class="alert alert-warning">
        <strong>Latest review note:</strong><br>
        <?= nl2br(esc($requestRecord['review_notes'])) ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form action="<?= esc($actionUrl) ?>" method="post" class="row g-4">
            <?= csrf_field() ?>

            <div class="col-md-6">
                <label class="form-label">Laboratory *</label>
                <select name="lab_id" class="form-select" required>
                    <option value="">Select a laboratory</option>
                    <?php foreach (($labs ?? []) as $lab): ?>
                        <option value="<?= esc($lab['id']) ?>" <?= (string) old('lab_id', $requestRecord['lab_id'] ?? '') === (string) $lab['id'] ? 'selected' : '' ?>>
                            <?= esc($lab['name']) ?><?= !empty($lab['room']) ? ' (' . esc($lab['room']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Current Status</label>
                <input type="text" class="form-control" value="<?= esc($requestModel ? $requestModel->statusLabel($currentStatus) : ucfirst($currentStatus)) ?>" readonly>
            </div>

            <div class="col-md-6">
                <label class="form-label">Organization / Institution *</label>
                <input type="text" name="organization_name" class="form-control" maxlength="255" value="<?= esc(old('organization_name', $requestRecord['organization_name'] ?? '')) ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Contact Name *</label>
                <input type="text" name="contact_name" class="form-control" maxlength="255" value="<?= esc(old('contact_name', $requestRecord['contact_name'] ?? '')) ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Contact Email *</label>
                <input type="email" name="contact_email" class="form-control" maxlength="255" value="<?= esc(old('contact_email', $requestRecord['contact_email'] ?? '')) ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Contact Phone *</label>
                <input type="text" name="contact_phone" class="form-control" maxlength="50" value="<?= esc(old('contact_phone', $requestRecord['contact_phone'] ?? '')) ?>" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Participant Count *</label>
                <input type="number" name="participant_count" class="form-control" min="1" value="<?= esc(old('participant_count', $requestRecord['participant_count'] ?? 1)) ?>" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Preferred Date *</label>
                <input type="date" name="preferred_date" class="form-control" min="<?= esc(date('Y-m-d')) ?>" value="<?= esc(old('preferred_date', $requestRecord['preferred_date'] ?? '')) ?>" required>
            </div>

            <div class="col-md-2">
                <label class="form-label">Start Time</label>
                <input type="time" name="preferred_start_time" class="form-control" value="<?= esc(old('preferred_start_time', $requestRecord['preferred_start_time'] ?? '')) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">End Time</label>
                <input type="time" name="preferred_end_time" class="form-control" value="<?= esc(old('preferred_end_time', $requestRecord['preferred_end_time'] ?? '')) ?>">
            </div>

            <div class="col-12">
                <label class="form-label">Purpose of Use *</label>
                <textarea name="purpose" class="form-control" rows="5" required><?= esc(old('purpose', $requestRecord['purpose'] ?? '')) ?></textarea>
                <div class="form-text">Explain what you need to do in the laboratory, why the lab is required, and any timing constraints.</div>
            </div>

            <div class="col-12">
                <label class="form-label">Equipment / Setup Notes</label>
                <textarea name="equipment_notes" class="form-control" rows="4"><?= esc(old('equipment_notes', $requestRecord['equipment_notes'] ?? '')) ?></textarea>
                <div class="form-text">List the equipment, workstation setup, or environmental requirements the PIC should know.</div>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2">
                <a href="/dashboard/external" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update Request' : 'Submit Request' ?></button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
