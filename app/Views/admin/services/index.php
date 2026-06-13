<?= $this->extend('layouts/main_admin') ?>
<?= $this->section('content') ?>

<?php $filters = $filters ?? ['q' => '', 'lab_id' => 0, 'active' => '']; ?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Service Bundles</h1>
            <p class="text-muted mb-0">Define bookable laboratory services as bundles of multiple assets and required quantities.</p>
        </div>
        <a href="/admin/services/create" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Add Service</a>
    </div>

    <?php if (session()->getFlashdata('message')): ?>
        <div class="alert alert-success border-0 shadow-sm"><?= esc(session()->getFlashdata('message')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger border-0 shadow-sm"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="/admin/services" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" name="q" class="form-control" value="<?= esc($filters['q']) ?>" placeholder="Service name, field of work, or laboratory">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">Laboratory</label>
                    <select name="lab_id" class="form-select">
                        <option value="0">All laboratories</option>
                        <?php foreach (($labs ?? []) as $lab): ?>
                            <option value="<?= esc($lab['id']) ?>" <?= (int) $filters['lab_id'] === (int) $lab['id'] ? 'selected' : '' ?>>
                                <?= esc($lab['name']) ?><?= ! empty($lab['room']) ? ' - ' . esc($lab['room']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Status</label>
                    <select name="active" class="form-select">
                        <option value="">All</option>
                        <option value="1" <?= $filters['active'] === '1' ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= $filters['active'] === '0' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-1 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($services)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-diagram-3 fs-1 d-block mb-3"></i>
                    No services configured yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Service</th>
                                <th>Laboratory</th>
                                <th>Bundle</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $service): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= esc($service['service_name']) ?></div>
                                        <div class="small text-muted"><?= esc($service['field_name'] ?: 'General service') ?></div>
                                        <?php if (! empty($service['acceptance_criteria'])): ?>
                                            <div class="small text-muted mt-1"><?= esc($service['acceptance_criteria']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= esc($service['lab_name'] ?: '-') ?></div>
                                        <div class="small text-muted"><?= esc($service['lab_room'] ?: '-') ?></div>
                                    </td>
                                    <td>
                                        <div class="small"><?= esc($service['bundle_summary'] ?: 'No linked assets') ?></div>
                                        <div class="small text-muted"><?= esc(count($service['required_assets'] ?? [])) ?> required asset type(s)</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= ! empty($service['is_active']) ? 'primary' : 'secondary' ?>"><?= ! empty($service['is_active']) ? 'Active' : 'Inactive' ?></span>
                                        <span class="badge bg-<?= ! empty($service['is_bookable']) ? 'success' : 'warning text-dark' ?> mt-1">
                                            <?= ! empty($service['is_bookable']) ? 'Bundle Available' : 'Bundle Unavailable' ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="/admin/services/edit/<?= esc($service['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="/admin/services/delete/<?= esc($service['id']) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this service bundle?')"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
