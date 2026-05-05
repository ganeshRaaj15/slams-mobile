<?php
/**
 * Dashboard Widgets (KPI Cards + Charts)
 * Usage:
 *   echo view('components/dashboard_widgets', [
 *       'stats' => $stats,
 *       'trends' => $trends
 *   ]);
 */
?>

<!-- KPI CARDS -->
<div class="row g-2 mb-3">

    <!-- Total Bookings -->
    <div class="col-md-3">
        <div class="card shadow-sm border-0 kpi-card-blue">
            <div class="card-body">
                <h6 class="text-uppercase small text-muted">Total Bookings</h6>
                <h2 class="fw-bold"><?= $stats['total'] ?? 0 ?></h2>
            </div>
        </div>
    </div>

    <!-- Pending -->
    <div class="col-md-3">
        <div class="card shadow-sm border-0 kpi-card-yellow">
            <div class="card-body">
                <h6 class="text-uppercase small text-muted">Pending</h6>
                <h2 class="fw-bold"><?= $stats['pending'] ?? 0 ?></h2>
            </div>
        </div>
    </div>

    <!-- Approved -->
    <div class="col-md-3">
        <div class="card shadow-sm border-0 kpi-card-green">
            <div class="card-body">
                <h6 class="text-uppercase small text-muted">Approved</h6>
                <h2 class="fw-bold"><?= $stats['approved'] ?? 0 ?></h2>
            </div>
        </div>
    </div>

    <!-- Rejected -->
    <div class="col-md-3">
        <div class="card shadow-sm border-0 kpi-card-red">
            <div class="card-body">
                <h6 class="text-uppercase small text-muted">Rejected</h6>
                <h2 class="fw-bold"><?= $stats['rejected'] ?? 0 ?></h2>
            </div>
        </div>
    </div>

</div>



<!-- MONTHLY TREND CHART -->
<div class="card shadow-sm border-0">
    <div class="card-body">
        <h6 class="text-uppercase small text-muted mb-3">Monthly Booking Trend</h6>

        <canvas id="trendChart" height="100"></canvas>
    </div>
</div>


<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('trendChart');
    if (!ctx || typeof Chart === 'undefined') {
        return;
    }

    const styles = getComputedStyle(document.documentElement);
    const primary = styles.getPropertyValue('--slams-primary').trim() || '#0f766e';

    const trendLabels = <?= json_encode(array_column($trends, 'month')) ?>;
    const trendData   = <?= json_encode(array_column($trends, 'total')) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Bookings Per Month',
                data: trendData,
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                borderColor: primary,
                backgroundColor: 'rgba(15, 118, 110, 0.14)'
            }]
        }
    });
});
</script>
