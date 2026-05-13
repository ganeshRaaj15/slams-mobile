<?= $this->extend('layouts/main_user') ?>

<?= $this->section('content') ?>

<?php
$filters = $filters ?? ['q' => '', 'status' => '', 'lab_id' => 0];
$stats = $stats ?? [];
$statusLabels = $statusLabels ?? [];
$requestModel = $requestModel ?? null;
?>

<div class="dashboard-header">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-0">External Access Requests</h2>
            <p class="text-muted small mb-0">Submit structured lab-use requests and track review updates without using the booking workflow directly.</p>
        </div>
        <a href="/dashboard/external/request" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> New Request
        </a>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<div class="alert alert-info border-0 shadow-sm mb-4">
    <strong>Status updates come by notification and email:</strong> after you submit, the PIC reviews first, then the Lab Manager. If either reviewer asks for extra information or adds notes, you will receive that update directly and can resubmit here.
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2">
        <div class="card shadow-sm border-0"><div class="card-body"><div class="small text-muted">Total</div><div class="fs-3 fw-bold"><?= esc((int) ($stats['total'] ?? 0)) ?></div></div></div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm border-0"><div class="card-body"><div class="small text-muted">PIC Queue</div><div class="fs-3 fw-bold"><?= esc((int) ($stats['pending_pic_approval'] ?? 0)) ?></div></div></div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm border-0"><div class="card-body"><div class="small text-muted">Manager Queue</div><div class="fs-3 fw-bold"><?= esc((int) ($stats['pending_manager_approval'] ?? 0)) ?></div></div></div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm border-0"><div class="card-body"><div class="small text-muted">Needs Info</div><div class="fs-3 fw-bold"><?= esc((int) ($stats['needs_information'] ?? 0)) ?></div></div></div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm border-0"><div class="card-body"><div class="small text-muted">Approved</div><div class="fs-3 fw-bold"><?= esc((int) ($stats['approved_for_scheduling'] ?? 0)) ?></div></div></div>
    </div>
    <div class="col-md-2">
        <div class="card shadow-sm border-0"><div class="card-body"><div class="small text-muted">Rejected</div><div class="fs-3 fw-bold"><?= esc((int) ($stats['rejected'] ?? 0)) ?></div></div></div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="get" action="/dashboard/external" class="row g-3 align-items-end">
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
                <a href="/dashboard/external" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <h5 class="fw-semibold text-primary mb-0">My Requests</h5>
    </div>
    <div class="card-body">
        <?php if (empty($requests)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inboxes fs-1 mb-3"></i>
                <p class="mb-1">No external requests found.</p>
                <p class="small mb-3">Start by submitting a request for the laboratory you want to use.</p>
                <a href="/dashboard/external/request" class="btn btn-primary btn-sm">Create your first request</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Request</th>
                            <th>Laboratory</th>
                            <th>Preferred Schedule</th>
                            <th>Status</th>
                            <th>Review Notes</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <?php
                            $status = (string) ($request['status'] ?? 'pending_pic_approval');
                            $badgeClass = $requestModel ? $requestModel->statusBadgeClass($status) : 'secondary';
                            $canEdit = $requestModel ? $requestModel->canUserEdit($request) : false;
                            $latestNote = $requestModel ? $requestModel->latestRequesterNote($request) : (string) ($request['review_notes'] ?? '');
                            $schedule = esc($request['preferred_date'] ?? '-');
                            if (!empty($request['preferred_start_time']) && !empty($request['preferred_end_time'])) {
                                $schedule .= '<br><small class="text-muted">' . esc(substr((string) $request['preferred_start_time'], 0, 5)) . ' - ' . esc(substr((string) $request['preferred_end_time'], 0, 5)) . '</small>';
                            }
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc($request['organization_name'] ?? '-') ?></div>
                                    <div class="small text-muted"><?= esc($request['contact_name'] ?? '-') ?>, <?= esc($request['participant_count'] ?? 0) ?> participant(s)</div>
                                    <div class="small text-muted">Submitted <?= esc(!empty($request['created_at']) ? date('d M Y H:i', strtotime((string) $request['created_at'])) : '-') ?></div>
                                    <div class="small text-muted">Current stage: <?= esc($requestModel ? $requestModel->stageLabel($requestModel->currentApprovalStage($request)) : ucfirst((string) ($request['current_approval_stage'] ?? 'pic'))) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= esc($request['lab_name'] ?? '-') ?></div>
                                    <div class="small text-muted"><?= esc($request['lab_room'] ?? '') ?></div>
                                </td>
                                <td><?= $schedule ?></td>
                                <td>
                                    <span class="badge bg-<?= esc($badgeClass) ?>"><?= esc($statusLabels[$status] ?? ucwords(str_replace('_', ' ', $status))) ?></span>
                                </td>
                                <td>
                                    <?php if ($latestNote !== ''): ?>
                                        <div class="small"><?= nl2br(esc($latestNote)) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">No notes yet.</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($canEdit): ?>
                                        <a href="/dashboard/external/request/edit/<?= esc($request['id']) ?>" class="btn btn-sm btn-outline-primary">Update</a>
                                    <?php else: ?>
                                        <span class="text-muted small">Locked</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
