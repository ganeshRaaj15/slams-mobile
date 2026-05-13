<?= $this->extend($layout) ?>

<?= $this->section('content') ?>

<?php
$filters = $filters ?? ['q' => '', 'status' => '', 'lab_id' => 0];
$statusLabels = $statusLabels ?? [];
$requestModel = $requestModel ?? null;
?>

<div class="dashboard-header">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-0">External Request Queue</h2>
            <p class="text-muted small mb-0">
                <?= $role === 'pic' ? 'Requests for laboratories assigned to you as PIC.' : 'Review and triage external access requests across the system.' ?>
            </p>
        </div>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-2"><div class="card shadow-sm border-0"><div class="card-body"><div class="small text-muted">Total</div><div class="fs-3 fw-bold"><?= esc((int) ($stats['total'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-2"><div class="card shadow-sm border-0"><div class="card-body"><div class="small text-muted">PIC Queue</div><div class="fs-3 fw-bold"><?= esc((int) ($stats['pending_pic_approval'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-2"><div class="card shadow-sm border-0"><div class="card-body"><div class="small text-muted">Manager Queue</div><div class="fs-3 fw-bold"><?= esc((int) ($stats['pending_manager_approval'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-2"><div class="card shadow-sm border-0"><div class="card-body"><div class="small text-muted">Needs Info</div><div class="fs-3 fw-bold"><?= esc((int) ($stats['needs_information'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-2"><div class="card shadow-sm border-0"><div class="card-body"><div class="small text-muted">Approved</div><div class="fs-3 fw-bold"><?= esc((int) ($stats['approved_for_scheduling'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-2"><div class="card shadow-sm border-0"><div class="card-body"><div class="small text-muted">Rejected</div><div class="fs-3 fw-bold"><?= esc((int) ($stats['rejected'] ?? 0)) ?></div></div></div></div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="get" action="/dashboard/external-requests" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted">Search</label>
                <input type="text" name="q" class="form-control" value="<?= esc($filters['q']) ?>" placeholder="Lab, organization, contact, or purpose">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    <?php foreach ($statusLabels as $statusKey => $statusLabel): ?>
                        <option value="<?= esc($statusKey) ?>" <?= $filters['status'] === $statusKey ? 'selected' : '' ?>><?= esc($statusLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Laboratory</label>
                <select name="lab_id" class="form-select">
                    <option value="0">All laboratories</option>
                    <?php foreach (($labs ?? []) as $lab): ?>
                        <option value="<?= esc($lab['id']) ?>" <?= (int) $filters['lab_id'] === (int) $lab['id'] ? 'selected' : '' ?>>
                            <?= esc($lab['name']) ?><?= !empty($lab['room']) ? ' (' . esc($lab['room']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                <a href="/dashboard/external-requests" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <h5 class="fw-semibold text-primary mb-0">Requests Awaiting Action</h5>
    </div>
    <div class="card-body">
        <?php if (empty($requests)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-clipboard-x fs-1 mb-3"></i>
                <p class="mb-0">No external requests matched the current filters.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Requester</th>
                            <th>Laboratory</th>
                            <th>Preferred Schedule</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <?php
                            $status = (string) ($request['status'] ?? 'pending_pic_approval');
                            $badgeClass = $requestModel ? $requestModel->statusBadgeClass($status) : 'secondary';
                            $requesterName = trim((string) ($request['requester_full_name'] ?? '')) ?: (string) ($request['contact_name'] ?? $request['requester_username'] ?? '-');
                            $stageLabel = $requestModel ? $requestModel->stageLabel($requestModel->currentApprovalStage($request)) : ucfirst((string) ($request['current_approval_stage'] ?? 'pic'));
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc($requesterName) ?></div>
                                    <div class="small text-muted"><?= esc($request['organization_name'] ?? '-') ?></div>
                                    <div class="small text-muted"><?= esc($request['contact_email'] ?? '-') ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= esc($request['lab_name'] ?? '-') ?></div>
                                    <div class="small text-muted"><?= esc($request['lab_room'] ?? '') ?></div>
                                </td>
                                <td>
                                    <div><?= esc($request['preferred_date'] ?? '-') ?></div>
                                    <?php if (!empty($request['preferred_start_time']) && !empty($request['preferred_end_time'])): ?>
                                        <div class="small text-muted"><?= esc(substr((string) $request['preferred_start_time'], 0, 5)) ?> - <?= esc(substr((string) $request['preferred_end_time'], 0, 5)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= esc($badgeClass) ?>"><?= esc($statusLabels[$status] ?? ucfirst($status)) ?></span>
                                    <div class="small text-muted mt-1"><?= esc($stageLabel) ?></div>
                                </td>
                                <td><?= esc(!empty($request['created_at']) ? date('d M Y H:i', strtotime((string) $request['created_at'])) : '-') ?></td>
                                <td class="text-end"><a href="/dashboard/external-requests/<?= esc($request['id']) ?>" class="btn btn-sm btn-outline-primary">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
