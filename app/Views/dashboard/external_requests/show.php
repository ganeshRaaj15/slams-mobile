<?= $this->extend($layout) ?>

<?= $this->section('content') ?>

<?php
$statusLabels = $statusLabels ?? [];
$requestModel = $requestModel ?? null;
$status = (string) ($requestRecord['status'] ?? 'pending_pic_approval');
$badgeClass = $requestModel ? $requestModel->statusBadgeClass($status) : 'secondary';
$requesterName = trim((string) ($requestRecord['requester_full_name'] ?? '')) ?: (string) ($requestRecord['contact_name'] ?? $requestRecord['requester_username'] ?? '-');
$picReviewerName = trim((string) ($requestRecord['pic_reviewer_full_name'] ?? '')) ?: (string) ($requestRecord['pic_reviewer_username'] ?? '');
$managerReviewerName = trim((string) ($requestRecord['manager_reviewer_full_name'] ?? '')) ?: (string) ($requestRecord['manager_reviewer_username'] ?? '');
$currentStage = $requestModel ? $requestModel->currentApprovalStage($requestRecord) : (string) ($requestRecord['current_approval_stage'] ?? 'pic');
$roleLabel = $role === 'manager' ? 'Lab Manager' : ($role === 'admin' ? 'Administrator' : 'PIC');
$awaitingAction = ($role === 'pic' && $currentStage === 'pic' && $status === 'pending_pic_approval')
    || ($role === 'manager' && $currentStage === 'manager' && $status === 'pending_manager_approval')
    || ($role === 'admin' && in_array($status, ['pending_pic_approval', 'pending_manager_approval'], true));
$approveValue = $currentStage === 'manager' ? 'approved_for_scheduling' : 'pending_manager_approval';
$approveLabel = $currentStage === 'manager' ? 'Approve For Scheduling' : 'Approve And Send To Lab Manager';
$latestNote = $requestModel ? $requestModel->latestRequesterNote($requestRecord) : (string) ($requestRecord['review_notes'] ?? '');
?>

