<?= $this->extend('layouts/main_admin') ?>
<?= $this->section('content') ?>

<?php $filters = $filters ?? ['lab_id' => 0, 'status' => '', 'q' => '']; ?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Full-Lab Reservations</h1>
            <p class="text-muted mb-0">Use these blocks for walk-ins, classes, events, or manual closures that should reserve the whole laboratory.</p>
        </div>
        <a href="/admin/reservations/create" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Add Reservation</a>
    </div>

    <?php if (session()->getFlashdata('message')): ?>
        <div class="alert alert-success border-0 shadow-sm"><?= esc(session()->getFlashdata('message')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger border-0 shadow-sm"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="/admin/reservations" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" name="q" class="form-control" value="<?= esc($filters['q']) ?>" placeholder="Title, note, or laboratory">
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
                <div class="col-md-3">
                    <label class="form-label small text-muted">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
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
            <?php if (empty($reservations)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-calendar-range fs-1 d-block mb-3"></i>
                    No full-lab reservations configured.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Reservation</th>
                                <th>Laboratory</th>
                                <th>Window</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= esc($reservation['title'] ?? '-') ?></div>
                                        <div class="small text-muted text-uppercase"><?= esc(str_replace('_', ' ', $reservation['reservation_type'] ?? 'reservation')) ?></div>
                                        <?php if (! empty($reservation['notes'])): ?>
                                            <div class="small text-muted mt-1"><?= esc($reservation['notes']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= esc($reservation['lab_name'] ?? '-') ?></div>
                                        <div class="small text-muted"><?= esc($reservation['lab_room'] ?? '-') ?></div>
                                    </td>
                                    <td>
                                        <div><?= esc(date('d M Y H:i', strtotime((string) ($reservation['start_at'] ?? 'now')))) ?></div>
                                        <div class="small text-muted">to <?= esc(date('d M Y H:i', strtotime((string) ($reservation['end_at'] ?? 'now')))) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= ($reservation['status'] ?? 'active') === 'active' ? 'danger' : 'secondary' ?>">
                                            <?= esc(ucfirst((string) ($reservation['status'] ?? 'active'))) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="/admin/reservations/edit/<?= esc($reservation['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="/admin/reservations/delete/<?= esc($reservation['id']) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this full-lab reservation?')"><i class="bi bi-trash"></i></button>
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
