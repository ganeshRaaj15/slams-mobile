<?= $this->extend('layouts/main_admin') ?>
<?= $this->section('content') ?>

<?php
$isEdit = $mode === 'edit';
$title = $isEdit ? 'Edit Asset' : 'Add Asset';
$action = $isEdit ? "/admin/assets/update/{$asset['id']}" : '/admin/assets/store';
$errors = session()->getFlashdata('errors') ?? [];
$availableUnits = (int) ($asset['quantity'] ?? ($asset['total_quantity'] ?? 1));
$totalUnits = (int) ($asset['total_quantity'] ?? $availableUnits ?: 1);
$maintenanceUnits = max($totalUnits - $availableUnits, 0);
$intelligence = $intelligence ?? [
    'risk_percent' => 0,
    'risk_band' => 'low',
    'decision_label' => 'Normal monitoring',
    'decision_priority' => 'low',
    'reasons' => [],
    'next_due_at' => '',
    'bookings_last_30d' => 0,
    'bookings_last_90d' => 0,
    'booking_units_last_90d' => 0,
    'days_since_last_booking' => 0,
    'planned_gap_delta' => 0,
];
?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= esc($title) ?></h1>
            <p class="text-muted mb-0">Maintain complete equipment records while letting the maintenance workflow control live availability.</p>
        </div>
        <a href="/admin/assets" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-2"></i>Back</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger border-0 shadow-sm">
            <div class="fw-semibold mb-2">Please fix the following:</div>
            <ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-1">Asset Record</h5></div>
                <div class="card-body">
                    <form action="<?= $action ?>" method="post" enctype="multipart/form-data" class="row g-3">
                        <?= csrf_field() ?>
                        <div class="col-md-4">
                            <label class="form-label">Asset Code</label>
                            <input type="text" name="asset_code" class="form-control" value="<?= esc(old('asset_code', $asset['asset_code'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Asset Name</label>
                            <input type="text" name="name" class="form-control" value="<?= esc(old('name', $asset['name'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Laboratory</label>
                            <select name="lab_id" class="form-select" required>
                                <option value="">Select laboratory</option>
                                <?php foreach ($labs as $lab): ?>
                                    <option value="<?= esc($lab['id']) ?>" <?= (string) old('lab_id', (string) ($asset['lab_id'] ?? '')) === (string) $lab['id'] ? 'selected' : '' ?>><?= esc($lab['name']) ?><?= !empty($lab['room']) ? ' - ' . esc($lab['room']) : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" class="form-control" value="<?= esc(old('category', $asset['category'] ?? '')) ?>" placeholder="Microscope, projector, workstation...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand" class="form-control" value="<?= esc(old('brand', $asset['brand'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Model</label>
                            <input type="text" name="model" class="form-control" value="<?= esc(old('model', $asset['model'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Serial Number</label>
                            <input type="text" name="serial_number" class="form-control" value="<?= esc(old('serial_number', $asset['serial_number'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Total Quantity</label>
                            <input type="number" min="1" name="total_quantity" class="form-control" value="<?= esc(old('total_quantity', $asset['total_quantity'] ?? 1)) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Currently Available</label>
                            <input type="text" class="form-control" value="<?= esc($availableUnits) ?> unit(s)" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Under Maintenance</label>
                            <input type="text" class="form-control" value="<?= esc($maintenanceUnits) ?> unit(s)" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">System Status</label>
                            <input type="text" class="form-control text-capitalize" value="<?= esc($asset['status'] ?? 'available') ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Purchase Date</label>
                            <input type="date" name="purchase_date" class="form-control" value="<?= esc(old('purchase_date', $asset['purchase_date'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Availability Source</label>
                            <input type="text" class="form-control" value="Managed automatically by maintenance records" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Location / Placement Note</label>
                            <input type="text" name="location_note" class="form-control" value="<?= esc(old('location_note', $asset['location_note'] ?? '')) ?>" placeholder="Bench 3, cabinet A, front station...">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Technical Specifications</label>
                            <textarea name="specifications" class="form-control" rows="5" placeholder="Capacity, voltage, supported software, accessories, calibration notes..."><?= esc(old('specifications', $asset['specifications'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Asset Image</label>
                            <input type="file" name="image" class="form-control">
                            <?php if ($isEdit && !empty($asset['image'])): ?>
                                <div class="mt-3"><img src="<?= esc(base_url($asset['image'])) ?>" alt="Asset image" style="width:110px;height:110px;object-fit:cover;border-radius:12px;"></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2">
                            <a href="/admin/assets" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Asset' ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-1">Workflow Notes</h6></div>
                <div class="card-body small text-muted">
                    Use a stable asset code and serial number so maintenance records, issue reports, and booking restrictions all point to the same equipment record. Asset status is now system-managed from maintenance activity rather than edited manually.
                </div>
            </div>

            <?php if ($isEdit): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white"><h6 class="mb-1">Predictive Maintenance Insight</h6></div>
                    <div class="card-body">
                        <?php $riskClass = ($intelligence['risk_band'] ?? 'low') === 'high' ? 'danger' : (($intelligence['risk_band'] ?? 'low') === 'medium' ? 'warning text-dark' : 'success'); ?>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge bg-<?= esc($riskClass) ?>">Risk <?= esc((int) ($intelligence['risk_percent'] ?? 0)) ?>%</span>
                            <span class="badge text-bg-light border text-uppercase"><?= esc($intelligence['decision_priority'] ?? 'low') ?> priority</span>
                        </div>
                        <div class="small text-muted mb-2"><?= esc($intelligence['decision_label'] ?? 'Normal monitoring') ?></div>
                        <div class="small text-muted">Bookings in last 30 days: <?= esc((int) ($intelligence['bookings_last_30d'] ?? 0)) ?></div>
                        <div class="small text-muted">Bookings in last 90 days: <?= esc((int) ($intelligence['bookings_last_90d'] ?? 0)) ?></div>
                        <div class="small text-muted">Units booked in last 90 days: <?= esc((int) ($intelligence['booking_units_last_90d'] ?? 0)) ?></div>
                        <div class="small text-muted">Days since last booking: <?= esc((int) ($intelligence['days_since_last_booking'] ?? 0)) ?></div>
                        <?php if (!empty($intelligence['next_due_at'])): ?>
                            <div class="small text-muted">Next estimated due date: <?= esc(date('d M Y', strtotime($intelligence['next_due_at']))) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($intelligence['reasons'])): ?>
                            <div class="small mt-3">
                                <?php foreach (array_slice($intelligence['reasons'], 0, 2) as $reason): ?>
                                    <div><?= esc($reason) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-1">Maintenance History</h6>
                        <span class="badge text-bg-light border"><?= esc(count($maintenanceHistory)) ?> record(s)</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($maintenanceHistory)): ?>
                            <p class="text-muted small mb-0">No maintenance records have been logged for this asset yet.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($maintenanceHistory as $record): ?>
                                    <div class="border rounded-3 p-3 bg-light-subtle">
                                        <div class="fw-semibold"><?= esc($record['title']) ?></div>
                                        <div class="small text-muted text-uppercase"><?= esc(str_replace('_', ' ', $record['status'])) ?> | <?= esc($record['priority']) ?></div>
                                        <div class="small text-muted">Affected units: <?= esc((int) ($record['quantity_affected'] ?? 1)) ?></div>
                                        <div class="small text-muted">Technician: <?= esc($record['technician_name'] ?: $record['technician_username'] ?: '-') ?></div>
                                        <div class="small text-muted">Updated: <?= esc($record['updated_at'] ? date('d M Y H:i', strtotime($record['updated_at'])) : '-') ?></div>
                                        <?php if (!empty($record['resolution_notes'])): ?>
                                            <div class="small mt-2"><?= esc($record['resolution_notes']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
