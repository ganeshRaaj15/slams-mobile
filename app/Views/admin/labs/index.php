<?= $this->extend('layouts/main_admin') ?>
<?= $this->section('content') ?>

<?php $filters = $filters ?? ['q' => '', 'pic' => '']; ?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Laboratory Management</h1>
            <p class="text-muted mb-0">Manage laboratory profiles, PIC ownership, capacity, and asset readiness.</p>
        </div>
        <a href="/admin/labs/create" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Add Lab</a>
    </div>

    <?php if (session()->getFlashdata('message')): ?>
        <div class="alert alert-success border-0 shadow-sm"><?= esc(session()->getFlashdata('message')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('warning')): ?>
        <div class="alert alert-warning border-0 shadow-sm"><?= esc(session()->getFlashdata('warning')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger border-0 shadow-sm"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="/admin/labs" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" name="q" class="form-control" value="<?= esc($filters['q']) ?>" placeholder="Lab name, room, PIC name, or PIC email">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">PIC Assignment</label>
                    <select name="pic" class="form-select">
                        <option value="">All labs</option>
                        <option value="assigned" <?= $filters['pic'] === 'assigned' ? 'selected' : '' ?>>Assigned PIC</option>
                        <option value="unassigned" <?= $filters['pic'] === 'unassigned' ? 'selected' : '' ?>>Missing PIC email</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a href="/admin/labs" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                </div>
            </form>
            <div class="small text-muted mt-3">Showing <?= esc(count($labs)) ?> of <?= esc($totalLabs ?? count($labs)) ?> laboratory record(s).</div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($labs)): ?>
                <div class="text-center text-muted py-5">No laboratories have been added yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Laboratory</th>
                                <th>PIC</th>
                                <th>Profile</th>
                                <th>Assets</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($labs as $lab): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= esc($lab['name']) ?></div>
                                        <div class="small text-muted">Room <?= esc($lab['room']) ?></div>
                                    </td>
                                    <td>
                                        <div><?= esc($lab['pic_name']) ?></div>
                                        <div class="small text-muted"><?= esc($lab['pic_email'] ?: '-') ?></div>
                                        <div class="small text-muted"><?= esc($lab['pic_phone'] ?: '-') ?></div>
                                        <?php if (!empty($lab['pic_email']) && empty($lab['pic_account_linked'])): ?>
                                            <div class="small text-danger mt-1">PIC email is not linked to a user account.</div>
                                        <?php elseif (!empty($lab['pic_email']) && empty($lab['pic_account_has_role'])): ?>
                                            <div class="small text-warning mt-1">Linked user does not have the PIC role.</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>Capacity: <?= esc($lab['capacity'] ?: '-') ?></div>
                                        <div class="small text-muted"><?= esc($lab['availability_note'] ?: 'No availability note') ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= esc($lab['asset_total']) ?> asset(s)</div>
                                        <div class="small text-muted"><?= esc($lab['assets_in_maintenance']) ?> in maintenance</div>
                                        <div class="small text-muted"><?= esc($lab['faulty_assets']) ?> faulty</div>
                                    </td>
                                    <td class="text-center">
                                        <a href="/admin/labs/edit/<?= esc($lab['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="/admin/labs/delete/<?= esc($lab['id']) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this laboratory?')"><i class="bi bi-trash"></i></button>
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
