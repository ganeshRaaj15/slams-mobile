<?= $this->extend('layouts/main_user') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-primary mb-0">PIC Dashboard</h2>
        <p class="text-muted small">Manage booking requests for your assigned laboratories.</p>
        <?php if (!empty($labs)): ?>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <?php foreach ($labs as $lab): ?>
                    <span class="badge bg-light text-dark border">
                        <i class="bi bi-building me-1"></i><?= esc($lab['name']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="small text-muted mt-2">No assigned laboratories found.</div>
        <?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/dashboard/report-issue" class="btn btn-outline-danger btn-sm px-3 shadow-sm">
            <i class="bi bi-tools me-1"></i> Report Asset Issue
        </a>
        <a href="/dashboard/reports/pdf" class="btn btn-outline-primary btn-sm px-3 shadow-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i> Download Report
        </a>
        <a href="/dashboard/reports/csv" class="btn btn-outline-success btn-sm px-3 shadow-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
        </a>
    </div>
</div>

<!-- DASHBOARD WIDGETS -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card widget-card bg-gradient-primary text-white shadow-sm border-0">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <div class="small opacity-75 fw-semibold text-uppercase">Pending PIC</div>
                    <div class="fs-3 fw-bold"><?= esc($widget['pending']) ?></div>
                </div>
                <i class="bi bi-hourglass-split fs-1 opacity-75"></i>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card widget-card bg-gradient-warning text-white shadow-sm border-0">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <div class="small opacity-75 fw-semibold text-uppercase">Pending Manager</div>
                    <div class="fs-3 fw-bold"><?= esc($widget['pending_mgr']) ?></div>
                </div>
                <i class="bi bi-hourglass-top fs-1 opacity-75"></i>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card widget-card bg-gradient-success text-white shadow-sm border-0">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <div class="small opacity-75 fw-semibold text-uppercase">Approved</div>
                    <div class="fs-3 fw-bold"><?= esc($widget['approved']) ?></div>
                </div>
                <i class="bi bi-check-circle fs-1 opacity-75"></i>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card widget-card bg-gradient-danger text-white shadow-sm border-0">
            <div class="card-body d-flex align-items-center">
                <div class="flex-grow-1">
                    <div class="small opacity-75 fw-semibold text-uppercase">Rejected</div>
                    <div class="fs-3 fw-bold"><?= esc($widget['rejected']) ?></div>
                </div>
                <i class="bi bi-x-circle fs-1 opacity-75"></i>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold text-dark me-2">Booking Status Summary</span>
            <span class="badge rounded-pill bg-dark-subtle text-dark border">Total: <?= esc($widget['total'] ?? 0) ?></span>
            <span class="badge rounded-pill bg-warning-subtle text-warning border">Pending PIC: <?= esc($widget['pending'] ?? 0) ?></span>
            <span class="badge rounded-pill bg-primary-subtle text-primary border">Pending Manager: <?= esc($widget['pending_mgr'] ?? 0) ?></span>
            <span class="badge rounded-pill bg-success-subtle text-success border">Approved: <?= esc($widget['approved'] ?? 0) ?></span>
            <span class="badge rounded-pill bg-danger-subtle text-danger border">Rejected: <?= esc($widget['rejected'] ?? 0) ?></span>
            <span class="badge rounded-pill bg-secondary-subtle text-secondary border">Cancelled: <?= esc($widget['cancelled'] ?? 0) ?></span>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase">Open Maintenance</div>
                <div class="fs-3 fw-bold text-danger"><?= esc($maintenanceStats['open'] ?? 0) ?></div>
                <div class="small text-muted">Reported, scheduled, repair, or testing cases in your labs</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase">Completed Maintenance</div>
                <div class="fs-3 fw-bold text-success"><?= esc($maintenanceStats['completed'] ?? 0) ?></div>
                <div class="small text-muted">Resolved equipment issues in your labs</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted small fw-semibold text-uppercase">Upcoming Approved</div>
                <div class="fs-3 fw-bold text-primary"><?= esc($maintenanceStats['upcoming'] ?? 0) ?></div>
                <div class="small text-muted">Approved bookings in the next 7 days</div>
            </div>
        </div>
    </div>
</div>
<!-- EQUIPMENT RISK -->
<?php $equipmentRisk = $equipmentRisk ?? ['high' => 0, 'medium' => 0, 'low' => 0, 'topAtRisk' => []]; ?>
<?php if ($equipmentRisk['high'] + $equipmentRisk['medium'] + $equipmentRisk['low'] > 0): ?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-semibold text-dark mb-0">
                <i class="bi bi-graph-up-arrow me-2 text-warning"></i>Equipment Risk in Your Labs
            </h5>
            <small class="text-muted">Predicted maintenance needs for assets in your assigned laboratories.</small>
        </div>
        <a href="/technician/maintenance" class="btn btn-outline-warning btn-sm">View Maintenance</a>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-4">
                <div class="text-center p-3 rounded-3" style="background:var(--bs-danger-bg-subtle, #fff5f5);">
                    <div class="fs-3 fw-bold text-danger"><?= esc($equipmentRisk['high']) ?></div>
                    <div class="small fw-semibold text-danger">High Risk</div>
                    <div class="small text-muted">Schedule maintenance now</div>
                </div>
            </div>
            <div class="col-4">
                <div class="text-center p-3 rounded-3" style="background:var(--bs-warning-bg-subtle, #fffbf0);">
                    <div class="fs-3 fw-bold text-warning"><?= esc($equipmentRisk['medium']) ?></div>
                    <div class="small fw-semibold text-warning">Moderate Risk</div>
                    <div class="small text-muted">Inspect within 14 days</div>
                </div>
            </div>
            <div class="col-4">
                <div class="text-center p-3 rounded-3" style="background:var(--bs-success-bg-subtle, #f0fff4);">
                    <div class="fs-3 fw-bold text-success"><?= esc($equipmentRisk['low']) ?></div>
                    <div class="small fw-semibold text-success">Low Risk</div>
                    <div class="small text-muted">Normal monitoring</div>
                </div>
            </div>
        </div>

        <?php if (! empty($equipmentRisk['topAtRisk'])): ?>
            <div class="fw-semibold text-dark mb-2 small text-uppercase text-muted">Assets Needing Attention</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Equipment</th>
                            <th>Risk</th>
                            <th>Recommendation</th>
                            <th>Est. Due Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($equipmentRisk['topAtRisk'] as $atRisk): ?>
                            <?php
                            $rBand = $atRisk['risk_band'] ?? 'low';
                            $rBadge = match ($rBand) {
                                'high'   => 'text-bg-danger',
                                'medium' => 'text-bg-warning',
                                default  => 'text-bg-success',
                            };
                            $rDue = $atRisk['next_due_at'] ? date('d M Y', strtotime((string) $atRisk['next_due_at'])) : '-';
                            $rReason = $atRisk['reasons'][0] ?? null;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc($atRisk['name']) ?></div>
                                    <small class="text-muted"><?= esc($atRisk['lab_name']) ?></small>
                                    <?php if ($rReason): ?>
                                        <div class="small text-muted fst-italic mt-1"><?= esc($rReason) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= esc($rBadge) ?>"><?= esc($atRisk['risk_percent']) ?>%</span></td>
                                <td><?= esc($atRisk['decision_label']) ?></td>
                                <td><?= esc($rDue) ?></td>
                                <td class="text-end">
                                    <a href="/technician/maintenance/create?asset_id=<?= esc($atRisk['asset_id']) ?>"
                                       class="btn btn-outline-primary btn-sm">Plan</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- CHARTS SECTION -->
<div class="row mb-4">
    <?php if (!empty($monthlyCounts)): ?>
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white">
                <h6 class="fw-semibold text-primary mb-0">
                    <i class="bi bi-calendar-week me-2"></i>Monthly Booking Trends
                </h6>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($facultyCounts)): ?>
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white">
                <h6 class="fw-semibold text-primary mb-0">
                    <i class="bi bi-building me-2"></i>Top Faculties
                </h6>
            </div>
            <div class="card-body">
                <canvas id="facultyChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- USAGE PATTERNS -->
