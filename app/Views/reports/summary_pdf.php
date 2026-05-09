<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= esc($reportTitle) ?></title>
    <style>
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 12px; color: #1f2937; margin: 0; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 6px; }
        h2 { font-size: 15px; margin: 18px 0 10px; color: #1e3a8a; }
        .meta { font-size: 11px; color: #6b7280; margin-bottom: 16px; }
        .kpi-grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .kpi-grid td { padding: 10px 12px; border: 1px solid #e5e7eb; }
        .kpi-label { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.08em; }
        .kpi-value { font-size: 16px; font-weight: 700; color: #111827; }
        .chart-row { margin: 6px 0; }
        .chart-label { font-size: 11px; margin-bottom: 4px; }
        .chart-bar { height: 10px; background: #e5e7eb; border-radius: 6px; overflow: hidden; }
        .chart-fill { height: 100%; background: #2563eb; }
        .chart-fill.secondary { background: #0ea5e9; }
        .chart-fill.warning { background: #f59e0b; }
        .chart-fill.success { background: #10b981; }
        .chart-fill.danger { background: #ef4444; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #e5e7eb; padding: 8px; font-size: 11px; }
        table.data th { background: #f3f4f6; text-align: left; }
        .two-column { width: 100%; }
        .two-column td { vertical-align: top; width: 50%; padding-right: 12px; }
    </style>
</head>
<body>
    <h1><?= esc($reportTitle) ?></h1>
    <div class="meta">
        Scope: <?= esc($scopeLabel) ?><br>
        Generated: <?= esc($generatedAt) ?>
    </div>

    <table class="kpi-grid">
        <tr>
            <td><div class="kpi-label">Total Bookings</div><div class="kpi-value"><?= esc($kpis['total_bookings']) ?></div></td>
            <td><div class="kpi-label">Approved</div><div class="kpi-value"><?= esc($kpis['approved']) ?></div></td>
            <td><div class="kpi-label">Pending</div><div class="kpi-value"><?= esc($kpis['pending']) ?></div></td>
            <td><div class="kpi-label">Rejected</div><div class="kpi-value"><?= esc($kpis['rejected']) ?></div></td>
        </tr>
        <tr>
            <td><div class="kpi-label">Cancelled</div><div class="kpi-value"><?= esc($kpis['cancelled'] ?? 0) ?></div></td>
            <td><div class="kpi-label">Open Maintenance</div><div class="kpi-value"><?= esc($kpis['maintenance_open']) ?></div></td>
            <td><div class="kpi-label">Completed Maintenance</div><div class="kpi-value"><?= esc($kpis['maintenance_completed']) ?></div></td>
            <td><div class="kpi-label">Total Labs</div><div class="kpi-value"><?= esc($kpis['total_labs']) ?></div></td>
        </tr>
        <tr>
            <td><div class="kpi-label">Total Assets</div><div class="kpi-value"><?= esc($kpis['total_assets']) ?></div></td>
            <td><div class="kpi-label">Users</div><div class="kpi-value"><?= esc($kpis['users'] ?? '-') ?></div></td>
            <td><div class="kpi-label">Maintenance Total</div><div class="kpi-value"><?= esc($kpis['maintenance_total'] ?? 0) ?></div></td>
            <td><div class="kpi-label">Scope Labs</div><div class="kpi-value"><?= esc($kpis['total_labs']) ?></div></td>
        </tr>
    </table>

    <table class="two-column">
        <tr>
            <td>
                <h2>Booking Status Breakdown</h2>
                <?php $totalStatus = max(1, array_sum($statusMap)); ?>
                <?php foreach ($statusMap as $status => $count): ?>
                    <?php $percent = round(($count / $totalStatus) * 100); $class = $status === 'APPROVED' ? 'success' : ($status === 'PENDING' ? 'warning' : ($status === 'CANCELLED' ? 'secondary' : 'danger')); ?>
                    <div class="chart-row"><div class="chart-label"><?= esc($status) ?> (<?= esc($count) ?>)</div><div class="chart-bar"><div class="chart-fill <?= esc($class) ?>" style="width: <?= esc($percent) ?>%;"></div></div></div>
                <?php endforeach; ?>
            </td>
            <td>
                <h2>Maintenance Status Breakdown</h2>
                <?php $totalMaintenance = max(1, array_sum($maintenanceStatus)); ?>
                <?php foreach ($maintenanceStatus as $status => $count): ?>
                    <?php $percent = round(($count / $totalMaintenance) * 100); ?>
                    <div class="chart-row"><div class="chart-label"><?= esc(strtoupper($status)) ?> (<?= esc($count) ?>)</div><div class="chart-bar"><div class="chart-fill secondary" style="width: <?= esc($percent) ?>%;"></div></div></div>
                <?php endforeach; ?>
            </td>
        </tr>
    </table>

    <h2>Top Labs by Bookings</h2>
    <table class="data">
        <thead><tr><th>Lab</th><th>Total Bookings</th></tr></thead>
        <tbody>
            <?php if (empty($topLabs)): ?>
                <tr><td colspan="2">No bookings available.</td></tr>
            <?php else: ?>
                <?php foreach ($topLabs as $lab): ?><tr><td><?= esc($lab['lab_name'] ?? 'Unknown Lab') ?></td><td><?= esc($lab['total']) ?></td></tr><?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h2>Most Reported Assets For Maintenance</h2>
    <table class="data">
        <thead><tr><th>Asset</th><th>Total Maintenance Cases</th></tr></thead>
        <tbody>
            <?php if (empty($topMaintenanceAssets)): ?>
                <tr><td colspan="2">No maintenance activity available.</td></tr>
            <?php else: ?>
                <?php foreach ($topMaintenanceAssets as $asset): ?><tr><td><?= esc($asset['asset_name'] ?? 'Unknown Asset') ?></td><td><?= esc($asset['total']) ?></td></tr><?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h2>Faculty Distribution</h2>
    <table class="data">
        <thead><tr><th>Faculty</th><th>Total Bookings</th></tr></thead>
        <tbody>
            <?php if (empty($facultyCounts)): ?>
                <tr><td colspan="2">No faculty data available.</td></tr>
            <?php else: ?>
                <?php foreach ($facultyCounts as $faculty): ?><tr><td><?= esc($faculty['faculty_name'] ?? 'Unknown Faculty') ?></td><td><?= esc($faculty['total']) ?></td></tr><?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h2>Monthly Booking Trend</h2>
    <table class="data">
        <thead><tr><th>Month</th><th>Total Bookings</th></tr></thead>
        <tbody>
            <?php if (empty($monthlyTrend)): ?>
                <tr><td colspan="2">No bookings in this period.</td></tr>
            <?php else: ?>
                <?php foreach ($monthlyTrend as $month): ?><tr><td><?= esc($month['month']) ?></td><td><?= esc($month['total']) ?></td></tr><?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h2>Monthly Maintenance Trend</h2>
    <table class="data">
        <thead><tr><th>Month</th><th>Total Maintenance Cases</th></tr></thead>
        <tbody>
            <?php if (empty($maintenanceTrend)): ?>
                <tr><td colspan="2">No maintenance cases in this period.</td></tr>
            <?php else: ?>
                <?php foreach ($maintenanceTrend as $month): ?><tr><td><?= esc($month['month']) ?></td><td><?= esc($month['total']) ?></td></tr><?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h2>Upcoming Booking Activity</h2>
    <table class="data">
        <thead><tr><th>Lab</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
        <tbody>
            <?php if (empty($upcomingBookings)): ?>
                <tr><td colspan="4">No upcoming booking activity.</td></tr>
            <?php else: ?>
                <?php foreach ($upcomingBookings as $booking): ?><tr><td><?= esc($booking['lab_name'] ?? '-') ?></td><td><?= esc($booking['date'] ?? '-') ?></td><td><?= esc(($booking['start_time'] ?? '-') . ' - ' . ($booking['end_time'] ?? '-')) ?></td><td><?= esc($booking['status'] ?? '-') ?></td></tr><?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h2>Lab Summary</h2>
    <table class="data">
        <thead><tr><th>Lab Name</th><th>Room</th><th>PIC</th><th>PIC Email</th></tr></thead>
        <tbody>
            <?php if (empty($labs)): ?>
                <tr><td colspan="4">No labs available.</td></tr>
            <?php else: ?>
                <?php foreach ($labs as $lab): ?><tr><td><?= esc($lab['name'] ?? '-') ?></td><td><?= esc($lab['room'] ?? '-') ?></td><td><?= esc($lab['pic_name'] ?? '-') ?></td><td><?= esc($lab['pic_email'] ?? '-') ?></td></tr><?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