<div class="dashboard-header">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-0">Review External Request</h2>
            <p class="text-muted small mb-0">This panel is stage-based for external users: PIC reviews first, then Lab Manager approval, with separate notes at each stage.</p>
        </div>
        <a href="/dashboard/external-requests" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Queue
        </a>
    </div>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="fw-semibold text-primary mb-0">Request Summary</h5>
                <span class="badge bg-<?= esc($badgeClass) ?>"><?= esc($statusLabels[$status] ?? ucfirst($status)) ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small text-muted">Requester</div>
                        <div class="fw-semibold"><?= esc($requesterName) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Organization</div>
                        <div class="fw-semibold"><?= esc($requestRecord['organization_name'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Email</div>
                        <div><?= esc($requestRecord['contact_email'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Phone</div>
                        <div><?= esc($requestRecord['contact_phone'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Laboratory</div>
                        <div class="fw-semibold"><?= esc($requestRecord['lab_name'] ?? '-') ?></div>
                        <div class="small text-muted"><?= esc($requestRecord['lab_room'] ?? '') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Participants</div>
                        <div><?= esc($requestRecord['participant_count'] ?? 0) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Current Approval Stage</div>
                        <div class="fw-semibold"><?= esc($requestModel ? $requestModel->stageLabel($currentStage) : ucfirst($currentStage)) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Preferred Date</div>
                        <div><?= esc($requestRecord['preferred_date'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Preferred Time</div>
                        <div>
                            <?php if (!empty($requestRecord['preferred_start_time']) && !empty($requestRecord['preferred_end_time'])): ?>
                                <?= esc(substr((string) $requestRecord['preferred_start_time'], 0, 5)) ?> - <?= esc(substr((string) $requestRecord['preferred_end_time'], 0, 5)) ?>
                            <?php else: ?>
                                Flexible / not specified
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">Purpose</div>
                        <div class="border rounded-3 p-3 bg-light-subtle"><?= nl2br(esc($requestRecord['purpose'] ?? '-')) ?></div>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">Equipment / Setup Notes</div>
                        <div class="border rounded-3 p-3 bg-light-subtle"><?= !empty($requestRecord['equipment_notes']) ? nl2br(esc($requestRecord['equipment_notes'])) : '<span class="text-muted">No additional equipment notes.</span>' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="fw-semibold text-primary mb-0">Approval Flow</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-semibold">PIC Review</div>
                                <span class="badge bg-<?= (int) ($requestRecord['pic_approved'] ?? 0) === 1 ? 'success' : ($currentStage === 'pic' ? 'warning text-dark' : 'secondary') ?>">
                                    <?= (int) ($requestRecord['pic_approved'] ?? 0) === 1 ? 'Approved' : ($currentStage === 'pic' && $status !== 'rejected' ? 'Active' : 'Waiting') ?>
                                </span>
                            </div>
                            <div class="small text-muted mb-1">Reviewer</div>
                            <div class="mb-2"><?= esc($picReviewerName !== '' ? $picReviewerName : 'No PIC review yet') ?></div>
                            <div class="small text-muted mb-1">Notes</div>
                            <div><?= !empty($requestRecord['pic_notes']) ? nl2br(esc($requestRecord['pic_notes'])) : '<span class="text-muted">No PIC notes yet.</span>' ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-semibold">Lab Manager Review</div>
                                <span class="badge bg-<?= (int) ($requestRecord['manager_approved'] ?? 0) === 1 ? 'success' : ($currentStage === 'manager' ? 'info text-dark' : 'secondary') ?>">
                                    <?= (int) ($requestRecord['manager_approved'] ?? 0) === 1 ? 'Approved' : ($currentStage === 'manager' && $status !== 'rejected' ? 'Active' : 'Waiting') ?>
                                </span>
                            </div>
                            <div class="small text-muted mb-1">Reviewer</div>
                            <div class="mb-2"><?= esc($managerReviewerName !== '' ? $managerReviewerName : 'No Lab Manager review yet') ?></div>
                            <div class="small text-muted mb-1">Notes</div>
                            <div><?= !empty($requestRecord['manager_notes']) ? nl2br(esc($requestRecord['manager_notes'])) : '<span class="text-muted">No Lab Manager notes yet.</span>' ?></div>
                        </div>
                    </div>
                </div>
                <div class="small text-muted mt-4 mb-2">Latest requester-facing note</div>
                <div class="border rounded-3 p-3 bg-light-subtle">
                    <?= $latestNote !== '' ? nl2br(esc($latestNote)) : '<span class="text-muted">No reviewer notes recorded yet.</span>' ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="fw-semibold text-primary mb-0">Approval Actions</h5>
            </div>
            <div class="card-body">
                <form action="/dashboard/external-requests/update/<?= esc($requestRecord['id']) ?>" method="post" class="row g-3">
                    <?= csrf_field() ?>

                    <div class="col-12">
                        <div class="small text-muted">Current actor</div>
                        <div class="fw-semibold"><?= esc($roleLabel) ?></div>
                        <div class="form-text">External users receive both notifications and email for every decision made here.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label"><?= esc($currentStage === 'manager' ? 'Lab Manager Notes' : 'PIC Notes') ?></label>
                        <textarea name="review_notes" class="form-control" rows="8" placeholder="Explain what the external requester should know next."><?= esc(old('review_notes', $currentStage === 'manager' ? ($requestRecord['manager_notes'] ?? '') : ($requestRecord['pic_notes'] ?? ''))) ?></textarea>
                        <div class="form-text">Required when requesting more information or rejecting the request.</div>
                    </div>

                    <div class="col-12">
                        <div class="small text-muted">Last updated</div>
                        <div><?= esc(!empty($requestRecord['updated_at']) ? date('d M Y H:i', strtotime((string) $requestRecord['updated_at'])) : '-') ?></div>
                    </div>

                    <?php if ($awaitingAction): ?>
                        <div class="col-12 d-flex flex-wrap justify-content-end gap-2">
                            <button type="submit" name="status" value="needs_information" class="btn btn-outline-secondary">Request More Information</button>
                            <button type="submit" name="status" value="rejected" class="btn btn-outline-danger">Reject Request</button>
                            <button type="submit" name="status" value="<?= esc($approveValue) ?>" class="btn btn-primary"><?= esc($approveLabel) ?></button>
                        </div>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-light border mb-0">This request is not currently waiting for action from your approval stage.</div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
