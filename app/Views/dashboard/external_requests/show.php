<?= $this->extend($layout) ?>

<?= $this->section('content') ?>

<?php
$statusLabels = $statusLabels ?? [];
$requestModel = $requestModel ?? null;
$status = (string) ($requestRecord['status'] ?? 'submitted');
$badgeClass = $requestModel ? $requestModel->statusBadgeClass($status) : 'secondary';
$requesterName = trim((string) ($requestRecord['requester_full_name'] ?? '')) ?: (string) ($requestRecord['contact_name'] ?? $requestRecord['requester_username'] ?? '-');
$reviewerName = trim((string) ($requestRecord['reviewer_full_name'] ?? '')) ?: (string) ($requestRecord['reviewer_username'] ?? '');
?>

<div class="dashboard-header">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-0">Review External Request</h2>
            <p class="text-muted small mb-0">Assess the request, add review notes, and move it to the correct operational status.</p>
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
                <h5 class="fw-semibold text-primary mb-0">Review History</h5>
            </div>
            <div class="card-body">
                <div class="small text-muted mb-2">Last reviewed by</div>
                <div class="fw-semibold mb-3"><?= esc($reviewerName !== '' ? $reviewerName : 'No reviewer yet') ?></div>
                <div class="small text-muted mb-2">Last review note</div>
                <div class="border rounded-3 p-3 bg-light-subtle">
                    <?= !empty($requestRecord['review_notes']) ? nl2br(esc($requestRecord['review_notes'])) : '<span class="text-muted">No review notes recorded yet.</span>' ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="fw-semibold text-primary mb-0">Update Request Status</h5>
            </div>
            <div class="card-body">
                <form action="/dashboard/external-requests/update/<?= esc($requestRecord['id']) ?>" method="post" class="row g-3">
                    <?= csrf_field() ?>

                    <div class="col-12">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <?php foreach ($statusLabels as $statusKey => $statusLabel): ?>
                                <option value="<?= esc($statusKey) ?>" <?= $status === $statusKey ? 'selected' : '' ?>><?= esc($statusLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Review Notes</label>
                        <textarea name="review_notes" class="form-control" rows="8" placeholder="Explain what the requester should know next."><?= esc(old('review_notes', $requestRecord['review_notes'] ?? '')) ?></textarea>
                        <div class="form-text">Add a clear reason when rejecting or requesting more information.</div>
                    </div>

                    <div class="col-12">
                        <div class="small text-muted">Last updated</div>
                        <div><?= esc(!empty($requestRecord['updated_at']) ? date('d M Y H:i', strtotime((string) $requestRecord['updated_at'])) : '-') ?></div>
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Save Review Decision</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
