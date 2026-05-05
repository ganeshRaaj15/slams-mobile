<?= $this->extend('layouts/main_technician') ?>
<?= $this->section('content') ?>
<div class="container-fluid">
    <?php if (session()->getFlashdata('success')): ?><div class="alert alert-success border-0 shadow-sm"><?= esc(session()->getFlashdata('success')) ?></div><?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger border-0 shadow-sm"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>

    <?php if (! empty($modelSummary['available'])): ?>
        <?php
            $metrics = $modelSummary['metrics'] ?? [];
            $dataset = $modelSummary['dataset'] ?? [];
            $trainedAt = ! empty($modelSummary['trained_at']) ? date('d M Y H:i', strtotime((string) $modelSummary['trained_at'])) : '-';
        ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <h6 class="mb-1">Local Predictive Maintenance Model</h6>
                    <small class="text-muted">Runs entirely on the server using local maintenance history. Last trained: <?= esc($trainedAt) ?></small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge text-bg-light border">Accuracy <?= esc(number_format(((float) ($metrics['accuracy'] ?? 0.0)) * 100, 1)) ?>%</span>
                    <span class="badge text-bg-light border">Precision <?= esc(number_format(((float) ($metrics['precision'] ?? 0.0)) * 100, 1)) ?>%</span>
                    <span class="badge text-bg-light border">Recall <?= esc(number_format(((float) ($metrics['recall'] ?? 0.0)) * 100, 1)) ?>%</span>
                    <span class="badge text-bg-light border">Samples <?= esc((int) ($dataset['samples_total'] ?? 0)) ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label">Workflow Stage</label><select name="status" class="form-select"><option value="">All stages</option><?php foreach ($statusOptions as $status): ?><option value="<?= esc($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= esc($statusLabels[$status] ?? ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Asset</label><select name="asset_id" class="form-select"><option value="0">All assets</option><?php foreach ($assets as $asset): ?><option value="<?= esc($asset['id']) ?>" <?= (int) $filters['asset_id'] === (int) $asset['id'] ? 'selected' : '' ?>><?= esc($asset['name']) ?><?= !empty($asset['lab_name']) ? ' - ' . esc($asset['lab_name']) : '' ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Scope</label><select name="scope" class="form-select"><option value="">All records</option><option value="mine" <?= $filters['scope'] === 'mine' ? 'selected' : '' ?>>Assigned to me</option></select></div>
                <div class="col-md-2 d-grid"><button type="submit" class="btn btn-success">Filter</button></div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-1">Predictive Maintenance Decisions</h6>
                <small class="text-muted">Risk scores and recommended actions based on the local maintenance model and completed planned-maintenance history.</small>
            </div>
            <span class="badge text-bg-light border">Next 90 days</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($upcomingForecasts)): ?>
                <div class="p-4 text-muted">No assets currently require predictive maintenance action in the next 90 days.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Asset</th>
                                <th>Risk</th>
                                <th>System Decision</th>
                                <th>Due Date</th>
                                <th>Last Completed</th>
                                <th>Reason</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingForecasts as $forecast): ?>
                                <?php
                                    $nextDueRaw = $forecast['next_due_at'] ?? '';
                                    $nextDueLabel = $nextDueRaw ? date('d M Y', strtotime($nextDueRaw)) : '-';
                                    $lastCompletedRaw = $forecast['last_completed_at'] ?? '';
                                    $lastCompletedLabel = $lastCompletedRaw ? date('d M Y', strtotime($lastCompletedRaw)) : '-';
                                    $intervalDays = (int) ($forecast['interval_days'] ?? 0);
                                    $months = $intervalDays > 0 ? max((int) round($intervalDays / 30), 1) : 0;
                                    $cycleLabel = $intervalDays > 0
                                        ? ($forecast['basis'] === 'average' ? 'Avg ' : 'Default ') . '~' . $months . ' mo'
                                        : '-';
                                    $daysUntil = (int) ($forecast['days_until'] ?? 0);
                                    $statusText = $daysUntil < 0
                                        ? 'Overdue by ' . abs($daysUntil) . ' day(s)'
                                        : 'Due in ' . $daysUntil . ' day(s)';
                                    $statusClass = $daysUntil < 0 ? 'text-bg-danger' : 'text-bg-warning';
                                    $scheduledFor = $nextDueRaw ? date('Y-m-d\\T09:00', strtotime($nextDueRaw)) : '';
                                    if ($scheduledFor === '') {
                                        $scheduledFor = date('Y-m-d\\T09:00', strtotime('+7 days'));
                                    }
                                    $recommendedPriority = $forecast['decision_priority'] ?? 'medium';
                                    $planQuery = http_build_query([
                                        'asset_id' => $forecast['asset_id'] ?? '',
                                        'scheduled_for' => $scheduledFor,
                                        'issue_type' => 'preventive',
                                        'title' => 'Preventive Maintenance - ' . ($forecast['name'] ?? 'Equipment'),
                                        'priority' => $recommendedPriority === 'high' ? 'high' : 'medium',
                                        'quantity_affected' => 1,
                                    ], '', '&', PHP_QUERY_RFC3986);
                                    $riskClass = match ($forecast['risk_band'] ?? 'low') {
                                        'high' => 'text-bg-danger',
                                        'medium' => 'text-bg-warning',
                                        default => 'text-bg-success',
                                    };
                                    $reasonText = implode(' ', array_slice($forecast['reasons'] ?? [], 0, 1));
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= esc($forecast['name'] ?? '-') ?></div>
                                        <small class="text-muted"><?= esc($forecast['lab_name'] ?? '-') ?></small>
                                    </td>
                                    <td><span class="badge <?= esc($riskClass) ?>"><?= esc((int) ($forecast['risk_percent'] ?? 0)) ?>%</span></td>
                                    <td>
                                        <div class="fw-semibold"><?= esc($forecast['decision_label'] ?? 'Normal monitoring') ?></div>
                                        <small class="text-muted text-uppercase"><?= esc($forecast['decision_priority'] ?? 'low') ?> priority</small>
                                    </td>
                                    <td><?= esc($nextDueLabel) ?></td>
                                    <td><?= esc($lastCompletedLabel) ?></td>
                                    <td>
                                        <?php if ($reasonText !== ''): ?>
                                            <div class="small"><?= esc($reasonText) ?></div>
                                        <?php else: ?>
                                            <span class="badge <?= esc($statusClass) ?>"><?= esc($statusText) ?></span>
                                        <?php endif; ?>
                                        <?php if ($intervalDays > 0): ?>
                                            <div class="small text-muted mt-1"><?= esc($cycleLabel) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/technician/maintenance/create?<?= esc($planQuery) ?>">Plan</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div><h5 class="mb-1">Maintenance Workflow</h5><small class="text-muted">Each case is completed step by step: first schedule and diagnose, then record repair work, then test and close with evidence.</small></div>
            <a href="/technician/maintenance/create" class="btn btn-success btn-sm"><i class="bi bi-plus-circle"></i> New Planned Maintenance</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th>Case</th><th>Asset</th><th>Stage</th><th>Priority</th><th>Reporter / Unit</th><th>Updated</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No maintenance records matched the selected filters.</td></tr>
                        <?php else: ?>
                            <?php foreach ($records as $record): ?>
                                <?php $label = $statusLabels[$record['status']] ?? ucwords(str_replace('_', ' ', $record['status'])); ?>
                                <tr>
                                    <td><div class="fw-semibold"><?= esc($record['title']) ?></div><small class="text-muted">#<?= esc($record['id']) ?> | <?= esc(ucfirst($record['issue_type'])) ?></small></td>
                                    <td><div><?= esc($record['asset_name'] ?? '-') ?></div><small class="text-muted"><?= esc($record['laboratory_name'] ?? '-') ?></small></td>
                                    <td><span class="badge text-bg-secondary"><?= esc($label) ?></span></td>
                                    <td><span class="badge text-bg-light border text-uppercase"><?= esc($record['priority']) ?></span></td>
                                    <td>
                                        <div class="small"><?= esc($record['reported_by_name'] ?: $record['reported_by_username'] ?: 'System') ?></div>
                                        <div class="small text-muted"><?= esc($record['unit_reference'] ?: 'No unit reference') ?></div>
                                    </td>
                                    <td><?= esc($record['updated_at'] ? date('d M Y H:i', strtotime($record['updated_at'])) : '-') ?></td>
                                    <td class="text-end"><a href="/technician/maintenance/edit/<?= esc($record['id']) ?>" class="btn btn-sm btn-outline-primary">Open Case</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
