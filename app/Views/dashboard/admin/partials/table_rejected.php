<!-- table_rejected.php -->

<?php if (empty($rejected)): ?>
    <div class="alert alert-secondary">No rejected bookings.</div>
<?php else: ?>
<table class="table table-hover align-middle">
    <thead class="table-light">
        <tr>
            <th>Lab</th>
            <th>Date</th>
            <th>Time</th>
            <th>Activity</th>
            <th>User Type</th>
            <th>Faculty</th>
            <th>Status</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($rejected as $b): ?>
        <tr>
            <td><?= esc($b['lab_name']) ?></td>
            <td><?= esc($b['date']) ?></td>
            <td><?= esc(substr($b['start_time'], 0, 5)) ?> - <?= esc(substr($b['end_time'], 0, 5)) ?></td>
            <td><?= esc($b['activity']) ?></td>
            <td><?= esc($b['user_type']) ?></td>
            <td><?= esc($b['faculty_name']) ?></td>
            <td><span class="badge bg-danger">Rejected</span></td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>
<?php endif; ?>
