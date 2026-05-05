<?= $this->extend('layouts/main_admin') ?>

<?= $this->section('content') ?>


<div class="admin-dashboard">
    <!-- PAGE HEADER -->
    <div class="dashboard-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
            <div>
                <h1>Admin Dashboard</h1>
                <p>System Overview & Real-time Analytics</p>
            </div>
            <div class="d-flex gap-3 flex-wrap align-items-center">
                <a href="/dashboard/reports/pdf" class="btn btn-glass">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Download Report
                </a>
                <a href="/dashboard/reports/csv" class="btn btn-glass">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
                </a>
                <div class="quick-stat">
                    <i class="bi bi-calendar-week"></i>
                    <div>
                        <div class="small text-muted">Today</div>
                        <div class="fw-bold"><?= date('d M Y') ?></div>
                    </div>
                </div>
                <div class="quick-stat">
                    <i class="bi bi-clock"></i>
                    <div>
                        <div class="small text-muted">Last Update</div>
                        <div class="fw-bold">Just now</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI WIDGETS -->
    <div class="row g-4 mb-3">
        <!-- Pending PIC -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-glass-card gradient-pending text-white">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="status-badge">PIC Stage</div>
                        </div>
                        <h6 class="mb-2 opacity-90 fw-medium">Pending Approvals</h6>
                        <div class="kpi-number"><?= esc($stats['pending']) ?></div>
                    </div>
                    <div class="icon-container">
                        <i class="bi bi-clock-history fs-4"></i>
                    </div>
                </div>
                <div class="small opacity-90">Awaiting PIC verification</div>
            </div>
        </div>

        <!-- Pending Manager -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-glass-card gradient-pending-mgr text-white">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="status-badge">Manager Stage</div>
                        </div>
                        <h6 class="mb-2 opacity-90 fw-medium">Pending Manager Review</h6>
                        <div class="kpi-number"><?= esc($stats['pending_mgr']) ?></div>
                    </div>
                    <div class="icon-container">
                        <i class="bi bi-check2-square fs-4"></i>
                    </div>
                </div>
                <div class="small opacity-90">Awaiting manager approval</div>
            </div>
        </div>

        <!-- Approved -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-glass-card gradient-approved text-white">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="status-badge">Approved</div>
                        </div>
                        <h6 class="mb-2 opacity-90 fw-medium">Approved Bookings</h6>
                        <div class="kpi-number"><?= esc($stats['approved']) ?></div>
                    </div>
                    <div class="icon-container">
                        <i class="bi bi-check-circle fs-4"></i>
                    </div>
                </div>
                <div class="small opacity-90">Successfully approved</div>
            </div>
        </div>

        <!-- Rejected -->
        <div class="col-xl-3 col-md-6">
            <div class="kpi-glass-card gradient-rejected text-white">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="status-badge">Rejected</div>
                        </div>
                        <h6 class="mb-2 opacity-90 fw-medium">Rejected Bookings</h6>
                        <div class="kpi-number"><?= esc($stats['rejected']) ?></div>
                    </div>
                    <div class="icon-container">
                        <i class="bi bi-x-circle fs-4"></i>
                    </div>
                </div>
                <div class="small opacity-90">Bookings that were declined</div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-5">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold text-dark me-2">Booking Status Summary</span>
                <span class="badge rounded-pill bg-dark-subtle text-dark border">Total: <?= esc($stats['total'] ?? 0) ?></span>
                <span class="badge rounded-pill bg-warning-subtle text-warning border">Pending PIC: <?= esc($stats['pending'] ?? 0) ?></span>
                <span class="badge rounded-pill bg-primary-subtle text-primary border">Pending Manager: <?= esc($stats['pending_mgr'] ?? 0) ?></span>
                <span class="badge rounded-pill bg-success-subtle text-success border">Approved: <?= esc($stats['approved'] ?? 0) ?></span>
                <span class="badge rounded-pill bg-danger-subtle text-danger border">Rejected: <?= esc($stats['rejected'] ?? 0) ?></span>
                <span class="badge rounded-pill bg-secondary-subtle text-secondary border">Cancelled: <?= esc($stats['cancelled'] ?? 0) ?></span>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW -->
    <div class="row g-4 mb-5">
        <!-- Booking Trends -->
        <div class="col-lg-8">
            <div class="glass-card h-100">
                <div class="glass-card-header d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-gray-800 mb-0">
                        <i class="bi bi-graph-up me-2"></i>
                        Booking Trends (Last 6 Months)
                    </h5>
                    <div class="stat-badge">
                        <i class="bi bi-bar-chart"></i>
                        Monthly Overview
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="chart-container-wrapper">
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Faculty Breakdown -->
        <div class="col-lg-4">
            <div class="glass-card h-100">
                <div class="glass-card-header d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-gray-800 mb-0">
                        <i class="bi bi-pie-chart me-2"></i>
                        Faculty Distribution
                    </h5>
                    <div class="stat-badge">
                        <i class="bi bi-info-circle"></i>
                        Total: <?= array_sum(array_column($facultyBreakdown, 'total')) ?>
                    </div>
                </div>
                <div class="card-body p-4 d-flex flex-column">
                    <div class="chart-container-wrapper" style="height: 160px;">
                        <div class="chart-container">
                            <canvas id="facultyChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Custom Legend -->
                    <div class="chart-legend-container mt-3">
                        <div class="row g-2">
                            <?php 
                            // Dynamic color generation for any number of faculties
                            $facultyColors = generateFacultyColors(count($facultyBreakdown));
                            $i = 0;
                            foreach ($facultyBreakdown as $faculty): 
                                $color = $facultyColors[$i];
                                $i++;
                            ?>
                            <div class="col-6">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="rounded-circle me-2" style="width: 12px; height: 12px; background: <?= $color ?>;"></div>
                                    <div class="text-truncate small" style="max-width: 120px;" title="<?= esc($faculty['faculty']) ?>">
                                        <?= esc($faculty['faculty']) ?>
                                    </div>
                                    <span class="ms-auto fw-semibold small"><?= $faculty['total'] ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- APPROVAL QUEUE -->
    <div class="glass-card mb-5">
        <div class="glass-card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h5 class="fw-bold text-gray-800 mb-1">
                    <i class="bi bi-list-check me-2"></i>
                    Approval Queue
                </h5>
                <p class="text-muted small mb-0">Manage booking approvals across different stages</p>
            </div>
            <div class="d-flex gap-2">
                <span class="stat-badge">
                    <i class="bi bi-clock"></i> Total Pending: <?= $stats['pending'] + $stats['pending_mgr'] ?>
                </span>
            </div>
        </div>
        
        <div class="card-body p-4">
            <ul class="nav nav-tabs" id="approvalTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pendingPicTab">
                        <i class="bi bi-clock-history me-1"></i>
                        <span class="d-none d-md-inline">Pending PIC</span>
                        <span class="badge bg-warning ms-2"><?= count($pendingPic) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pendingMgrTab">
                        <i class="bi bi-check2-square me-1"></i>
                        <span class="d-none d-md-inline">Pending Manager</span>
                        <span class="badge bg-primary ms-2"><?= count($pendingMgr) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#approvedTab">
                        <i class="bi bi-check-circle me-1"></i>
                        <span class="d-none d-md-inline">Approved</span>
                        <span class="badge bg-success ms-2"><?= count($approved) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#rejectedTab">
                        <i class="bi bi-x-circle me-1"></i>
                        <span class="d-none d-md-inline">Rejected</span>
                        <span class="badge bg-danger ms-2"><?= count($rejected) ?></span>
                    </button>
                </li>
            </ul>

            <div class="tab-content mt-4">
                <div class="tab-pane fade show active" id="pendingPicTab">
                    <div class="table-responsive">
                        <?php include('partials/table_pending_pic.php'); ?>
                    </div>
                </div>
                <div class="tab-pane fade" id="pendingMgrTab">
                    <div class="table-responsive">
                        <?php include('partials/table_pending_manager.php'); ?>
                    </div>
                </div>
                <div class="tab-pane fade" id="approvedTab">
                    <div class="table-responsive">
                        <?php include('partials/table_approved.php'); ?>
                    </div>
                </div>
                <div class="tab-pane fade" id="rejectedTab">
                    <div class="table-responsive">
                        <?php include('partials/table_rejected.php'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CHART JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php
// Helper function to generate distinct colors for any number of faculties
function generateFacultyColors($count) {
    // Base palette with 12 distinct, color-blind friendly colors
    $basePalette = [
        '#1f77b4', '#ff7f0e', '#2ca02c', '#d62728', '#9467bd', '#8c564b',
        '#e377c2', '#7f7f7f', '#bcbd22', '#17becf', '#393b79', '#5254a3'
    ];
    
    // Extended palette for more than 12 faculties
    $extendedPalette = [
        '#6b6ecf', '#9c9ede', '#637939', '#8ca252', '#b5cf6b', '#cedb9c',
        '#8c6d31', '#bd9e39', '#e7ba52', '#843c39', '#ad494a', '#d6616b',
        '#e7969c', '#7b4173', '#a55194', '#ce6dbd', '#de9ed6'
    ];
    
    // Combine palettes
    $fullPalette = array_merge($basePalette, $extendedPalette);
    
    // If we need more colors than available, generate them dynamically
    if ($count > count($fullPalette)) {
        $generatedColors = [];
        // Use HSL color space to generate evenly distributed colors
        for ($i = 0; $i < $count; $i++) {
            $hue = ($i * 137.508) % 360; // Golden angle for distribution
            $saturation = 70 + (($i % 3) * 10); // 70-90%
            $lightness = 45 + (($i % 2) * 10); // 45-55%
            $generatedColors[] = hslToHex($hue, $saturation, $lightness);
        }
        return $generatedColors;
    }
    
    // Return subset of palette based on count needed
    return array_slice($fullPalette, 0, $count);
}

// Helper function to convert HSL to HEX
function hslToHex($h, $s, $l) {
    $h /= 360;
    $s /= 100;
    $l /= 100;
    
    $r = $l;
    $g = $l;
    $b = $l;
    $v = ($l <= 0.5) ? ($l * (1.0 + $s)) : ($l + $s - $l * $s);
    
    if ($v > 0) {
        $m = $l + $l - $v;
        $sv = ($v - $m) / $v;
        $h *= 6.0;
        $sextant = floor($h);
        $fract = $h - $sextant;
        $vsf = $v * $sv * $fract;
        $mid1 = $m + $vsf;
        $mid2 = $v - $vsf;
        
        switch ($sextant) {
            case 0:
                $r = $v;
                $g = $mid1;
                $b = $m;
                break;
            case 1:
                $r = $mid2;
                $g = $v;
                $b = $m;
                break;
            case 2:
                $r = $m;
                $g = $v;
                $b = $mid1;
                break;
            case 3:
                $r = $m;
                $g = $mid2;
                $b = $v;
                break;
            case 4:
                $r = $mid1;
                $g = $m;
                $b = $v;
                break;
            case 5:
                $r = $v;
                $g = $m;
                $b = $mid2;
                break;
        }
    }
    
    $r = round($r * 255);
    $g = round($g * 255);
    $b = round($b * 255);
    
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

// Generate colors for the current faculty data
$facultyColors = generateFacultyColors(count($facultyBreakdown));

// Prepare faculty data with labels and colors for JavaScript
$facultyChartData = [];
$i = 0;
foreach ($facultyBreakdown as $faculty) {
    $facultyChartData[] = [
        'label' => $faculty['faculty'],
        'value' => $faculty['total'],
        'color' => $facultyColors[$i]
    ];
    $i++;
}
?>

/* ------------------------------
   BOOKING TRENDS LINE CHART
------------------------------ */
const trendCtx = document.getElementById('trendChart');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($trends, 'month')) ?>,
        datasets: [{
            label: "Total Bookings",
            data: <?= json_encode(array_column($trends, 'total')) ?>,
            borderColor: "#3b82f6",
            backgroundColor: "rgba(59, 130, 246, 0.15)",
            pointBackgroundColor: "#1e40af",
            pointBorderColor: "#ffffff",
            pointBorderWidth: 2,
            pointRadius: 6,
            pointHoverRadius: 8,
            borderWidth: 3,
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(15, 23, 42, 0.95)',
                titleColor: '#ffffff',
                bodyColor: '#e2e8f0',
                borderColor: '#3b82f6',
                borderWidth: 1,
                cornerRadius: 8,
                padding: 12,
                displayColors: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(59, 130, 246, 0.08)',
                    drawBorder: false
                },
                ticks: {
                    color: '#64748b',
                    padding: 8
                }
            },
            x: {
                grid: {
                    color: 'rgba(59, 130, 246, 0.08)',
                    drawBorder: false
                },
                ticks: {
                    color: '#64748b',
                    padding: 8
                }
            }
        }
    }
});

