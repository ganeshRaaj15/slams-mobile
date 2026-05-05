<!-- table_pending_manager.php -->

<?php if (empty($pendingMgr)): ?>
    <div class="alert alert-info">No bookings waiting for Manager approval.</div>
<?php else: ?>
<table class="table table-hover align-middle">
    <thead class="table-light">
        <tr>
            <th>Lab</th>
            <th>Date</th>
            <th>Time</th>
            <th>Activity</th>
            <th>Faculty</th>
            <th>PIC Approved</th>
            <th class="text-end">Actions</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($pendingMgr as $b): ?>
        <tr>
            <td><?= esc($b['lab_name']) ?></td>
            <td><?= esc($b['date']) ?></td>
            <td><?= esc(substr($b['start_time'], 0, 5)) ?> - <?= esc(substr($b['end_time'], 0, 5)) ?></td>
            <td><?= esc($b['activity']) ?></td>
            <td><?= esc($b['faculty_name']) ?></td>
            <td><span class="badge bg-success">Yes</span></td>

            <td class="text-end">
                <form action="/booking/approve/<?= $b['id'] ?>" method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-success btn-sm">
                        <i class="bi bi-check2-circle"></i> Approve
                    </button>
                </form>

                <form action="/booking/reject/<?= $b['id'] ?>" method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-danger btn-sm">
                        <i class="bi bi-x-circle"></i> Reject
                    </button>
                </form>
            </td>

        </tr>
        <?php endforeach ?>
    </tbody>
</table>
<?php endif; ?>
