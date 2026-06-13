<?= $this->extend('layouts/main_admin') ?>
<?= $this->section('content') ?>

<?php
$isEdit = ($mode ?? 'create') === 'edit';
$reservation = $reservation ?? [];
?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= $isEdit ? 'Edit Full-Lab Reservation' : 'Create Full-Lab Reservation' ?></h1>
            <p class="text-muted mb-0">This blocks the entire laboratory for the selected time range, unlike normal service bookings.</p>
        </div>
        <a href="/admin/reservations" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-2"></i>Back</a>
    </div>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger border-0 shadow-sm"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-1">Reservation Details</h5></div>
                <div class="card-body">
                    <form action="<?= $isEdit ? '/admin/reservations/update/' . (int) ($reservation['id'] ?? 0) : '/admin/reservations/store' ?>" method="post" class="row g-3">
                        <?= csrf_field() ?>
                        <div class="col-md-6">
                            <label class="form-label">Laboratory</label>
                            <select name="lab_id" class="form-select" required>
                                <option value="">Select laboratory</option>
                                <?php foreach (($labs ?? []) as $lab): ?>
                                    <option value="<?= esc($lab['id']) ?>" <?= (string) old('lab_id', $reservation['lab_id'] ?? '') === (string) $lab['id'] ? 'selected' : '' ?>>
                                        <?= esc($lab['name']) ?><?= ! empty($lab['room']) ? ' - ' . esc($lab['room']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reservation Type</label>
                            <?php $type = old('reservation_type', $reservation['reservation_type'] ?? 'reservation'); ?>
                            <select name="reservation_type" class="form-select">
                                <?php foreach (['reservation' => 'Reservation', 'walk_in' => 'Walk In', 'class' => 'Class', 'event' => 'Event', 'maintenance' => 'Maintenance'] as $value => $label): ?>
                                    <option value="<?= esc($value) ?>" <?= $type === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" value="<?= esc(old('title', $reservation['title'] ?? '')) ?>" required>
                        </div>
                        <?php
                        $startAt = old('start_at', $reservation['start_at'] ?? '');
                        $endAt = old('end_at', $reservation['end_at'] ?? '');
                        $startDate = $startAt ? date('Y-m-d', strtotime($startAt)) : '';
                        $startTime = $startAt ? date('H:i', strtotime($startAt)) : '';
                        $endDate = $endAt ? date('Y-m-d', strtotime($endAt)) : '';
                        $endTime = $endAt ? date('H:i', strtotime($endAt)) : '';
                        ?>
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= esc($startDate) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Time</label>
                            <input type="time" name="start_time" class="form-control" value="<?= esc($startTime) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?= esc($endDate) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time" class="form-control" value="<?= esc($endTime) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <?php $status = old('status', $reservation['status'] ?? 'active'); ?>
                            <select name="status" class="form-select">
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="4"><?= esc(old('notes', $reservation['notes'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2">
                            <a href="/admin/reservations" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Reservation' : 'Create Reservation' ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
