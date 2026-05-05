<?= $this->extend('layouts/main_user'); ?>
<?= $this->section('content'); ?>

<div class="container py-4">

    <h3 class="fw-bold mb-4 text-primary">
        <i class="bi bi-journal-bookmark-fill me-2"></i> My Booking History
    </h3>

    <?php if (empty($bookings)): ?>
        <div class="alert alert-info">No bookings found.</div>
    <?php else: ?>

        <div class="table-responsive fkmp-card-soft p-3">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time Slot</th>
                        <th>Lab</th>
                        <th>Status</th>
                        <th>PDF</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($b['date'])) ?></td>

                            <td><?= date('h:i A', strtotime($b['start_time'])) ?> &ndash;
                                <?= date('h:i A', strtotime($b['end_time'])) ?></td>

                            <td><?= $b['lab_name'] ?></td>

                            <td>
                                <?php $status = strtoupper((string) ($b['status'] ?? 'PENDING')); ?>
                                <?php if ($status === 'APPROVED'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php elseif ($status === 'REJECTED'): ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php elseif ($status === 'CANCELLED'): ?>
                                    <span class="badge bg-secondary">Cancelled</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($b['pdf_path']): ?>
                                    <a href="<?= site_url('document/pdf/' . basename($b['pdf_path'])) ?>" target="_blank"
                                       class="btn btn-sm btn-outline-primary">
                                       <i class="bi bi-file-earmark-pdf"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">No file</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <button class="btn btn-sm btn-fkmp" onclick="openBookingDetails(<?= $b['id'] ?>)">
                                    View Details
                                </button>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>

<?= $this->include('bookings/components/booking_details_modal'); ?>

<script>
function openBookingDetails(id) {

    fetch('/api/bookings/details/' + id)
        .then(r => r.json())
        .then(data => {

            if (data.status !== 'success') return;

            document.getElementById('detailLab').innerText = data.booking.lab_name + " (" + data.booking.room + ")";
            document.getElementById('detailDate').innerText = data.booking.date;
            document.getElementById('detailTime').innerText =
                data.booking.start_time + " - " + data.booking.end_time;

            /* Applicants */
            const appList = document.getElementById('detailApplicants');
            appList.innerHTML = "";
            data.applicants.forEach(a => {
                appList.innerHTML += `
                    <li>${a.name} &ndash; ${a.matric_id} (${a.email})</li>
                `;
            });

            /* Assets */
            const assetList = document.getElementById('detailAssets');
            assetList.innerHTML = "";
            if (data.assets.length === 0) {
                assetList.innerHTML = "<li>No assets used</li>";
            } else {
                data.assets.forEach(a => {
                    assetList.innerHTML += `<li>${a.asset_name} (Qty: ${a.quantity_used})</li>`;
                });
            }

            new bootstrap.Modal(document.getElementById('bookingDetailsModal')).show();
        });
}
</script>

<?= $this->endSection(); ?>