/* ------------------------------
   FACULTY PIE CHART
------------------------------ */
const facultyCtx = document.getElementById('facultyChart');

// Use PHP-generated dynamic colors
const facultyChartData = <?= json_encode($facultyChartData) ?>;
const facultyLabels = facultyChartData.map(item => item.label);
const facultyValues = facultyChartData.map(item => item.value);
const facultyChartColors = facultyChartData.map(item => item.color);

new Chart(facultyCtx, {
    type: 'doughnut',
    data: {
        labels: facultyLabels,
        datasets: [{
            data: facultyValues,
            backgroundColor: facultyChartColors,
            borderColor: '#ffffff',
            borderWidth: 3,
            borderRadius: 8,
            spacing: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { 
                display: false 
            },
            tooltip: {
                backgroundColor: 'rgba(15, 23, 42, 0.95)',
                titleColor: '#ffffff',
                bodyColor: '#e2e8f0',
                borderColor: '#3b82f6',
                borderWidth: 1,
                cornerRadius: 8,
                padding: 12,
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((value / total) * 100);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        },
        cutout: '65%'
    }
});

// Function to update colors if faculty data changes dynamically
function updateFacultyColors(newCount) {
    // This function can be used if you need to update colors dynamically via AJAX
    // It generates colors using the same algorithm as PHP
    function generateDynamicColors(count) {
        const basePalette = [
            '#1f77b4', '#ff7f0e', '#2ca02c', '#d62728', '#9467bd', '#8c564b',
            '#e377c2', '#7f7f7f', '#bcbd22', '#17becf', '#393b79', '#5254a3'
        ];
        
        if (count <= basePalette.length) {
            return basePalette.slice(0, count);
        }
        
        // Generate colors dynamically for large counts
        const colors = [];
        for (let i = 0; i < count; i++) {
            const hue = (i * 137.508) % 360;
            const saturation = 70 + ((i % 3) * 10);
            const lightness = 45 + ((i % 2) * 10);
            colors.push(hslToHex(hue, saturation, lightness));
        }
        return colors;
    }
    
    function hslToHex(h, s, l) {
        h /= 360;
        s /= 100;
        l /= 100;
        
        let r, g, b;
        
        if (s === 0) {
            r = g = b = l;
        } else {
            const hue2rgb = (p, q, t) => {
                if (t < 0) t += 1;
                if (t > 1) t -= 1;
                if (t < 1/6) return p + (q - p) * 6 * t;
                if (t < 1/2) return q;
                if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                return p;
            };
            
            const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
            const p = 2 * l - q;
            
            r = hue2rgb(p, q, h + 1/3);
            g = hue2rgb(p, q, h);
            b = hue2rgb(p, q, h - 1/3);
        }
        
        const toHex = x => {
            const hex = Math.round(x * 255).toString(16);
            return hex.length === 1 ? '0' + hex : hex;
        };
        
        return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
    }
    
    return generateDynamicColors(newCount);
}
</script>

<?= $this->endSection() ?>

