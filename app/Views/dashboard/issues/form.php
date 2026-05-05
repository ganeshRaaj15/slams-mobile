<?= $this->extend('layouts/main_user') ?>
<?= $this->section('content') ?>

<div class="container py-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-1">Report Asset Issue</h2>
            <p class="text-muted mb-0 small">Only fill the basics: choose the equipment, identify the exact unit if needed, explain the problem, and attach a photo if you have one.</p>
        </div>
        <a href="/dashboard" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-2"></i>Back to Dashboard</a>
    </div>

    <?php if (session()->getFlashdata('success')): ?><div class="alert alert-success shadow-sm border-0"><?= esc(session()->getFlashdata('success')) ?></div><?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger shadow-sm border-0"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4"><h5 class="fw-semibold text-primary mb-1">New Issue Report</h5></div>
                <div class="card-body px-4 pb-4">
                    <form method="post" action="/dashboard/report-issue/store" class="row g-3" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="col-12">
                            <div class="alert alert-info border-0 mb-0">
                                <div class="fw-semibold text-dark mb-2">What You Need To Fill</div>
                                <ul class="small mb-0 ps-3">
                                    <li>Select the equipment with the problem.</li>
                                    <li>Enter how many units are affected and identify the exact unit if there are multiple similar units.</li>
                                    <li>Write a short summary and a clear problem description.</li>
                                    <li>Add a photo if it helps show the issue.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Equipment With The Problem</label>
                            <select name="asset_id" class="form-select" required>
                                <option value="">Select equipment</option>
                                <?php foreach ($assets as $asset): ?>
                                    <option value="<?= esc($asset['id']) ?>" <?= old('asset_id') == $asset['id'] ? 'selected' : '' ?>><?= esc($asset['asset_code'] ?: ('AST-' . str_pad((string) $asset['id'], 4, '0', STR_PAD_LEFT))) ?> - <?= esc($asset['name']) ?><?= !empty($asset['lab_name']) ? ' | ' . esc($asset['lab_name']) : '' ?><?= !empty($asset['lab_room']) ? ' (' . esc($asset['lab_room']) . ')' : '' ?> | <?= esc((int) ($asset['quantity'] ?? 0)) ?> available of <?= esc((int) ($asset['total_quantity'] ?? $asset['quantity'] ?? 0)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Pick the exact asset. The option text also shows how many units are still available.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Short Problem Summary</label>
                            <input type="text" name="title" class="form-control" value="<?= esc(old('title')) ?>" placeholder="Example: PC does not power on" required>
                            <div class="form-text">Use a short title that someone else can recognize quickly.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">How Many Units Are Affected?</label>
                            <input type="number" name="quantity_affected" class="form-control" min="1" value="<?= esc(old('quantity_affected', 1)) ?>" required>
                            <div class="form-text">Enter the number of units included in this report.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">How Urgent Is It?</label>
                            <select name="priority" class="form-select" required><?php foreach ($priorities as $priority): ?><option value="<?= esc($priority) ?>" <?= old('priority', 'medium') === $priority ? 'selected' : '' ?>><?= esc(ucfirst($priority)) ?></option><?php endforeach; ?></select>
                            <div class="form-text">Use high or critical only when the equipment cannot be used or the issue is urgent.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Unit / Workstation Reference</label>
                            <input type="text" name="unit_reference" class="form-control" value="<?= esc(old('unit_reference')) ?>" placeholder="Example: PC-07, Seat B3, Projector Unit 2, Monitor at Booth 4">
                            <div class="form-text">Required for multi-unit equipment such as PCs, monitors, or laptops.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Problem Description</label>
                            <textarea name="description" rows="6" class="form-control" placeholder="Describe what is wrong, what you observed, and when it happened..." required><?= esc(old('description')) ?></textarea>
                            <div class="form-text">Explain the symptoms, what you tried, and anything the technician should notice first.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Photo Evidence</label>
                            <input type="file" name="report_photo" class="form-control" accept="image/png,image/jpeg,image/webp">
                            <div class="form-text">Optional, but recommended. A clear photo helps the technician identify the exact issue faster.</div>
                        </div>
                        <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-primary"><i class="bi bi-send me-2"></i>Submit Report</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-4 mb-4"><div class="card-header bg-white border-0 pt-4 px-4"><h6 class="fw-semibold text-primary mb-1">What Happens Next</h6></div><div class="card-body px-4 pb-4 small text-muted"><p class="mb-2"><strong>1.</strong> You report the issue and identify the affected unit.</p><p class="mb-2"><strong>2.</strong> The technician reviews the report, adds a diagnosis, and schedules the maintenance work.</p><p class="mb-2"><strong>3.</strong> The technician records the repair work and tests the equipment.</p><p class="mb-0"><strong>4.</strong> The unit becomes available again only after the technician completes the workflow with notes and proof.</p></div></div>
            <div class="card shadow-sm border-0 rounded-4"><div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center"><h6 class="fw-semibold text-primary mb-1">Your Recent Reports</h6><span class="badge bg-light text-dark border"><?= esc(count($recentReports)) ?></span></div><div class="card-body px-4 pb-4"><?php if (empty($recentReports)): ?><p class="text-muted small mb-0">You have not submitted any equipment issue reports yet.</p><?php else: ?><div class="d-flex flex-column gap-3"><?php foreach ($recentReports as $report): ?><div class="border rounded-3 p-3 bg-light"><div class="fw-semibold"><?= esc($report['title']) ?></div><div class="small text-muted"><?= esc($report['asset_name'] ?? '-') ?> | <?= esc($report['laboratory_name'] ?? '-') ?></div><?php if (!empty($report['unit_reference'])): ?><div class="small text-muted">Unit reference: <?= esc($report['unit_reference']) ?></div><?php endif; ?><div class="small text-muted">Affected units: <?= esc((int) ($report['quantity_affected'] ?? 1)) ?></div><div class="small text-muted text-uppercase mt-1"><?= esc(str_replace('_', ' ', $report['status'])) ?> | <?= esc($report['priority']) ?></div></div><?php endforeach; ?></div><?php endif; ?></div></div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
