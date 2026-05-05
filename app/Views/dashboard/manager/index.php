<?= $this->extend('layouts/main_user') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-primary mb-0">Lab Manager Dashboard</h2>
        <p class="text-muted small">Comprehensive overview and management tools for all laboratories.</p>
    </div>
    <a href="/dashboard/reports/pdf" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-file-earmark-pdf me-1"></i> Download Report
    </a>
    <a href="/dashboard/reports/csv" class="btn btn-outline-success btn-sm">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
    </a>
</div>

<!-- QUICK STATS CARDS -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card stats-card bg-white border-0 shadow-sm rounded-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase">Total Bookings</div>
                        <div class="fs-3 fw-bold text-primary"><?= esc($stats['total']) ?></div>
                    </div>
                    <i class="bi bi-calendar-check fs-2 text-primary opacity-75"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stats-card bg-white border-0 shadow-sm rounded-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase">This Week</div>
                        <div class="fs-3 fw-bold text-success"><?= esc($stats['currentWeek']) ?></div>
                        <div class="small <?= $stats['weekGrowth'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <i class="bi bi-arrow-<?= $stats['weekGrowth'] >= 0 ? 'up' : 'down' ?> me-1"></i>
                            <?= abs($stats['weekGrowth']) ?>%
                        </div>
                    </div>
                    <i class="bi bi-graph-up-arrow fs-2 text-success opacity-75"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stats-card bg-white border-0 shadow-sm rounded-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase">Pending Approval</div>
                        <div class="fs-3 fw-bold text-warning"><?= esc($stats['pendingManager']) ?></div>
                        <div class="small text-muted">Non-FKMP bookings</div>
                    </div>
                    <i class="bi bi-clipboard-check fs-2 text-warning opacity-75"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stats-card bg-white border-0 shadow-sm rounded-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase">Faculty Mix</div>
                        <div class="fs-3 fw-bold text-info"><?= esc($stats['fkmp']) ?>/<?= esc($stats['nonFkmp']) ?></div>
                        <div class="small text-muted">FKMP/Non-FKMP</div>
                    </div>
                    <i class="bi bi-people fs-2 text-info opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $pendingPicTotal = max((int) ($stats['pending'] ?? 0) - (int) ($stats['pendingManager'] ?? 0), 0); ?>
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold text-dark me-2">Booking Status Summary</span>
            <span class="badge rounded-pill bg-dark-subtle text-dark border">Total: <?= esc($stats['total'] ?? 0) ?></span>
            <span class="badge rounded-pill bg-warning-subtle text-warning border">Pending PIC: <?= esc($pendingPicTotal) ?></span>
            <span class="badge rounded-pill bg-primary-subtle text-primary border">Pending Manager: <?= esc($stats['pendingManager'] ?? 0) ?></span>
            <span class="badge rounded-pill bg-success-subtle text-success border">Approved: <?= esc($stats['approved'] ?? 0) ?></span>
            <span class="badge rounded-pill bg-danger-subtle text-danger border">Rejected: <?= esc($stats['rejected'] ?? 0) ?></span>
            <span class="badge rounded-pill bg-secondary-subtle text-secondary border">Cancelled: <?= esc($stats['cancelled'] ?? 0) ?></span>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stats-card bg-white border-0 shadow-sm rounded-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase">Open Maintenance</div>
                        <div class="fs-3 fw-bold text-danger"><?= esc($stats['maintenanceOpen'] ?? 0) ?></div>
                        <div class="small text-muted">Reported, scheduled, repair, or testing cases</div>
                    </div>
                    <i class="bi bi-tools fs-2 text-danger opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stats-card bg-white border-0 shadow-sm rounded-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase">Completed Maintenance</div>
                        <div class="fs-3 fw-bold text-success"><?= esc($stats['maintenanceCompleted'] ?? 0) ?></div>
                        <div class="small text-muted">Resolved maintenance cases</div>
                    </div>
                    <i class="bi bi-check2-circle fs-2 text-success opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stats-card bg-white border-0 shadow-sm rounded-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="text-muted small fw-semibold text-uppercase">Upcoming Approved</div>
                        <div class="fs-3 fw-bold text-primary"><?= esc($stats['upcomingApproved'] ?? 0) ?></div>
                        <div class="small text-muted">Approved bookings in the next 7 days</div>
                    </div>
                    <i class="bi bi-calendar-event fs-2 text-primary opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- ENHANCED TAB NAVIGATION WITH BETTER DESIGN -->
