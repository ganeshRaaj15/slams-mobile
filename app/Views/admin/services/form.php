<?= $this->extend('layouts/main_admin') ?>
<?= $this->section('content') ?>

<?php
$isEdit = ($mode ?? 'create') === 'edit';
$service = $service ?? [];
$errors = session()->getFlashdata('errors') ?? [];
$selectedRequirements = $selectedRequirements ?? [];

$oldAssetIds = old('requirement_asset_id');
$oldQuantities = old('requirement_quantity');
if (is_array($oldAssetIds) && is_array($oldQuantities)) {
    $selectedRequirements = [];
    foreach ($oldAssetIds as $index => $assetIdRaw) {
        $assetId = (int) $assetIdRaw;
        $quantity = max((int) ($oldQuantities[$index] ?? 0), 0);
        if ($assetId > 0 && $quantity > 0) {
            $selectedRequirements[$assetId] = $quantity;
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= $isEdit ? 'Edit Service Bundle' : 'Create Service Bundle' ?></h1>
            <p class="text-muted mb-0">Applicants book the service, and SLAMS checks every linked asset in the bundle before allowing the slot.</p>
        </div>
        <a href="/admin/services" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-2"></i>Back</a>
    </div>

    <?php if (! empty($errors)): ?>
        <div class="alert alert-danger border-0 shadow-sm">
            <div class="fw-semibold mb-2">Please fix the following:</div>
            <ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger border-0 shadow-sm"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <form action="<?= $isEdit ? '/admin/services/update/' . (int) ($service['id'] ?? 0) : '/admin/services/store' ?>" method="post" class="row g-4">
        <?= csrf_field() ?>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-1">Service Details</h5></div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Laboratory</label>
                        <select name="laboratory_id" id="serviceLabId" class="form-select" required>
                            <option value="">Select laboratory</option>
                            <?php foreach (($labs ?? []) as $lab): ?>
                                <option value="<?= esc($lab['id']) ?>" <?= (string) old('laboratory_id', $service['laboratory_id'] ?? '') === (string) $lab['id'] ? 'selected' : '' ?>>
                                    <?= esc($lab['name']) ?><?= ! empty($lab['room']) ? ' - ' . esc($lab['room']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Service Name</label>
                        <input type="text" name="service_name" class="form-control" value="<?= esc(old('service_name', $service['service_name'] ?? '')) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Field of Work</label>
                        <input type="text" name="field_name" class="form-control" value="<?= esc(old('field_name', $service['field_name'] ?? '')) ?>" placeholder="Testing, fabrication, imaging, analysis...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Calibration Status</label>
                        <select name="calibration_status" class="form-select">
                            <?php $calibration = old('calibration_status', $service['calibration_status'] ?? 'unknown'); ?>
                            <?php foreach (['valid' => 'Valid', 'expired' => 'Expired', 'unknown' => 'Unknown'] as $value => $label): ?>
                                <option value="<?= esc($value) ?>" <?= $calibration === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Availability</label>
                        <select name="is_active" class="form-select">
                            <?php $active = (string) old('is_active', isset($service['is_active']) ? (string) (int) $service['is_active'] : '1'); ?>
                            <option value="1" <?= $active === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $active === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Acceptance Criteria</label>
                        <textarea name="acceptance_criteria" class="form-control" rows="4"><?= esc(old('acceptance_criteria', $service['acceptance_criteria'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Service Notes</label>
                        <textarea name="service_notes" class="form-control" rows="4"><?= esc(old('service_notes', $service['service_notes'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-1">Bundle Requirements</h5>
                    <div class="small text-muted">Enter the quantity required for each asset that belongs to the selected laboratory. Leave `0` to exclude an asset from the service.</div>
                </div>
                <div class="card-body">
                    <div id="serviceAssetRows" class="d-flex flex-column gap-3">
                        <?php foreach (($labAssets ?? []) as $asset): ?>
                            <?php $selectedQty = (int) ($selectedRequirements[(int) $asset['id']] ?? 0); ?>
                            <div class="border rounded-3 p-3 service-asset-row" data-lab-id="<?= esc((string) ($asset['lab_id'] ?? 0)) ?>">
                                <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                                    <div>
                                        <div class="fw-semibold"><?= esc($asset['name'] ?? '-') ?></div>
                                        <div class="small text-muted"><?= esc($asset['asset_code'] ?? '-') ?><?= ! empty($asset['model']) ? ' | ' . esc($asset['model']) : '' ?></div>
                                        <div class="small text-muted">Status: <?= esc($asset['status'] ?? '-') ?> | Available <?= esc((int) ($asset['quantity'] ?? 0)) ?>/<?= esc((int) ($asset['total_quantity'] ?? 0)) ?></div>
                                    </div>
                                    <div style="min-width: 180px;">
                                        <input type="hidden" name="requirement_asset_id[]" value="<?= esc((string) ($asset['id'] ?? 0)) ?>">
                                        <label class="form-label small">Quantity Required</label>
                                        <input
                                            type="number"
                                            min="0"
                                            max="<?= esc((string) max((int) ($asset['total_quantity'] ?? 0), 1)) ?>"
                                            name="requirement_quantity[]"
                                            class="form-control"
                                            value="<?= esc((string) $selectedQty) ?>"
                                        >
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="/admin/services" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Service Bundle' : 'Create Service Bundle' ?></button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const labField = document.getElementById('serviceLabId');
    const rows = Array.from(document.querySelectorAll('.service-asset-row'));

    function updateAssetVisibility() {
        const labId = labField?.value || '';
        rows.forEach((row) => {
            const matches = !labId || row.dataset.labId === labId;
            row.style.display = matches ? '' : 'none';
            if (!matches) {
                const qtyField = row.querySelector('input[name="requirement_quantity[]"]');
                if (qtyField) qtyField.value = '0';
            }
        });
    }

    labField?.addEventListener('change', updateAssetVisibility);
    updateAssetVisibility();
});
</script>

<?= $this->endSection() ?>