<?php if (!empty($usageData['dayOfWeek']) || !empty($usageData['timeSlots'])): ?>
<div class="row mb-4">
    <?php if (!empty($usageData['dayOfWeek'])): ?>
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h6 class="fw-semibold text-primary mb-0">
                    <i class="bi bi-bar-chart me-2"></i>Usage by Day of Week
                </h6>
            </div>
            <div class="card-body">
                <canvas id="dayChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($usageData['timeSlots'])): ?>
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h6 class="fw-semibold text-primary mb-0">
                    <i class="bi bi-clock me-2"></i>Popular Time Slots
                </h6>
            </div>
            <div class="card-body">
                <canvas id="timeChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- BOOKINGS AWAITING PIC APPROVAL -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="fw-semibold text-primary mb-0">
            <i class="bi bi-clipboard-check me-2"></i>Bookings Awaiting PIC Approval
        </h5>
        <span class="badge bg-primary"><?= esc($widget['pending']) ?> requests</span>
    </div>

    <div class="card-body">
        <?php if (empty($pendingPic)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-check-circle fs-1"></i>
                <p class="mt-2">No pending PIC approval requests.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Lab</th>
                            <th>Date & Time</th>
                            <th>Activity</th>
                            <th>Faculty</th>
                            <th>Assets</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($pendingPic as $b): ?>
                            <tr id="row-<?= $b['id'] ?>">
                                <td>
                                    <strong><?= esc($b['lab_name']) ?></strong><br>
                                    <small class="text-muted">Room <?= esc($b['lab_room']) ?></small>
                                </td>

                                <td>
                                    <div class="fw-semibold"><?= esc($b['date']) ?></div>
                                    <small class="text-muted"><?= esc($b['start_time']) ?> to <?= esc($b['end_time']) ?></small>
                                </td>
                                
                                <td>
                                    <div class="text-truncate" style="max-width: 200px;" 
                                         title="<?= esc($b['activity']) ?>">
                                        <?= esc($b['activity']) ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <?php if ($b['is_fkmp']): ?>
                                        <span class="badge bg-success">FKMP</span>
                                    <?php else: ?>
                                        <span class="badge bg-info"><?= esc($b['faculty_name'] ?? 'Other') ?></span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <?php if (!empty($b['assets'])): ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($b['assets'] as $asset): ?>
                                                <span class="badge bg-light text-dark border">
                                                    <?= esc($asset['name']) ?> (x<?= $asset['quantity_used'] ?>)
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">No assets</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end">
                                    <button class="btn btn-outline-primary btn-sm px-3"
                                            onclick="viewBookingDetails(<?= $b['id'] ?>)"
                                            data-bs-toggle="tooltip" title="View Details">
                                        <i class="bi bi-eye me-1"></i>View
                                    </button>
                                    
                                    <button class="btn btn-success btn-sm px-3"
                                            onclick="approveBooking(<?= $b['id'] ?>)"
                                            data-bs-toggle="tooltip" title="Approve">
                                        <i class="bi bi-check-lg"></i>
                                    </button>

                                    <button class="btn btn-danger btn-sm px-3"
                                            onclick="rejectBooking(<?= $b['id'] ?>)"
                                            data-bs-toggle="tooltip" title="Reject">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL FOR BOOKING DETAILS -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Booking Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bookingDetails">
                <!-- Content loaded via AJAX -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger btn-sm px-3" id="btnRejectModal" onclick="rejectBookingModal()">
                    <i class="bi bi-x-lg me-1"></i>Reject
                </button>
                <button type="button" class="btn btn-success btn-sm px-3" id="btnApproveModal" onclick="approveBookingModal()">
                    <i class="bi bi-check-lg me-1"></i>Approve
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let currentBookingId = null;
const csrfHeaderName = "X-CSRF-TOKEN";
const csrfTokenValue = "<?= csrf_hash() ?>";

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Initialize charts
    initializeCharts();
});

