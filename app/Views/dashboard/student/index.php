<?= $this->extend('layouts/main_user') ?>
<?= $this->section('content') ?>

<?php $filters = $filters ?? ['q' => '', 'status' => '', 'date_from' => '', 'date_to' => '']; ?>


<div class="container py-4">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-primary"><?= esc($dashboardLabel ?? 'Student Dashboard') ?></h2>
            <p class="text-muted mb-0 small">Welcome back, <?= esc($user->full_name ?? $user->username ?? 'User') ?>!</p>
        </div>
    </div>


    <!-- ======================= NEXT UPCOMING BOOKING ======================= -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold text-primary mb-1">
                            <i class="bi bi-calendar-event me-2"></i>Next Upcoming Booking
                        </h6>

                        <?php if ($nextBooking): ?>
                            <div class="small text-muted mb-1">
                                <?= esc($nextBooking['date']) ?> -
                                <?= esc($nextBooking['start_time']) ?> to <?= esc($nextBooking['end_time']) ?>
                            </div>
                            <div class="fw-semibold">
                                <?= esc($nextBooking['lab_name']) ?>
                                <?php if (! empty($nextBooking['lab_room'])): ?>
                                    <span class="text-muted small"> (Room <?= esc($nextBooking['lab_room']) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted mt-1">
                                <?= esc($nextBooking['activity']) ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small mb-1">You have no upcoming bookings.</p>
                            <a href="/laboratories" class="small">Browse laboratories &amp; make your first booking</a>
                        <?php endif; ?>
                    </div>

                    <div class="text-end">
                        <?php if ($nextBooking): ?>
                            <?php
                                $badge = [
                                    'PENDING'  => 'warning',
                                    'APPROVED' => 'success',
                                    'REJECTED' => 'danger',
                                    'CANCELLED' => 'secondary',
                                ][$nextBooking['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $badge ?> px-3 py-2">
                                <?= esc($nextBooking['status']) ?>
                            </span>
                        <?php else: ?>
                            <i class="bi bi-calendar-plus text-primary" style="font-size:2rem;"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOTAL SUMMARY -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <h6 class="fw-bold text-primary mb-2">
                        <i class="bi bi-graph-up-arrow me-2"></i>Overview
                    </h6>
                    <div class="d-flex justify-content-between mb-1 small">
                        <span>Pending</span>
                        <span class="fw-semibold text-warning"><?= esc($stats['pending']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1 small">
                        <span>Approved</span>
                        <span class="fw-semibold text-success"><?= esc($stats['approved']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1 small">
                        <span>Rejected</span>
                        <span class="fw-semibold text-danger"><?= esc($stats['rejected']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1 small">
                        <span>Cancelled</span>
                        <span class="fw-semibold text-secondary"><?= esc($stats['cancelled'] ?? 0) ?></span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between small">
                        <span>Total bookings</span>
                        <span class="fw-bold"><?= esc($stats['total']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================= KPI ROW ======================= -->
    <div class="row g-3 mb-4">

        <div class="col-md-3">
            <div class="kpi-card bg-primary">
                <div>
                    <h6 class="fw-semibold mb-1">Pending</h6>
                    <h2 class="fw-bold"><?= esc($stats['pending']) ?></h2>
                </div>
                <i class="bi bi-hourglass-split kpi-icon"></i>
            </div>
        </div>

        <div class="col-md-3">
            <div class="kpi-card bg-success">
                <div>
                    <h6 class="fw-semibold mb-1">Approved</h6>
                    <h2 class="fw-bold"><?= esc($stats['approved']) ?></h2>
                </div>
                <i class="bi bi-check-circle kpi-icon"></i>
            </div>
        </div>

        <div class="col-md-3">
            <div class="kpi-card bg-danger">
                <div>
                    <h6 class="fw-semibold mb-1">Rejected</h6>
                    <h2 class="fw-bold"><?= esc($stats['rejected']) ?></h2>
                </div>
                <i class="bi bi-x-circle kpi-icon"></i>
            </div>
        </div>

        <div class="col-md-3">
            <div class="kpi-card bg-secondary">
                <div>
                    <h6 class="fw-semibold mb-1">Cancelled</h6>
                    <h2 class="fw-bold"><?= esc($stats['cancelled'] ?? 0) ?></h2>
                </div>
                <i class="bi bi-slash-circle kpi-icon"></i>
            </div>
        </div>

    </div>

    <!-- ======================= QUICK ACTIONS ======================= -->
    <h5 class="fw-bold text-primary mb-3">Quick Actions</h5>

    <div class="row g-3 mb-4">

        <div class="col-md-6 col-xl-3">
            <a href="/laboratories" class="text-decoration-none text-dark">
                <div class="card quick-action-card p-3 shadow-sm border-0">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-building quick-action-icon"></i>
                        <div>
                            <a href="/laboratories" class="small">Browse laboratories &amp; make your first booking</a>
                            <p class="small text-muted mb-0">
                                View available labs & start booking.
                            </p>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-3">
            <a href="/assets" class="text-decoration-none text-dark">
                <div class="card quick-action-card p-3 shadow-sm border-0">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-hdd-stack quick-action-icon"></i>
                        <div>
                            <h6 class="fw-bold mb-1">View Equipment</h6>
                            <p class="small text-muted mb-0">
                                Explore lab equipment and quantities.
                            </p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-xl-3">
            <a href="/dashboard/report-issue" class="text-decoration-none text-dark">
                <div class="card quick-action-card p-3 shadow-sm border-0">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-tools quick-action-icon text-danger"></i>
                        <div>
                            <h6 class="fw-bold mb-1">Report Asset Issue</h6>
                            <p class="small text-muted mb-0">
                                Notify a technician about faulty or damaged equipment.
                            </p>
                        </div>
                    </div>
                </div>
            </a>
        </div>

    </div>
    <!-- ======================= PERSONALIZED HINTS ======================= -->
    <?php if (!empty($personalizedHints['lab_name']) || !empty($personalizedHints['slot'])): ?>
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h6 class="fw-bold text-primary mb-1">
                        <i class="bi bi-magic me-2"></i>Your Booking Patterns
                    </h6>
                    <div class="small text-muted">
                        Based on your approved bookings
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if (!empty($personalizedHints['lab_name'])): ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-2 px-3">
                            Most booked lab: <?= esc($personalizedHints['lab_name']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($personalizedHints['slot'])): ?>
                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 py-2 px-3">
                            Most booked slot: <?= esc($personalizedHints['slot']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ======================= CHART + TIMELINE ======================= -->
    <div class="row g-3 mb-4">

        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white border-0">
                    <h5 class="fw-bold text-primary mb-0">Booking Activity (Last 6 Months)</h5>
                </div>
                <div class="card-body">
                    <canvas id="studentTrendChart" height="120"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-primary mb-0">Upcoming Schedule</h6>
                    <?php if (!empty($upcomingBookings)): ?>
                        <span class="badge bg-soft-primary text-primary small">
                            <?= count($upcomingBookings) ?> upcoming
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card-body">

                    <?php if (empty($upcomingBookings)): ?>
                        <p class="text-muted small mb-0">No upcoming bookings found.</p>
                    <?php else: ?>
                        <ul class="timeline">
                            <?php foreach ($upcomingBookings as $ub): ?>
                                <li class="timeline-item">
                                    <div class="timeline-date">
                                        <?= esc($ub['date']) ?> -
                                        <?= esc($ub['start_time']) ?> to <?= esc($ub['end_time']) ?>
                                    </div>
                                    <div class="timeline-lab">
                                        <?= esc($ub['lab_name']) ?>
                                        <?php if (! empty($ub['lab_room'])): ?>
                                            <span class="text-muted small"> (<?= esc($ub['lab_room']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted"><?= esc($ub['activity']) ?></div>

                                    <?php
                                        $badge = [
                                            'PENDING'  => 'warning',
                                            'APPROVED' => 'success',
                                            'REJECTED' => 'danger',
                                            'CANCELLED' => 'secondary',
                                        ][$ub['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $badge ?> timeline-status mt-1">
                                        <?= esc($ub['status']) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                </div>
            </div>
        </div>

    </div>

    <!-- ======================= BOOKINGS TABLE ======================= -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-white border-0">
            <h5 class="fw-bold text-primary mb-0">Your Bookings</h5>
        </div>

        <div class="card-body">

            <!-- FILTER BAR -->
            <form method="get" action="/dashboard/student" class="mb-3 d-flex flex-wrap gap-2 align-items-end">
                <div>
                    <label class="small text-muted">Search</label>
                    <input type="text" name="q" id="filterSearch" class="form-control form-control-sm" value="<?= esc($filters['q']) ?>" placeholder="Lab, room, or activity">
                </div>

                <div>
                    <label class="small text-muted">Status</label>
                    <select id="filterStatus" name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="PENDING" <?= $filters['status'] === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                        <option value="APPROVED" <?= $filters['status'] === 'APPROVED' ? 'selected' : '' ?>>Approved</option>
                        <option value="REJECTED" <?= $filters['status'] === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
                        <option value="CANCELLED" <?= $filters['status'] === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div>
                    <label class="small text-muted">Start Date</label>
                    <input type="date" id="filterStart" name="date_from" class="form-control form-control-sm" value="<?= esc($filters['date_from']) ?>">
                </div>

                <div>
                    <label class="small text-muted">End Date</label>
                    <input type="date" id="filterEnd" name="date_to" class="form-control form-control-sm" value="<?= esc($filters['date_to']) ?>">
                </div>

                <button id="applyFilters" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel me-1"></i> Apply Filters
                </button>

                <a id="clearFilters" href="/dashboard/student" class="btn btn-outline-secondary btn-sm">
                    Reset
                </a>

            </form>

            <?php if (empty($bookings)): ?>

                <div class="text-center text-muted py-4">
                    <i class="bi bi-calendar-x fs-1"></i>
                    <p class="mt-2 mb-0">You have not made any bookings yet.</p>
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="table table-modern align-middle">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Lab</th>
                                <th>Activity</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($bookings as $b): ?>
                                <tr class="booking-row" data-id="<?= $b['id'] ?>">
                                    <td class="fw-semibold"><?= esc($b['date']) ?></td>
                                    <td><?= esc($b['start_time']) ?> - <?= esc($b['end_time']) ?></td>
                                    <td>
                                        <strong><?= esc($b['lab_name']) ?></strong><br>
                                        <small class="text-muted">Room <?= esc($b['lab_room']) ?></small>
                                    </td>
                                    <td><?= esc($b['activity']) ?></td>
                                    <td>
                                        <?php
                                            $badge = [
                                                'PENDING' => 'warning',
                                                'APPROVED' => 'success',
                                                'REJECTED' => 'danger',
                                                'CANCELLED' => 'secondary',
                                            ][$b['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badge ?> px-3 py-2">
                                            <?= esc($b['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </div>
    </div>

</div>

<!-- BOOKING DETAILS MODAL -->
<div class="modal fade" id="bookingDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content rounded-4 shadow-lg">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-journal-text me-2"></i>Booking Details
                </h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body" id="bookingDetailsBody">
                <p class="text-center text-muted">Loading...</p>
            </div>

            <div class="modal-footer border-0">
                <button id="rebookBtn" class="btn btn-primary">
                    <i class="bi bi-arrow-repeat me-1"></i>Rebook
                </button>

                <button id="cancelBookingBtn" class="btn btn-danger">
                    <i class="bi bi-trash me-1"></i>Cancel Booking
                </button>

                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>

<!-- ======================= CHART.JS ======================= -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const csrfHeaderName = "X-CSRF-TOKEN";
const csrfTokenValue = "<?= csrf_hash() ?>";

new Chart(document.getElementById('studentTrendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($monthlyCounts, 'month')) ?>,
        datasets: [{
            label: "Bookings",
            data: <?= json_encode(array_column($monthlyCounts, 'count')) ?>,
            borderColor: "#2563eb",
            backgroundColor: "rgba(37, 99, 235, 0.25)",
            borderWidth: 3,
            tension: 0.4,
            pointRadius: 5
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// ------------------------------------------------------
// OPEN DETAILS MODAL
// ------------------------------------------------------
document.querySelectorAll(".booking-row").forEach(row => {
    row.addEventListener("click", function () {

        const id = this.dataset.id;
        const modal = new bootstrap.Modal(document.getElementById("bookingDetailsModal"));
        const body = document.getElementById("bookingDetailsBody");

        body.innerHTML = "<p class='text-center text-muted'>Loading...</p>";
        modal.show();

        fetch(`/dashboard/student/booking-details/${id}`)
            .then(r => r.json())
            .then(res => {

                if (!res.success) {
                    body.innerHTML = `<div class="alert alert-danger">${res.message}</div>`;
                    return;
                }

                const b = res.booking;

                // Status badge
                const badgeColor = {
                    PENDING: "warning",
                    APPROVED: "success",
                    REJECTED: "danger",
                    CANCELLED: "secondary"
                }[b.status] ?? "secondary";

                let html = `
                    <h4 class="fw-bold text-primary">${b.lab_name}</h4>
                    <p class="text-muted small">Room ${b.lab_room ?? '-'}</p>

                    <div class="row mb-3">
                        <div class="col-md-6 col-xl-3"><strong>Date:</strong><br>${b.date}</div>
                        <div class="col-md-6 col-xl-3"><strong>Time:</strong><br>${b.start_time} - ${b.end_time}</div>
                        <div class="col-md-6 col-xl-3">
                            <strong>Status:</strong><br>
                            <span class="badge bg-${badgeColor} px-3 py-2">${b.status}</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <strong>Activity</strong>
                        <p>${b.activity}</p>
                    </div>

                    <div class="mb-3">
                        <strong>Supervisor</strong>
                        <p>
                            ${b.supervisor_name || "-"}<br>
                            ${b.supervisor_email || ""}<br>
                            ${b.supervisor_phone || ""}
                        </p>
                    </div>

                    <div class="mb-3">
                        <strong>Assets Used</strong>
                        <ul>
                `;

                res.assets.forEach(a => {
                    html += `<li>${a.name} - ${a.quantity_used} unit(s)</li>`;
                });

                html += `</ul></div>`;

                if (b.pdf_path) {
                    const pdfUrl = `/document/pdf/${encodeURIComponent(b.pdf_path.split('/').pop())}`;
                    html += `
                    <div class="mb-3">
                        <strong>PDF Attachment</strong><br>
                        <a href="${pdfUrl}" target="_blank" class="btn btn-outline-primary btn-sm mt-1">
                            View PDF
                        </a>
                    </div>`;
                }

                body.innerHTML = html;

                // Cancel Button
                const cancelBtn = document.getElementById("cancelBookingBtn");
                cancelBtn.style.display = (b.status === "PENDING") ? "inline-block" : "none";

                cancelBtn.onclick = () => {
                    if (!confirm("Are you sure you want to cancel this booking?")) return;

                    fetch(`/dashboard/student/cancel-booking/${b.id}`, {
                        method: "POST",
                        headers: {
                            "X-Requested-With": "XMLHttpRequest",
                            [csrfHeaderName]: csrfTokenValue
                        }
                    })
                    .then(r => r.json())
                    .then(resp => {
                        alert(resp.message);
                        if (resp.success) location.reload();
                    });
                };

                // Rebook
                document.getElementById("rebookBtn").onclick = () => {
                    window.location.href = `/laboratories/${b.lab_id}?rebook=${b.id}`;
                };

            });
    });
});


// Helper
function statusColor(status) {
    return {
        "PENDING": "warning",
        "APPROVED": "success",
        "REJECTED": "danger",
        "CANCELLED": "secondary",
    }[status] ?? "secondary";
}

// ------------------------------------------------------
// FILTER LOGIC
// ------------------------------------------------------
document.getElementById("applyFilters").addEventListener("click", function () {

    const status = document.getElementById("filterStatus").value;
    const start  = document.getElementById("filterStart").value;
    const end    = document.getElementById("filterEnd").value;
    const search = document.getElementById("filterSearch").value.toLowerCase().trim();

    document.querySelectorAll(".booking-row").forEach(row => {

        const rowStatus = row.querySelector("td:nth-child(5)").innerText.trim();
        const rowDate   = row.querySelector("td:nth-child(1)").innerText.trim();
        const rowText   = row.innerText.toLowerCase();

        let show = true;

        if (status && rowStatus !== status) show = false;
        if (search && !rowText.includes(search)) show = false;

        if (start && rowDate < start) show = false;
        if (end && rowDate > end) show = false;

        row.style.display = show ? "" : "none";
    });
});

document.getElementById("clearFilters").addEventListener("click", () => {
    document.getElementById("filterSearch").value = "";
    document.getElementById("filterStatus").value = "";
    document.getElementById("filterStart").value = "";
    document.getElementById("filterEnd").value = "";

    document.querySelectorAll(".booking-row").forEach(row => row.style.display = "");
});
</script>

<?= $this->endSection() ?>