<div class="card shadow-sm border-0 rounded-3 mb-4 overflow-hidden">
    <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
        <div class="d-flex align-items-center mb-3">
            <div class="flex-grow-1">
                <h6 class="fw-bold text-dark mb-0">Dashboard Controls</h6>
                <p class="text-muted small mb-0">Switch between booking approvals and analytics</p>
            </div>
        </div>
        
        <ul class="nav nav-tabs nav-tabs-modern" id="dashboardTabs" role="tablist">
            <li class="nav-item me-3" role="presentation">
                <button class="nav-link px-4 py-3 rounded-top-3 <?= $activeTab === 'approvals' ? 'active' : '' ?>" 
                        id="approvals-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#approvals" 
                        type="button" 
                        role="tab">
                    <div class="d-flex align-items-center">
                        <div class="nav-icon-wrapper me-3">
                            <i class="bi bi-clipboard-check fs-5"></i>
                        </div>
                        <div class="text-start">
                            <div class="fw-semibold">Booking Approvals</div>
                            <div class="small text-muted">Manage pending requests</div>
                        </div>
                        <?php if ($stats['pendingManager'] > 0): ?>
                            <span class="badge bg-danger ms-3"><?= esc($stats['pendingManager']) ?></span>
                        <?php endif; ?>
                    </div>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link px-4 py-3 rounded-top-3 <?= $activeTab === 'metrics' ? 'active' : '' ?>" 
                        id="metrics-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#metrics" 
                        type="button" 
                        role="tab">
                    <div class="d-flex align-items-center">
                        <div class="nav-icon-wrapper me-3">
                            <i class="bi bi-bar-chart fs-5"></i>
                        </div>
                        <div class="text-start">
                            <div class="fw-semibold">Lab Analytics</div>
                            <div class="small text-muted">Performance insights</div>
                        </div>
                    </div>
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body p-0">
        <div class="tab-content" id="dashboardTabsContent">
            
            <!-- TAB 1: BOOKING APPROVALS -->
            <div class="tab-pane fade <?= $activeTab === 'approvals' ? 'show active' : '' ?>" 
                 id="approvals" 
                 role="tabpanel">
                 
                <?php if (empty($pendingMgr)): ?>
                    <div class="text-center py-5 px-4">
                        <div class="empty-state-icon mb-3">
                            <i class="bi bi-check-circle-fill text-success fs-1"></i>
                        </div>
                        <h5 class="fw-semibold text-dark mb-2">No pending approvals</h5>
                        <p class="text-muted mb-4">All non-FKMP bookings have been processed.</p>
                        <div class="d-inline-block bg-light rounded-3 p-3">
                            <div class="small text-muted">All clear! Check back later for new requests.</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Pending Approval Requests</h6>
                                <p class="text-muted small mb-0"><?= count($pendingMgr) ?> non-FKMP bookings awaiting review</p>
                            </div>
                            <a href="/dashboard/reports/csv" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-download me-1"></i>Export CSV
                            </a>
                        </div>
                        
                        <div class="table-responsive rounded-3 border">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="20%" class="border-0 ps-4">Lab & Date</th>
                                        <th width="15%" class="border-0">Faculty</th>
                                        <th width="15%" class="border-0">PIC</th>
                                        <th width="25%" class="border-0">Assets Requested</th>
                                        <th width="25%" class="border-0 pe-4 text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingMgr as $b): ?>
                                        <tr id="row-mgr-<?= $b['id'] ?>" class="border-top">
                                            <td class="ps-4">
                                                <div class="fw-semibold text-dark"><?= esc($b['lab_name']) ?></div>
                                                <div class="text-muted small">
                                                    <i class="bi bi-calendar3 me-1"></i><?= esc($b['date']) ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <i class="bi bi-clock me-1"></i><?= esc($b['start_time']) ?>-<?= esc($b['end_time']) ?>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 py-2 px-3">
                                                    <?= esc($b['faculty_name'] ?? 'Other') ?>
                                                </span>
                                            </td>
                                            
                                            <td>
                                                <div class="fw-medium small text-dark"><?= esc($b['pic_name'] ?? 'N/A') ?></div>
                                                <div class="text-muted x-small"><?= esc($b['pic_email'] ?? '') ?></div>
                                            </td>
                                            
                                            <td>
                                                <?php if (!empty($b['assets'])): ?>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <?php foreach ($b['assets'] as $asset): ?>
                                                            <span class="badge bg-light text-dark border py-2">
                                                                <i class="bi bi-box me-1"></i><?= esc($asset['name']) ?> (x<?= $asset['quantity_used'] ?>)
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small"><i class="bi bi-dash-circle me-1"></i>No assets requested</span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="pe-4 text-end">
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <button class="btn btn-outline-primary btn-sm px-3"
                                                            onclick="viewBookingDetails(<?= $b['id'] ?>)"
                                                            data-bs-toggle="tooltip" title="View Details">
                                                        <i class="bi bi-eye me-1"></i>View
                                                    </button>
                                                    <div class="btn-group">
                                                        <button class="btn btn-success btn-sm px-3"
                                                                onclick="approveBooking(<?= $b['id'] ?>)"
                                                                data-bs-toggle="tooltip" title="Approve Booking">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                        <button class="btn btn-danger btn-sm px-3"
                                                                onclick="rejectBooking(<?= $b['id'] ?>)"
                                                                data-bs-toggle="tooltip" title="Reject Booking">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 2: LAB METRICS & ANALYTICS -->
            <div class="tab-pane fade <?= $activeTab === 'metrics' ? 'show active' : '' ?>" 
                 id="metrics" 
                 role="tabpanel">
                 
                <div class="p-4">
                    <!-- SECTION 1: QUICK INSIGHTS -->
                    <div class="row mb-4 g-4">
                        <div class="col-md-6 col-xl-3">
                            <div class="card border-0 bg-gradient-primary text-white shadow-sm overflow-hidden h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small opacity-85">Avg. Weekly Utilization</div>
                                            <div class="fs-4 fw-bold mt-1">
                                                <?php 
                                                $avgUtil = array_column($analytics['weeklyUtilization'], 'avg_utilization');
                                                echo count($avgUtil) > 0 ? round(array_sum($avgUtil) / count($avgUtil), 1) : 0;
                                                ?>%
                                            </div>
                                        </div>
                                        <i class="bi bi-speedometer2 fs-2 opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-xl-3">
                            <div class="card border-0 bg-gradient-success text-white shadow-sm overflow-hidden h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small opacity-85">Peak Hour</div>
                                            <div class="fs-4 fw-bold mt-1">
                                                <?php 
                                                if (!empty($analytics['peakHours']['timeSlots'])) {
                                                    $peak = max(array_column($analytics['peakHours']['timeSlots'], 'booking_count'));
                                                    $peakHour = $analytics['peakHours']['timeSlots'][array_search($peak, array_column($analytics['peakHours']['timeSlots'], 'booking_count'))]['hour_label'];
                                                    echo $peakHour;
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <i class="bi bi-clock-history fs-2 opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-xl-3">
                            <div class="card border-0 bg-gradient-info text-white shadow-sm overflow-hidden h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small opacity-85">Busiest Day</div>
                                            <div class="fs-4 fw-bold mt-1">
                                                <?php 
                                                if (!empty($analytics['peakHours']['busyDays'])) {
                                                    echo $analytics['peakHours']['busyDays'][0]['day'] ?? 'N/A';
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <i class="bi bi-calendar-event fs-2 opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-xl-3">
                            <div class="card border-0 bg-gradient-warning text-white shadow-sm overflow-hidden h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small opacity-85">Top Faculty</div>
                                            <div class="fs-4 fw-bold mt-1">
                                                <?php 
                                                if (!empty($analytics['facultyDistribution'])) {
                                                    echo $analytics['facultyDistribution'][0]['faculty'] ?? 'N/A';
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <i class="bi bi-trophy fs-2 opacity-75"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 1B: DEMAND INSIGHTS -->
                    <?php $demand = $analytics['demandInsights'] ?? []; ?>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3 flex-wrap gap-2">
                            <div>
                                <h6 class="fw-bold text-dark mb-0">
                                    <i class="bi bi-lightning-charge me-2 text-primary"></i>Demand Insights
                                </h6>
                                <p class="text-muted small mb-0">
                                    Recommended high-demand slots based on approved bookings
                                </p>
                            </div>
                            <form method="get" class="d-flex align-items-center gap-2">
                                <input type="hidden" name="tab" value="metrics">
                                <select name="insight_period" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="2" <?= ($insightPeriod ?? 8) === 2 ? 'selected' : '' ?>>Last 2 weeks</option>
                                    <option value="4" <?= ($insightPeriod ?? 8) === 4 ? 'selected' : '' ?>>Last 1 month</option>
                                    <option value="8" <?= ($insightPeriod ?? 8) === 8 ? 'selected' : '' ?>>Last 2 months</option>
                                    <option value="12" <?= ($insightPeriod ?? 8) === 12 ? 'selected' : '' ?>>Last 3 months</option>
                                </select>
                            </form>
                        </div>
                        <div class="card-body">
                            <?php if (empty($demand['top_slots'])): ?>
                                <div class="text-muted small">
                                    No approved bookings found in the selected period.
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <?php foreach ($demand['top_slots'] as $slot): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-2 px-3">
                                            <?= esc($slot['label']) ?> (<?= esc($slot['count']) ?>)
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-muted small">
                                    Based on <?= esc($demand['total_bookings'] ?? 0) ?> approved bookings in the last
                                    <?= esc($demand['period_weeks'] ?? $insightPeriod ?? 8) ?> weeks.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>


                    
                    <!-- SECTION 2: WEEKLY UTILIZATION & PEAK HOURS -->
                    <div class="row mb-4">
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0">
                                            <i class="bi bi-calendar-week me-2 text-primary"></i>Weekly Utilization Trend
                                        </h6>
                                        <p class="text-muted small mb-0">Last 8 weeks performance</p>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-filter me-1"></i>Filter
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="#">This Month</a></li>
                                            <li><a class="dropdown-item" href="#">Last 3 Months</a></li>
                                            <li><a class="dropdown-item" href="#">This Year</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="card-body pt-0">
                                    <canvas id="weeklyUtilizationChart" height="150"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white border-0 py-3">
                                    <h6 class="fw-bold text-dark mb-0">
                                        <i class="bi bi-pie-chart me-2 text-primary"></i>Peak Hours Distribution
                                    </h6>
                                    <p class="text-muted small mb-0">Booking frequency by time slot</p>
                                </div>
                                <div class="card-body">
                                    <canvas id="peakHoursPieChart" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SECTION 3: FACULTY & ASSET DISTRIBUTION -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white border-0 py-3">
                                    <h6 class="fw-bold text-dark mb-0">
                                        <i class="bi bi-people me-2 text-primary"></i>Faculty Distribution
                                    </h6>
                                    <p class="text-muted small mb-0">Booking distribution across faculties</p>
                                </div>
                                <div class="card-body">
                                    <canvas id="facultyPieChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white border-0 py-3">
                                    <h6 class="fw-bold text-dark mb-0">
                                        <i class="bi bi-radar me-2 text-primary"></i>Asset Usage Pattern
                                    </h6>
                                    <p class="text-muted small mb-0">Most requested equipment & tools</p>
                                </div>
                                <div class="card-body">
                                    <canvas id="assetRadarChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SECTION 4: LAB PERFORMANCE TABLE -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0">
                                    <i class="bi bi-building me-2 text-primary"></i>Lab Performance Dashboard
                                </h6>
                                <p class="text-muted small mb-0">Detailed analysis by laboratory</p>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <div class="d-flex align-items-center">
                                    <div class="legend-dot bg-primary"></div>
                                    <span class="small ms-1 me-3">Total</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="legend-dot bg-success"></div>
                                    <span class="small ms-1 me-3">Approved</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="legend-dot bg-warning"></div>
                                    <span class="small ms-1 me-3">Pending</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="legend-dot bg-danger"></div>
                                    <span class="small ms-1 me-3">Rejected</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="legend-dot bg-secondary"></div>
                                    <span class="small ms-1">Cancelled</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="border-0 ps-4">Laboratory</th>
                                            <th class="border-0 text-center">PIC</th>
                                            <th class="border-0 text-center">Status</th>
                                            <th class="border-0 text-center">Weekly Util.</th>
                                            <th class="border-0 text-center">Trend</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($analytics['labPerformance'] as $index => $lab): ?>
                                            <tr class="<?= $index % 2 === 0 ? 'bg-light bg-opacity-10' : '' ?>">
                                                <td class="ps-4">
                                                    <div class="fw-semibold text-dark"><?= esc($lab['name']) ?></div>
                                                    <small class="text-muted">Room <?= esc($lab['room']) ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <small class="fw-medium"><?= esc($lab['pic_name']) ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-2">
                                                        <span class="badge bg-primary bg-opacity-10 text-primary border-0 py-2 px-3" data-bs-toggle="tooltip" title="Total"><?= esc($lab['total']) ?></span>
                                                        <span class="badge bg-success bg-opacity-10 text-success border-0 py-2 px-3" data-bs-toggle="tooltip" title="Approved"><?= esc($lab['approved']) ?></span>
                                                        <span class="badge bg-warning bg-opacity-10 text-warning border-0 py-2 px-3" data-bs-toggle="tooltip" title="Pending"><?= esc($lab['pending']) ?></span>
                                                        <span class="badge bg-danger bg-opacity-10 text-danger border-0 py-2 px-3" data-bs-toggle="tooltip" title="Rejected"><?= esc($lab['rejected']) ?></span>
                                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border-0 py-2 px-3" data-bs-toggle="tooltip" title="Cancelled"><?= esc($lab['cancelled'] ?? 0) ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex flex-column align-items-center">
                                                        <div class="fw-bold <?= $lab['weekly_utilization'] > 60 ? 'text-success' : ($lab['weekly_utilization'] > 30 ? 'text-warning' : 'text-info') ?>">
                                                            <?= round($lab['weekly_utilization'], 0) ?>%
                                                        </div>
                                                        <div class="progress mt-1" style="height: 6px; width: 100px; border-radius: 3px;">
                                                            <div class="progress-bar <?= $lab['weekly_utilization'] > 60 ? 'bg-success' : ($lab['weekly_utilization'] > 30 ? 'bg-warning' : 'bg-info') ?>"
                                                                 role="progressbar" 
                                                                 style="width: <?= min(100, $lab['weekly_utilization']) ?>%; border-radius: 3px;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($lab['weekly_utilization'] > 60): ?>
                                                        <div class="trend-indicator bg-success bg-opacity-10 text-success rounded-circle p-2 d-inline-block" data-bs-toggle="tooltip" title="High Usage">
                                                            <i class="bi bi-arrow-up"></i>
                                                        </div>
                                                    <?php elseif ($lab['weekly_utilization'] > 30): ?>
                                                        <div class="trend-indicator bg-warning bg-opacity-10 text-warning rounded-circle p-2 d-inline-block" data-bs-toggle="tooltip" title="Moderate Usage">
                                                            <i class="bi bi-dash"></i>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="trend-indicator bg-info bg-opacity-10 text-info rounded-circle p-2 d-inline-block" data-bs-toggle="tooltip" title="Low Usage">
                                                            <i class="bi bi-arrow-down"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SECTION 5: MONTHLY TRENDS -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0">
                                    <i class="bi bi-graph-up-arrow me-2 text-primary"></i>Monthly Booking Trends
                                </h6>
                                <p class="text-muted small mb-0">Year-over-year booking patterns</p>
                            </div>
                            <div class="btn-group btn-group-sm shadow-sm">
                                <button type="button" class="btn btn-outline-primary active" onclick="updateTrendChart('line')">
                                    <i class="bi bi-graph-up"></i> Line
                                </button>
                                <button type="button" class="btn btn-outline-primary" onclick="updateTrendChart('bar')">
                                    <i class="bi bi-bar-chart"></i> Bar
                                </button>
                                <button type="button" class="btn btn-outline-primary" onclick="updateTrendChart('area')">
                                    <i class="bi bi-layers"></i> Area
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyTrendsChart" height="150"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL FOR BOOKING DETAILS -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 rounded-top-3">
                <h5 class="modal-title">Booking Details - Manager Review</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bookingDetails">
                <!-- Content loaded via AJAX -->
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-3">Loading booking details...</p>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-3">
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
let trendChart = null;
const csrfHeaderName = "X-CSRF-TOKEN";
const csrfTokenValue = "<?= csrf_hash() ?>";

// Initialize tooltips and charts
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Check if metrics tab is active and initialize charts
    const metricsTab = document.getElementById('metrics');
    if (metricsTab && metricsTab.classList.contains('active')) {
        setTimeout(() => {
            if (typeof initializeCharts === 'function') {
                initializeCharts();
            }
        }, 100);
    }
    
    // Listen for tab changes to initialize charts
    document.getElementById('metrics-tab').addEventListener('shown.bs.tab', function() {
        setTimeout(() => {
            if (typeof initializeCharts === 'function') {
                initializeCharts();
            }
        }, 50);
    });
});

function initializeCharts() {
    // Weekly Utilization Chart (Line)
    const weeklyCtx = document.getElementById('weeklyUtilizationChart').getContext('2d');
    new Chart(weeklyCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($analytics['weeklyUtilization'], 'week_label')) ?>,
            datasets: [{
                label: 'Utilization %',
                data: <?= json_encode(array_column($analytics['weeklyUtilization'], 'avg_utilization')) ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 3,
                pointBackgroundColor: '#3b82f6',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Utilization: ' + context.parsed.y + '%';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    }
                }
            }
        }
    });
    
    // Peak Hours Pie Chart
    const peakPieCtx = document.getElementById('peakHoursPieChart').getContext('2d');
    const peakLabels = <?= json_encode(array_column($analytics['peakHours']['timeSlots'], 'hour_label')) ?>;
    const peakData = <?= json_encode(array_column($analytics['peakHours']['timeSlots'], 'booking_count')) ?>;
    
    // Generate vibrant colors for pie chart
    const peakColors = peakLabels.map((_, index) => {
        const hue = (index * 40) % 360;
        return `hsl(${hue}, 70%, 60%)`;
    });
    
    new Chart(peakPieCtx, {
        type: 'doughnut',
        data: {
            labels: peakLabels,
            datasets: [{
                data: peakData,
                backgroundColor: peakColors,
                borderColor: '#fff',
                borderWidth: 2,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 15,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.parsed / total) * 100);
                            return `${context.label}: ${context.parsed} bookings (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Faculty Distribution Pie Chart
    const facultyCtx = document.getElementById('facultyPieChart').getContext('2d');
    const facultyLabels = <?= json_encode(array_column($analytics['facultyDistribution'], 'faculty')) ?>;
    const facultyData = <?= json_encode(array_column($analytics['facultyDistribution'], 'count')) ?>;
    
    // Color faculty by FKMP status
    const facultyColors = <?= json_encode(array_column($analytics['facultyDistribution'], 'is_fkmp')) ?>.map(isFkmp => 
        isFkmp == 1 ? '#10b981' : '#3b82f6'
    );
    
    new Chart(facultyCtx, {
        type: 'pie',
        data: {
            labels: facultyLabels,
            datasets: [{
                data: facultyData,
                backgroundColor: facultyColors,
                borderColor: '#fff',
                borderWidth: 2,
                hoverOffset: 15
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 12,
                        padding: 15,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.parsed / total) * 100);
                            const isFkmp = <?= json_encode(array_column($analytics['facultyDistribution'], 'is_fkmp')) ?>[context.dataIndex];
                            const type = isFkmp == 1 ? ' (FKMP)' : ' (Non-FKMP)';
                            return `${context.label}${type}: ${context.parsed} bookings (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Asset Usage Radar Chart
    const assetCtx = document.getElementById('assetRadarChart').getContext('2d');
    const assetLabels = <?= json_encode(array_column($analytics['assetUsage']['mostUsed'], 'name')) ?>;
    const assetData = <?= json_encode(array_column($analytics['assetUsage']['mostUsed'], 'total_used')) ?>;
    
    // Normalize data for radar chart (0-100 scale)
    const maxAssetUsage = Math.max(...assetData);
    const normalizedAssetData = assetData.map(value => (value / maxAssetUsage) * 100);
    
    new Chart(assetCtx, {
        type: 'radar',
        data: {
            labels: assetLabels,
            datasets: [{
                label: 'Usage Level',
                data: normalizedAssetData,
                backgroundColor: 'rgba(245, 158, 11, 0.2)',
                borderColor: '#f59e0b',
                pointBackgroundColor: '#f59e0b',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#f59e0b',
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        display: false
                    },
                    pointLabels: {
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const index = context.dataIndex;
                            const actualValue = assetData[index];
                            return `${assetLabels[index]}: ${actualValue} times used`;
                        }
                    }
                }
            }
        }
    });
    
    // Monthly Trends Chart
    initializeTrendChart('line');

}

function initializeTrendChart(type = 'line') {
    const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
    
    if (trendChart) {
        trendChart.destroy();
    }
    
    const isArea = type === 'area';
    
    trendChart = new Chart(monthlyCtx, {
        type: isArea ? 'line' : type,
        data: {
            labels: <?= json_encode(array_column($analytics['monthlyTrends'], 'month')) ?>,
            datasets: [{
                label: 'Bookings',
                data: <?= json_encode(array_column($analytics['monthlyTrends'], 'total')) ?>,
                borderColor: '#8b5cf6',
                backgroundColor: isArea ? 'rgba(139, 92, 246, 0.3)' : (type === 'bar' ? '#8b5cf6' : 'transparent'),
                tension: 0.3,
                fill: isArea,
                borderWidth: type === 'bar' ? 0 : 3,
                borderRadius: type === 'bar' ? 5 : 0,
                hoverBackgroundColor: type === 'bar' ? '#7c3aed' : undefined
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    }
                }
            }
        }
    });
}