function viewBookingDetails(id) {
    currentBookingId = id;
    const modalElement = document.getElementById('bookingModal');
    if (window.slamsPrepareModal) {
        window.slamsPrepareModal(modalElement);
    } else if (modalElement && modalElement.parentElement !== document.body) {
        document.body.appendChild(modalElement);
    }
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    
    // Show loading
    document.getElementById('bookingDetails').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    // Fetch booking details
    fetch(`/dashboard/pic/booking/${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                displayBookingDetails(data.booking);
                modal.show();
            } else {
                alert(data.message);
            }
        });
}

function displayBookingDetails(booking) {
    const container = document.getElementById('bookingDetails');
    
    const isFkmp = booking.is_fkmp == 1;
    
    // Assets list
    let assetsHtml = '<p class="text-muted mb-0">No assets selected</p>';
    if (booking.assets && booking.assets.length > 0) {
        assetsHtml = '<div class="row g-2">';
        booking.assets.forEach(asset => {
            assetsHtml += `
                <div class="col-md-6">
                    <div class="card border">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                ${asset.image ? `<img src="${asset.image}" class="rounded me-3" width="40" height="40" alt="${asset.name}">` : ''}
                                <div>
                                    <h6 class="mb-0">${asset.name}</h6>
                                    <small class="text-muted">Quantity: ${asset.quantity_used}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        assetsHtml += '</div>';
    }
    
    // PDF link
    let pdfHtml = '';
    if (booking.pdf_url) {
    pdfHtml = `
        <div class="mt-3">
            <a href="${booking.pdf_url}" target="_blank" class="btn btn-outline-primary btn-sm px-3 shadow-sm">
                <i class="bi bi-file-pdf me-1"></i>View Uploaded PDF
            </a>
        </div>
    `;
}
    
    container.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="fw-semibold">Laboratory Information</h6>
                <div class="card border mb-3">
                    <div class="card-body">
                        <h5 class="card-title">${booking.lab_name}</h5>
                        <p class="card-text mb-1"><i class="bi bi-geo-alt me-2"></i>Room ${booking.lab_room}</p>
                        <p class="card-text mb-1"><i class="bi bi-person me-2"></i>PIC: ${booking.pic_name}</p>
                        <p class="card-text"><i class="bi bi-envelope me-2"></i>${booking.pic_email}</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <h6 class="fw-semibold">Booking Details</h6>
                <div class="card border mb-3">
                    <div class="card-body">
                        <p class="mb-2"><strong>Date:</strong> ${booking.date}</p>
                        <p class="mb-2"><strong>Time:</strong> ${booking.start_time} - ${booking.end_time}</p>
                        <p class="mb-2"><strong>Faculty:</strong> ${booking.faculty_name} ${isFkmp ? '(FKMP)' : ''}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <h6 class="fw-semibold">Activity Description</h6>
                <div class="card border mb-3">
                    <div class="card-body">
                        <p>${booking.activity}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <h6 class="fw-semibold">Supervisor Information</h6>
                <div class="card border mb-3">
                    <div class="card-body">
                        <p class="mb-1"><strong>Name:</strong> ${booking.supervisor_name || 'Not provided'}</p>
                        <p class="mb-1"><strong>Email:</strong> ${booking.supervisor_email || 'Not provided'}</p>
                        <p class="mb-0"><strong>Phone:</strong> ${booking.supervisor_phone || 'Not provided'}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <h6 class="fw-semibold">Requested Assets</h6>
                <div class="card border mb-3">
                    <div class="card-body">
                        ${assetsHtml}
                    </div>
                </div>
            </div>
        </div>
        
        ${pdfHtml}
    `;
}

function approveBookingModal() {
    if (currentBookingId) {
        approveBooking(currentBookingId);
        bootstrap.Modal.getInstance(document.getElementById('bookingModal')).hide();
    }
}

function rejectBookingModal() {
    if (currentBookingId) {
        rejectBooking(currentBookingId);
        bootstrap.Modal.getInstance(document.getElementById('bookingModal')).hide();
    }
}

function approveBooking(id) {
    if (!confirm("Approve this booking?")) return;

    fetch(`/booking/approve/${id}`, {
        method:"POST",
        headers: {
            "X-Requested-With":"XMLHttpRequest",
            [csrfHeaderName]: csrfTokenValue
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === "success") {
            document.getElementById(`row-${id}`).remove();
            location.reload(); // Reload to update charts
        } else alert(data.message);
    });
}

function rejectBooking(id) {
    if (!confirm("Reject this booking?")) return;

    fetch(`/booking/reject/${id}`, {
        method:"POST",
        headers: {
            "X-Requested-With":"XMLHttpRequest",
            [csrfHeaderName]: csrfTokenValue
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === "success") {
            document.getElementById(`row-${id}`).remove();
            location.reload(); // Reload to update charts
        } else alert(data.message);
    });
}

function initializeCharts() {
    <?php if (!empty($monthlyCounts)): ?>
    // Monthly Chart
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($monthlyCounts, 'month')) ?>,
            datasets: [{
                label: 'Bookings',
                data: <?= json_encode(array_column($monthlyCounts, 'count')) ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            }
        }
    });
    <?php endif; ?>
    
    <?php if (!empty($facultyCounts)): ?>
    // Faculty Chart
    const facultyCtx = document.getElementById('facultyChart').getContext('2d');
    new Chart(facultyCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($facultyCounts, 'faculty')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($facultyCounts, 'count')) ?>,
                backgroundColor: [
                    '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
    <?php endif; ?>
    
    <?php if (!empty($usageData['dayOfWeek'])): ?>
    // Day of Week Chart
    const dayCtx = document.getElementById('dayChart').getContext('2d');
    new Chart(dayCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($usageData['dayOfWeek'], 'day')) ?>,
            datasets: [{
                label: 'Bookings',
                data: <?= json_encode(array_column($usageData['dayOfWeek'], 'count')) ?>,
                backgroundColor: '#3b82f6'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
    <?php endif; ?>
    
    <?php if (!empty($usageData['timeSlots'])): ?>
    // Time Slots Chart
    const timeCtx = document.getElementById('timeChart').getContext('2d');
    new Chart(timeCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($usageData['timeSlots'], 'slot')) ?>,
            datasets: [{
                label: 'Bookings',
                data: <?= json_encode(array_column($usageData['timeSlots'], 'count')) ?>,
                backgroundColor: '#10b981'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
    <?php endif; ?>
}
</script>


<?= $this->endSection() ?>


