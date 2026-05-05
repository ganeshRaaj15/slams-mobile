<!-- table_approved.php -->

<?php if (empty($approved)): ?>
    <div class="alert alert-success">No approved bookings yet.</div>
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
            <th>PIC</th>
            <th>Manager</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($approved as $b): ?>
        <tr>
            <td><?= esc($b['lab_name']) ?></td>
            <td><?= esc($b['date']) ?></td>
            <td><?= esc(substr($b['start_time'], 0, 5)) ?> - <?= esc(substr($b['end_time'], 0, 5)) ?></td>
            <td><?= esc($b['activity']) ?></td>
            <td><?= esc($b['user_type']) ?></td>
            <td><?= esc($b['faculty_name']) ?></td>
            <td>
                <?php if ($b['approved_by_pic']): ?>
                    <span class="badge bg-success">Yes</span>
                <?php else: ?>
                    <span class="badge bg-secondary">No</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($b['approved_by_manager']): ?>
                    <span class="badge bg-success">Yes</span>
                <?php else: ?>
                    <span class="badge bg-secondary">No</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>
<?php endif; ?>
