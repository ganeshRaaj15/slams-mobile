<?= $this->extend('layouts/main_technician') ?>
<?= $this->section('content') ?>
<?php
$statusLabel = $statusLabels[$record['status'] ?? 'reported'] ?? ucwords(str_replace('_', ' ', $record['status'] ?? 'reported'));
$isEdit = $mode === 'edit';
$reporterName = $record['reported_by_name'] ?? $record['reported_by_username'] ?? 'System';
$issuePhoto = !empty($record['report_photo_path']) ? base_url($record['report_photo_path']) : null;
$completionPhoto = !empty($record['completion_photo_path']) ? base_url($record['completion_photo_path']) : null;
$actionUrl = $isEdit ? '/technician/maintenance/update/' . $record['id'] : '/technician/maintenance/store';
$stageTitle = match ($stageMode) {
    'pre' => 'Plan Preventive Maintenance',
    'reported' => 'Review Reported Issue',
    'scheduled' => 'Scheduled Case Ready To Start',
    'in_progress' => 'Record Repair Work',
    'testing' => 'Test And Close Case',
    default => 'Maintenance Case',
};
$stageHelp = match ($stageMode) {
    'pre' => 'Create a planned maintenance case, set the schedule, and record why the work is needed.',
    'reported' => 'Confirm the reported issue, add your diagnosis, and schedule the maintenance visit.',
    'scheduled' => 'This step does not need new notes yet. Review the case and start repair when work begins.',
    'in_progress' => 'Only record the repair or servicing work completed in this step.',
    'testing' => 'Finish the case with testing notes, the final outcome, and completion evidence.',
    default => 'This case is closed. You can review the details and evidence below.',
};
$readOnlyDetails = in_array($stageMode, ['scheduled', 'in_progress', 'testing', 'locked'], true);
$submitLabel = match ($stageMode) {
    'pre' => 'Create And Schedule Case',
    'reported' => 'Accept And Schedule Case',
    'scheduled' => 'Mark Repair As Started',
    'in_progress' => 'Save And Move To Testing',
    'testing' => 'Complete Case',
    default => 'Back',
};
$primaryTransition = match ($stageMode) {
    'reported' => 'scheduled',
    'scheduled' => 'in_progress',
    'in_progress' => 'testing',
    'testing' => 'completed',
    default => '',
};
$issueTypeLabels = [
    'preventive' => 'Preventive',
    'corrective' => 'Corrective',
    'inspection' => 'Inspection',
    'calibration' => 'Calibration',
    'other' => 'Other',
];
$selectedAsset = null;
foreach ($assets as $assetOption) {
    if ((string) ($assetOption['id'] ?? '') === (string) old('asset_id', (string) ($record['asset_id'] ?? ''))) {
        $selectedAsset = $assetOption;
        break;
    }
}
$selectedAssetSummary = 'No equipment selected yet.';
if ($selectedAsset) {
    $selectedAssetSummary = trim((string) ($selectedAsset['asset_code'] ?? '')) !== ''
        ? $selectedAsset['asset_code'] . ' - ' . $selectedAsset['name']
        : $selectedAsset['name'];
    if (! empty($selectedAsset['lab_name'])) {
        $selectedAssetSummary .= ' | ' . $selectedAsset['lab_name'];
    }
    $selectedAssetSummary .= ' | ' . (int) ($selectedAsset['quantity'] ?? 0) . ' available of ' . (int) ($selectedAsset['total_quantity'] ?? $selectedAsset['quantity'] ?? 0);
}
$currentIssueType = old('issue_type', $record['issue_type'] ?? '');
$currentPriority = old('priority', $record['priority'] ?? '');
$stageChecklist = match ($stageMode) {
    'pre' => [
        'Fill the case basics so the system knows which equipment and units are involved.',
        'Set the planned maintenance date and time.',
        'Record the diagnosis or the reason this planned work is needed.',
    ],
    'reported' => [
        'Review the reported case details.',
        'Set the maintenance date and time.',
        'Record your diagnosis before accepting the case.',
    ],
    'scheduled' => [
        'No extra fields are required on this step.',
        'Check the schedule and diagnosis, then start repair when work begins.',
    ],
    'in_progress' => [
        'Add the repair or servicing work completed.',
        'Move the case to testing after the repair notes are complete.',
    ],
    'testing' => [
        'Confirm the repair notes are complete.',
        'Explain how the equipment was tested.',
        'Summarize the final condition and attach a completion photo.',
    ],
    default => [
        'This case is closed.',
        'Review the summary, evidence, and activity log if you need the history.',
    ],
};
?>
<div class="container-fluid">
    <?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger border-0 shadow-sm"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>
    <?php if (session()->getFlashdata('success')): ?><div class="alert alert-success border-0 shadow-sm"><?= esc(session()->getFlashdata('success')) ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1"><?= esc($stageTitle) ?></h5>
                        <small class="text-muted"><?= esc($stageHelp) ?></small>
                    </div>
                    <span class="badge text-bg-secondary"><?= esc($statusLabel) ?></span>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= $actionUrl ?>" class="row g-3" enctype="multipart/form-data">
                        <?= csrf_field() ?>

                        <div class="col-12">
                            <div class="alert alert-info border-0 mb-0">
                                <div class="fw-semibold text-dark mb-2">What To Fill On This Step</div>
                                <ul class="small mb-0 ps-3">
                                    <?php foreach ($stageChecklist as $item): ?>
                                        <li><?= esc($item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="border rounded-3 p-3 bg-light-subtle">
                                <div class="fw-semibold text-dark mb-1">Case Basics</div>
                                <div class="small text-muted mb-0">
                                    <?= $readOnlyDetails ? 'These details are locked after scheduling so the maintenance history stays consistent.' : 'These details identify the equipment, the affected units, and what this maintenance case is about.' ?>
                                </div>
                            </div>
                        </div>

                        <?php if (! $readOnlyDetails): ?>
                            <div class="col-md-6">
                                <label class="form-label">Equipment Being Serviced</label>
                                <select name="asset_id" class="form-select" required>
                                    <?php if (! $isEdit): ?><option value="">Select equipment</option><?php endif; ?>
                                    <?php foreach ($assets as $asset): ?>
                                        <option value="<?= esc($asset['id']) ?>" <?= (string) old('asset_id', (string) ($record['asset_id'] ?? '')) === (string) $asset['id'] ? 'selected' : '' ?>>
                                            <?= esc($asset['name']) ?><?= !empty($asset['lab_name']) ? ' - ' . esc($asset['lab_name']) : '' ?> | <?= esc((int) ($asset['quantity'] ?? 0)) ?> available of <?= esc((int) ($asset['total_quantity'] ?? $asset['quantity'] ?? 0)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Pick the exact asset. The option text also shows how many units are still available to take out of service.</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">How Many Units Are Affected?</label>
                                <input type="number" name="quantity_affected" min="1" class="form-control" value="<?= esc(old('quantity_affected', $record['quantity_affected'] ?? 1)) ?>" required>
                                <div class="form-text">Enter the number of units included in this maintenance case.</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">How Urgent Is It?</label>
                                <select name="priority" class="form-select" required>
                                    <?php foreach ($priorities as $priority): ?>
                                        <option value="<?= esc($priority) ?>" <?= old('priority', $record['priority'] ?? '') === $priority ? 'selected' : '' ?>><?= esc(ucfirst($priority)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Use high or critical only when the equipment is unusable or the issue is time-sensitive.</div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Short Case Title</label>
                                <input type="text" name="title" class="form-control" value="<?= esc(old('title', $record['title'] ?? '')) ?>" placeholder="Example: Preventive maintenance for oscilloscope set A" required>
                                <div class="form-text">A short label that helps the technician and reporter recognize the case quickly.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Maintenance Category</label>
                                <select name="issue_type" class="form-select" required <?= ($isEdit && ($record['issue_type'] ?? '') === 'corrective') ? 'disabled' : '' ?>>
                                    <?php foreach ($issueTypes as $issueType): ?>
                                        <option value="<?= esc($issueType) ?>" <?= old('issue_type', $record['issue_type'] ?? '') === $issueType ? 'selected' : '' ?>><?= esc($issueTypeLabels[$issueType] ?? ucfirst($issueType)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($isEdit && ($record['issue_type'] ?? '') === 'corrective'): ?><input type="hidden" name="issue_type" value="<?= esc(old('issue_type', $record['issue_type'] ?? '')) ?>"><?php endif; ?>
                                <?php if ($isEdit && ($record['issue_type'] ?? '') === 'corrective'): ?>
                                    <div class="form-text">This is a user-reported corrective case, so the category cannot be changed here.</div>
                                <?php else: ?>
                                    <div class="form-text">Preventive is for planned work. Inspection and calibration are also planned maintenance categories.</div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Unit / Workstation Reference</label>
                                <input type="text" name="unit_reference" class="form-control" value="<?= esc(old('unit_reference', $record['unit_reference'] ?? '')) ?>" placeholder="Example: PC-07, Seat B3, Projector Unit 2">
                                <div class="form-text">Required for multi-unit equipment so the technician knows the exact physical unit involved.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Problem Or Work Description</label>
                                <textarea name="description" class="form-control" rows="4" placeholder="Explain what is wrong or what maintenance work is planned, including any symptoms or observations." required><?= esc(old('description', $record['description'] ?? '')) ?></textarea>
                                <div class="form-text">Describe the problem if this is a reported fault, or the reason for planned work if this is preventive maintenance.</div>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="asset_id" value="<?= esc(old('asset_id', $record['asset_id'] ?? '')) ?>">
                            <input type="hidden" name="quantity_affected" value="<?= esc(old('quantity_affected', $record['quantity_affected'] ?? 1)) ?>">
                            <input type="hidden" name="priority" value="<?= esc(old('priority', $record['priority'] ?? '')) ?>">
                            <input type="hidden" name="title" value="<?= esc(old('title', $record['title'] ?? '')) ?>">
                            <input type="hidden" name="issue_type" value="<?= esc(old('issue_type', $record['issue_type'] ?? '')) ?>">
                            <input type="hidden" name="unit_reference" value="<?= esc(old('unit_reference', $record['unit_reference'] ?? '')) ?>">
                            <input type="hidden" name="description" value="<?= esc(old('description', $record['description'] ?? '')) ?>">

                            <div class="col-md-8">
                                <label class="form-label">Equipment Being Serviced</label>
                                <input type="text" class="form-control" value="<?= esc($selectedAssetSummary) ?>" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Affected Units</label>
                                <input type="text" class="form-control" value="<?= esc((string) old('quantity_affected', $record['quantity_affected'] ?? 1)) ?>" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Priority</label>
                                <input type="text" class="form-control" value="<?= esc(ucfirst((string) $currentPriority)) ?>" readonly>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Short Case Title</label>
                                <input type="text" class="form-control" value="<?= esc(old('title', $record['title'] ?? '')) ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Maintenance Category</label>
                                <input type="text" class="form-control" value="<?= esc($issueTypeLabels[$currentIssueType] ?? ucfirst((string) $currentIssueType)) ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Unit / Workstation Reference</label>
                                <input type="text" class="form-control" value="<?= esc(old('unit_reference', $record['unit_reference'] ?? 'Not specified')) ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Problem Or Work Description</label>
                                <textarea class="form-control" rows="4" readonly><?= esc(old('description', $record['description'] ?? '')) ?></textarea>
                            </div>
                        <?php endif; ?>

                        <?php if (in_array($stageMode, ['pre', 'reported'], true)): ?>
                            <div class="col-12">
                                <div class="border rounded-3 p-3 bg-light-subtle">
                                    <div class="fw-semibold text-dark mb-1">Step 1: Plan And Schedule</div>
                                    <div class="small text-muted mb-0">Set when the maintenance will happen and record the initial diagnosis or planned reason before moving the case forward.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Planned Maintenance Date And Time</label>
                                <input type="datetime-local" name="scheduled_for" class="form-control" value="<?= esc(old('scheduled_for', !empty($record['scheduled_for']) ? date('Y-m-d\TH:i', strtotime($record['scheduled_for'])) : '')) ?>">
                                <div class="form-text">Required before the case can be scheduled.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Diagnosis / Initial Findings</label>
                                <textarea name="diagnosis_notes" class="form-control" rows="4" placeholder="State what is faulty, what you observed, or why this planned work is needed."><?= esc(old('diagnosis_notes', $record['diagnosis_notes'] ?? '')) ?></textarea>
                                <div class="form-text">Required before this step can be completed. Keep it short and specific.</div>
                            </div>
                        <?php elseif ($stageMode === 'scheduled'): ?>
                            <input type="hidden" name="scheduled_for" value="<?= esc(old('scheduled_for', !empty($record['scheduled_for']) ? date('Y-m-d\TH:i', strtotime($record['scheduled_for'])) : '')) ?>">
                            <input type="hidden" name="diagnosis_notes" value="<?= esc(old('diagnosis_notes', $record['diagnosis_notes'] ?? '')) ?>">
                            <div class="col-12">
                                <div class="border rounded-3 p-3 bg-light-subtle">
                                    <div class="fw-semibold text-dark mb-1">Step 2: Start Repair</div>
                                    <div class="small text-muted mb-0">No new notes are required yet. Review the schedule and diagnosis, then start repair when the technician begins work.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Scheduled Date And Time</label>
                                <input type="text" class="form-control" value="<?= esc(!empty($record['scheduled_for']) ? date('d M Y H:i', strtotime($record['scheduled_for'])) : '-') ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Diagnosis / Initial Findings</label>
                                <textarea class="form-control" rows="4" readonly><?= esc($record['diagnosis_notes'] ?? '') ?></textarea>
                            </div>
                        <?php elseif ($stageMode === 'in_progress'): ?>
                            <input type="hidden" name="scheduled_for" value="<?= esc(old('scheduled_for', !empty($record['scheduled_for']) ? date('Y-m-d\TH:i', strtotime($record['scheduled_for'])) : '')) ?>">
                            <input type="hidden" name="diagnosis_notes" value="<?= esc(old('diagnosis_notes', $record['diagnosis_notes'] ?? '')) ?>">
                            <div class="col-12">
                                <div class="border rounded-3 p-3 bg-light-subtle">
                                    <div class="fw-semibold text-dark mb-1">Step 3: Record Repair Work</div>
                                    <div class="small text-muted mb-0">Describe what was repaired, replaced, cleaned, calibrated, or otherwise serviced before you move the case to testing.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Scheduled Date And Time</label>
                                <input type="text" class="form-control" value="<?= esc(!empty($record['scheduled_for']) ? date('d M Y H:i', strtotime($record['scheduled_for'])) : '-') ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Diagnosis / Initial Findings</label>
                                <textarea class="form-control" rows="4" readonly><?= esc($record['diagnosis_notes'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Repair Work Completed</label>
                                <textarea name="work_notes" class="form-control" rows="4" placeholder="Describe the repair work or servicing that was carried out."><?= esc(old('work_notes', $record['work_notes'] ?? '')) ?></textarea>
                                <div class="form-text">Required before you can move the case to testing.</div>
                            </div>
                        <?php elseif ($stageMode === 'testing'): ?>
                            <input type="hidden" name="scheduled_for" value="<?= esc(old('scheduled_for', !empty($record['scheduled_for']) ? date('Y-m-d\TH:i', strtotime($record['scheduled_for'])) : '')) ?>">
                            <input type="hidden" name="diagnosis_notes" value="<?= esc(old('diagnosis_notes', $record['diagnosis_notes'] ?? '')) ?>">
                            <div class="col-12">
                                <div class="border rounded-3 p-3 bg-light-subtle">
                                    <div class="fw-semibold text-dark mb-1">Step 4: Test And Close</div>
                                    <div class="small text-muted mb-0">Confirm what work was done, explain how the equipment was tested, summarize the final condition, and attach proof before closing the case.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Scheduled Date And Time</label>
                                <input type="text" class="form-control" value="<?= esc(!empty($record['scheduled_for']) ? date('d M Y H:i', strtotime($record['scheduled_for'])) : '-') ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Diagnosis / Initial Findings</label>
                                <textarea class="form-control" rows="4" readonly><?= esc($record['diagnosis_notes'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Repair Work Completed</label>
                                <textarea name="work_notes" class="form-control" rows="4" placeholder="Describe the repair work or servicing that was carried out."><?= esc(old('work_notes', $record['work_notes'] ?? '')) ?></textarea>
                                <div class="form-text">Required before completion.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">How You Tested Or Verified It</label>
                                <textarea name="test_notes" class="form-control" rows="4" placeholder="Explain how you checked that the equipment is working again."><?= esc(old('test_notes', $record['test_notes'] ?? '')) ?></textarea>
                                <div class="form-text">Required before completion.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Final Outcome Summary</label>
                                <textarea name="resolution_notes" class="form-control" rows="4" placeholder="Summarize the final condition of the equipment and what was resolved."><?= esc(old('resolution_notes', $record['resolution_notes'] ?? '')) ?></textarea>
                                <div class="form-text">Required before completion.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Completion Photo</label>
                                <input type="file" name="completion_photo" class="form-control" accept="image/png,image/jpeg,image/webp">
                                <div class="form-text">Attach one clear photo after the repair or servicing is finished. This is required unless a completion photo is already stored for the case.</div>
                            </div>
                        <?php endif; ?>

                        <?php if (! $isLocked): ?>
                            <div class="col-12 d-flex justify-content-end gap-2">
                                <a href="/technician/maintenance" class="btn btn-outline-secondary">Back</a>
                                <?php if ($isEdit && in_array($stageMode, ['reported', 'scheduled', 'in_progress'], true)): ?>
                                    <button type="submit" name="transition" value="cancelled" class="btn btn-outline-danger" onclick="return confirm('Cancel this maintenance case?')">Cancel Case</button>
                                <?php endif; ?>
                                <?php if ($isEdit && $stageMode === 'testing'): ?>
                                    <button type="submit" name="transition" value="in_progress" class="btn btn-outline-warning">Return To Repair</button>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-success" <?= $isEdit && $primaryTransition !== '' ? 'name="transition" value="' . esc($primaryTransition) . '"' : '' ?>><?= esc($submitLabel) ?></button>
                            </div>
                        <?php else: ?>
                            <div class="col-12 d-flex justify-content-end"><a href="/technician/maintenance" class="btn btn-outline-secondary">Back</a></div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <?php if (! empty($modelSummary['available']) && ! empty($assetPrediction)): ?>
                <?php
                    $riskClass = match ($assetPrediction['risk_band'] ?? 'low') {
                        'high' => 'text-bg-danger',
                        'medium' => 'text-bg-warning',
                        default => 'text-bg-success',
                    };
                ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white"><h6 class="mb-1">Predictive Maintenance Decision</h6></div>
                    <div class="card-body small text-muted">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold text-dark">Risk Score</span>
                            <span class="badge <?= esc($riskClass) ?>"><?= esc((int) ($assetPrediction['risk_percent'] ?? 0)) ?>%</span>
                        </div>
                        <div class="mb-2"><strong>Recommended Action:</strong> <?= esc($assetPrediction['decision']['label'] ?? 'Normal monitoring') ?></div>
                        <div class="mb-2"><strong>Priority:</strong> <?= esc(ucfirst((string) ($assetPrediction['decision']['priority'] ?? 'low'))) ?></div>
                        <?php if (! empty($assetPrediction['reasons'])): ?>
                            <div class="mb-0"><strong>Why:</strong>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($assetPrediction['reasons'] as $reason): ?>
                                        <li><?= esc($reason) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-1">Case Summary</h6></div>
                <div class="card-body small text-muted">
                    <div class="mb-2"><strong>Reporter:</strong> <?= esc($reporterName) ?></div>
                    <div class="mb-2"><strong>Current Step:</strong> <?= esc($statusLabel) ?></div>
                    <div class="mb-2"><strong>Category:</strong> <?= esc($issueTypeLabels[$record['issue_type'] ?? ''] ?? ucfirst((string) ($record['issue_type'] ?? ''))) ?></div>
                    <div class="mb-2"><strong>Affected Units:</strong> <?= esc((int) ($record['quantity_affected'] ?? 1)) ?></div>
                    <div class="mb-2"><strong>Unit Reference:</strong> <?= esc($record['unit_reference'] ?? 'Not specified') ?></div>
                    <div class="mb-2"><strong>Accepted At:</strong> <?= esc(!empty($record['accepted_at']) ? date('d M Y H:i', strtotime($record['accepted_at'])) : '-') ?></div>
                    <div class="mb-2"><strong>Scheduled For:</strong> <?= esc(!empty($record['scheduled_for']) ? date('d M Y H:i', strtotime($record['scheduled_for'])) : '-') ?></div>
                    <div class="mb-2"><strong>Tested At:</strong> <?= esc(!empty($record['tested_at']) ? date('d M Y H:i', strtotime($record['tested_at'])) : '-') ?></div>
                    <div class="mb-0"><strong>Completed At:</strong> <?= esc(!empty($record['completed_at']) ? date('d M Y H:i', strtotime($record['completed_at'])) : '-') ?></div>
                </div>
            </div>

            <?php if ($issuePhoto || $completionPhoto): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white"><h6 class="mb-1">Evidence</h6></div>
                    <div class="card-body small text-muted d-flex flex-column gap-3">
                        <?php if ($issuePhoto): ?><div><div class="fw-semibold mb-2">Reported Issue Photo</div><img src="<?= esc($issuePhoto) ?>" alt="Issue evidence" class="img-fluid rounded-3 border"></div><?php endif; ?>
                        <?php if ($completionPhoto): ?><div><div class="fw-semibold mb-2">Completion Photo</div><img src="<?= esc($completionPhoto) ?>" alt="Completion evidence" class="img-fluid rounded-3 border"></div><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-1">Simple Workflow</h6></div>
                <div class="card-body small text-muted">
                    <p class="mb-2"><strong>1. Reported / Planned</strong>: the issue or planned maintenance work is recorded.</p>
                    <p class="mb-2"><strong>2. Scheduled</strong>: the technician adds diagnosis and sets the maintenance time.</p>
                    <p class="mb-2"><strong>3. Repair In Progress</strong>: the technician records the repair or servicing work completed.</p>
                    <p class="mb-0"><strong>4. Testing / Completed</strong>: the technician verifies the equipment, uploads proof, and closes the case.</p>
                </div>
            </div>

            <?php if ($isEdit): ?>
                <div class="card border-0 shadow-sm"><div class="card-header bg-white"><h6 class="mb-1">Activity Log</h6></div><div class="card-body"><?php if (empty($logs)): ?><p class="text-muted small mb-0">No activity logged for this case yet.</p><?php else: ?><div class="d-flex flex-column gap-3"><?php foreach ($logs as $log): ?><div class="border rounded-3 p-3 bg-light-subtle"><div class="fw-semibold small text-dark"><?= esc($statusLabels[$log['to_status']] ?? ucwords(str_replace('_', ' ', $log['to_status'] ?? 'updated'))) ?></div><div class="small text-muted"><?= esc($log['full_name'] ?: $log['username'] ?: 'System') ?> | <?= esc(!empty($log['created_at']) ? date('d M Y H:i', strtotime($log['created_at'])) : '-') ?></div><?php if (!empty($log['notes'])): ?><div class="small mt-2"><?= esc($log['notes']) ?></div><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?></div></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

