<?= $this->extend('layouts/main_technician') ?>
<?= $this->section('content') ?>
<div class="container-fluid">
    <?php if (session()->getFlashdata('success')): ?><div class="alert alert-success border-0 shadow-sm"><?= esc(session()->getFlashdata('success')) ?></div><?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger border-0 shadow-sm"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small text-uppercase mb-2">Open Cases</div><div class="display-6 fw-bold text-dark"><?= esc($stats['open_total']) ?></div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small text-uppercase mb-2">Assigned To Me</div><div class="display-6 fw-bold text-dark"><?= esc($stats['assigned_to_me']) ?></div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small text-uppercase mb-2">Awaiting Test</div><div class="display-6 fw-bold text-dark"><?= esc($stats['awaiting_test']) ?></div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small text-uppercase mb-2">Completed This Month</div><div class="display-6 fw-bold text-dark"><?= esc($stats['completed_this_month']) ?></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div><h5 class="mb-1">Recent Maintenance Activity</h5><small class="text-muted">Open each case to move it through the guided maintenance workflow.</small></div>
            <a href="/technician/maintenance" class="btn btn-outline-success btn-sm">View All</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th>Asset</th><th>Laboratory</th><th>Stage</th><th>Priority</th><th>Scheduled</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                        <?php if (empty($recentRecords)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No maintenance records available yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentRecords as $record): ?>
                                <tr>
                                    <td><div class="fw-semibold"><?= esc($record['asset_name'] ?? 'Unknown asset') ?></div><small class="text-muted">#<?= esc($record['id']) ?> <?= esc($record['title']) ?></small></td>
                                    <td><?= esc($record['laboratory_name'] ?? '-') ?></td>
                                    <td><span class="badge text-bg-secondary"><?= esc($statusLabels[$record['status']] ?? ucwords(str_replace('_', ' ', $record['status']))) ?></span></td>
                                    <td><span class="badge text-bg-light border text-uppercase"><?= esc($record['priority']) ?></span></td>
                                    <td><?= esc($record['scheduled_for'] ? date('d M Y H:i', strtotime($record['scheduled_for'])) : '-') ?></td>
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