function updateTrendChart(type) {
    initializeTrendChart(type);
    
    // Update button states
    document.querySelectorAll('#metrics .btn-group-sm .btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
}

// Booking functions
function viewBookingDetails(id) {
    currentBookingId = id;
    const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
    
    document.getElementById('bookingDetails').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    fetch(`/dashboard/manager/booking/${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                displayBookingDetails(data.booking);
                modal.show();
            } else {
                alert(data.message);
                modal.hide();
            }
        });
}

function displayBookingDetails(booking) {
    const container = document.getElementById('bookingDetails');
    
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
                <a href="${booking.pdf_url}" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-file-pdf me-1"></i>View Uploaded PDF
                </a>
            </div>
        `;
    }
    
    const isFkmp = booking.is_fkmp == 1;
    
    container.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <div class="card border mb-3">
                    <div class="card-body">
                        <h6 class="fw-semibold">Laboratory Information</h6>
                        <p class="mb-2"><strong>Lab:</strong> ${booking.lab_name}</p>
                        <p class="mb-2"><strong>Room:</strong> ${booking.lab_room}</p>
                        <p class="mb-2"><strong>PIC:</strong> ${booking.pic_name}</p>
                        <p class="mb-0"><strong>PIC Email:</strong> ${booking.pic_email}</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card border mb-3">
                    <div class="card-body">
                        <h6 class="fw-semibold">Booking Details</h6>
                        <p class="mb-2"><strong>Date:</strong> ${booking.date}</p>
                        <p class="mb-2"><strong>Time:</strong> ${booking.start_time} - ${booking.end_time}</p>
                        <p class="mb-2"><strong>Faculty:</strong> ${booking.faculty_name}</p>
                        <p class="mb-2">
                            <strong>Type:</strong> 
                            <span class="badge ${isFkmp ? 'bg-success' : 'bg-info'}">
                                ${isFkmp ? 'FKMP' : 'Non-FKMP'}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card border mb-3">
            <div class="card-body">
                <h6 class="fw-semibold">Activity Description</h6>
                <p>${booking.activity}</p>
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
        
        ${!isFkmp ? `
        <div class="alert alert-info mt-3">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Manager Approval Required:</strong> This booking is from a Non-FKMP student.
        </div>
        ` : ''}
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
            document.getElementById(`row-mgr-${id}`).remove();
            
            // Update badge count
            const badge = document.querySelector('#approvals-tab .badge');
            if (badge) {
                const currentCount = parseInt(badge.textContent);
                if (currentCount > 1) {
                    badge.textContent = currentCount - 1;
                } else {
                    badge.remove();
                }
            }
            
            // If no more pending, show message
            if (document.querySelectorAll('#approvals tbody tr').length === 0) {
                document.querySelector('#approvals').innerHTML = `
                    <div class="text-center py-5 px-4">
                        <div class="empty-state-icon mb-3">
                            <i class="bi bi-check-circle-fill text-success fs-1"></i>
                        </div>
                        <h5 class="fw-semibold text-dark mb-2">No pending approvals</h5>
                        <p class="text-muted mb-4">All non-FKMP bookings have been processed.</p>
                        <div class="d-inline-block bg-light rounded-3 p-3">
                            <div class="small text-muted">All clear! Check back later for new requests.</div>
                        </div>
                    </div>
                `;
            }
        } else {
            alert(data.message);
        }
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
            document.getElementById(`row-mgr-${id}`).remove();
            
            // Update badge count
            const badge = document.querySelector('#approvals-tab .badge');
            if (badge) {
                const currentCount = parseInt(badge.textContent);
                if (currentCount > 1) {
                    badge.textContent = currentCount - 1;
                } else {
                    badge.remove();
                }
            }
            
            // If no more pending, show message
            if (document.querySelectorAll('#approvals tbody tr').length === 0) {
                document.querySelector('#approvals').innerHTML = `
                    <div class="text-center py-5 px-4">
                        <div class="empty-state-icon mb-3">
                            <i class="bi bi-check-circle-fill text-success fs-1"></i>
                        </div>
                        <h5 class="fw-semibold text-dark mb-2">No pending approvals</h5>
                        <p class="text-muted mb-4">All non-FKMP bookings have been processed.</p>
                        <div class="d-inline-block bg-light rounded-3 p-3">
                            <div class="small text-muted">All clear! Check back later for new requests.</div>
                        </div>
                    </div>
                `;
            }
        } else {
            alert(data.message);
        }
    });
}
</script>


<?= $this->endSection() ?>

