<?php

namespace App\Libraries;

use App\Models\BookingModel;
use CodeIgniter\Shield\Entities\User;

class ReportSnapshotBuilder
{
    protected UserRoleResolver $roleResolver;

    public function __construct()
    {
        $this->roleResolver = new UserRoleResolver();
    }

    public function build(User $user): array
    {
        $role = $this->reportRole($user);
        if (! in_array($role, ['admin', 'manager', 'pic'], true)) {
            throw new \RuntimeException('You do not have access to reports.');
        }

        $db = db_connect();
        $email = strtolower(trim((string) ($db->table('auth_identities')
            ->where('user_id', $user->id)
            ->where('type', 'email_password')
            ->get()
            ->getRow('secret') ?? '')));

        $labIds = [];
        if ($role === 'pic') {
            $labIds = $db->table('laboratories')
                ->select('id')
                ->where('LOWER(TRIM(pic_email)) =', $email)
                ->get()
                ->getResultArray();
            $labIds = array_map(static fn(array $row): int => (int) $row['id'], $labIds);
        }

        $applyLabScope = static function ($builder, string $column = 'lab_id') use ($labIds, $role) {
            if ($role === 'pic') {
                if ($labIds === []) {
                    $builder->where('1 = 0');
                } else {
                    $builder->whereIn($column, $labIds);
                }
            }

            return $builder;
        };

        $labsQuery = $db->table('laboratories');
        if ($role === 'pic') {
            if ($labIds === []) {
                $labsQuery->where('1 = 0');
            } else {
                $labsQuery->whereIn('id', $labIds);
            }
        }
        $labs = $labsQuery->get()->getResultArray();

        $bookingStatuses = BookingModel::CORE_STATUSES;
        $statusMap = array_fill_keys($bookingStatuses, 0);
        $statusCounts = $applyLabScope(
            $db->table('bookings')
                ->select('status, COUNT(*) AS total')
                ->whereIn('status', $bookingStatuses)
                ->groupBy('status')
        )->get()->getResultArray();

        foreach ($statusCounts as $row) {
            if (array_key_exists((string) $row['status'], $statusMap)) {
                $statusMap[(string) $row['status']] = (int) $row['total'];
            }
        }

        $assetsStatus = $applyLabScope(
            $db->table('assets')
                ->select('status, COUNT(*) AS total')
                ->groupBy('status')
        )->get()->getResultArray();

        $assetTotals = [
            'available' => 0,
            'maintenance' => 0,
            'faulty' => 0,
        ];
        foreach ($assetsStatus as $row) {
            $status = strtolower((string) ($row['status'] ?? ''));
            if (array_key_exists($status, $assetTotals)) {
                $assetTotals[$status] = (int) $row['total'];
            }
        }

        $monthlyCutoff = date('Y-m-d', strtotime('-6 months'));
        $monthlyTrend = $applyLabScope(
            $db->table('bookings')
                ->select("DATE_FORMAT(date, '%Y-%m') AS month, COUNT(*) AS total")
                ->where('date >=', $monthlyCutoff)
                ->whereIn('status', $bookingStatuses)
                ->groupBy("DATE_FORMAT(date, '%Y-%m')")
                ->orderBy('month', 'ASC')
        )->get()->getResultArray();

        $topLabsBuilder = $db->table('bookings b')
            ->select('l.name AS lab_name, COUNT(*) AS total')
            ->join('laboratories l', 'l.id = b.lab_id', 'left')
            ->whereIn('b.status', $bookingStatuses)
            ->groupBy('l.name')
            ->orderBy('total', 'DESC')
            ->limit(5);
        if (in_array($role, ['pic', 'manager'], true)) {
            $topLabsBuilder->where('b.status', 'APPROVED');
        }
        if ($role === 'pic') {
            if ($labIds === []) {
                $topLabsBuilder->where('1 = 0');
            } else {
                $topLabsBuilder->whereIn('b.lab_id', $labIds);
            }
        }
        $topLabs = $topLabsBuilder->get()->getResultArray();

        $facultyBuilder = $db->table('bookings b')
            ->select('f.name_en AS faculty_name, COUNT(*) AS total')
            ->join('faculties f', 'f.id = b.faculty_id', 'left')
            ->whereIn('b.status', $bookingStatuses)
            ->groupBy('f.name_en')
            ->orderBy('total', 'DESC')
            ->limit(6);
        if (in_array($role, ['pic', 'manager'], true)) {
            $facultyBuilder->where('b.status', 'APPROVED');
        }
        if ($role === 'pic') {
            if ($labIds === []) {
                $facultyBuilder->where('1 = 0');
            } else {
                $facultyBuilder->whereIn('b.lab_id', $labIds);
            }
        }
        $facultyCounts = $facultyBuilder->get()->getResultArray();

        $maintenanceQuery = $db->table('maintenance_records mr')
            ->select('mr.status, COUNT(*) AS total')
            ->join('assets a', 'a.id = mr.asset_id', 'left')
            ->groupBy('mr.status');
        $maintenanceQuery = $applyLabScope($maintenanceQuery, 'a.lab_id');
        $maintenanceRows = $maintenanceQuery->get()->getResultArray();

        $maintenanceStatus = [
            'reported' => 0,
            'scheduled' => 0,
            'in_progress' => 0,
            'testing' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];
        foreach ($maintenanceRows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $maintenanceStatus)) {
                $maintenanceStatus[$status] = (int) $row['total'];
            }
        }

        $maintenanceTrendQuery = $db->table('maintenance_records mr')
            ->select("DATE_FORMAT(mr.created_at, '%Y-%m') AS month, COUNT(*) AS total")
            ->join('assets a', 'a.id = mr.asset_id', 'left')
            ->where('mr.created_at >=', date('Y-m-d', strtotime('-6 months')))
            ->groupBy("DATE_FORMAT(mr.created_at, '%Y-%m')")
            ->orderBy('month', 'ASC');
        $maintenanceTrendQuery = $applyLabScope($maintenanceTrendQuery, 'a.lab_id');
        $maintenanceTrend = $maintenanceTrendQuery->get()->getResultArray();

        $topMaintenanceAssetsQuery = $db->table('maintenance_records mr')
            ->select('a.name AS asset_name, COUNT(*) AS total')
            ->join('assets a', 'a.id = mr.asset_id', 'left')
            ->groupBy('a.name')
            ->orderBy('total', 'DESC')
            ->limit(5);
        $topMaintenanceAssetsQuery = $applyLabScope($topMaintenanceAssetsQuery, 'a.lab_id');
        $topMaintenanceAssets = $topMaintenanceAssetsQuery->get()->getResultArray();

        $upcomingApprovalsQuery = $db->table('bookings b')
            ->select('l.name AS lab_name, b.date, b.start_time, b.end_time, b.status, b.approval_flow')
            ->join('laboratories l', 'l.id = b.lab_id', 'left')
            ->where('b.date >=', date('Y-m-d'))
            ->whereIn('b.status', BookingModel::ACTIVE_STATUSES)
            ->orderBy('b.date', 'ASC')
            ->orderBy('b.start_time', 'ASC')
            ->limit(8);
        $upcomingApprovalsQuery = $applyLabScope($upcomingApprovalsQuery, 'b.lab_id');
        $upcomingBookings = $upcomingApprovalsQuery->get()->getResultArray();

        $userCount = $role === 'admin' ? $db->table('users')->where('deleted_at', null)->countAllResults() : null;
        $maintenanceOpen = $maintenanceStatus['reported']
            + $maintenanceStatus['scheduled']
            + $maintenanceStatus['in_progress']
            + $maintenanceStatus['testing'];

        return [
            'reportTitle' => strtoupper($role) . ' Analytics Report',
            'scopeLabel' => $role === 'pic' ? 'PIC Scope (Assigned Labs)' : 'System-wide Scope',
            'generatedAt' => date('Y-m-d H:i'),
            'kpis' => [
                'total_bookings' => array_sum($statusMap),
                'approved' => $statusMap['APPROVED'],
                'pending' => $statusMap['PENDING'],
                'rejected' => $statusMap['REJECTED'],
                'cancelled' => $statusMap['CANCELLED'],
                'total_labs' => count($labs),
                'total_assets' => array_sum($assetTotals),
                'users' => $userCount,
                'maintenance_total' => array_sum($maintenanceStatus),
                'maintenance_open' => $maintenanceOpen,
                'maintenance_completed' => $maintenanceStatus['completed'],
            ],
            'assetTotals' => $assetTotals,
            'statusMap' => $statusMap,
            'monthlyTrend' => $monthlyTrend,
            'topLabs' => $topLabs,
            'facultyCounts' => $facultyCounts,
            'labs' => $labs,
            'maintenanceStatus' => $maintenanceStatus,
            'maintenanceTrend' => $maintenanceTrend,
            'topMaintenanceAssets' => $topMaintenanceAssets,
            'upcomingBookings' => $upcomingBookings,
            'role' => $role,
        ];
    }

    public function buildCsv(array $data): string
    {
        $rows = [
            ['SLAMS Report', $data['reportTitle'] ?? 'SLAMS Report'],
            ['Scope', $data['scopeLabel'] ?? 'Unknown Scope'],
            ['Generated', $data['generatedAt'] ?? date('Y-m-d H:i')],
            [],
            ['KPI', 'Value'],
        ];

        foreach (($data['kpis'] ?? []) as $label => $value) {
            if ($value !== null) {
                $rows[] = [ucwords(str_replace('_', ' ', (string) $label)), $value];
            }
        }

        $rows[] = [];
        $rows[] = ['Booking Status', 'Total'];
        foreach (($data['statusMap'] ?? []) as $status => $total) {
            $rows[] = [$status, $total];
        }

        $rows[] = [];
        $rows[] = ['Maintenance Status', 'Total'];
        foreach (($data['maintenanceStatus'] ?? []) as $status => $total) {
            $rows[] = [ucwords(str_replace('_', ' ', (string) $status)), $total];
        }

        $rows[] = [];
        $rows[] = ['Asset Status', 'Total'];
        foreach (($data['assetTotals'] ?? []) as $status => $total) {
            $rows[] = [ucwords((string) $status), $total];
        }

        $rows[] = [];
        $rows[] = ['Top Labs By Bookings', 'Total'];
        foreach (($data['topLabs'] ?? []) as $lab) {
            $rows[] = [$lab['lab_name'] ?? 'Unknown Lab', $lab['total'] ?? 0];
        }

        $rows[] = [];
        $rows[] = ['Monthly Booking Trend', 'Total'];
        foreach (($data['monthlyTrend'] ?? []) as $month) {
            $rows[] = [$month['month'] ?? '-', $month['total'] ?? 0];
        }

        $rows[] = [];
        $rows[] = ['Monthly Maintenance Trend', 'Total'];
        foreach (($data['maintenanceTrend'] ?? []) as $month) {
            $rows[] = [$month['month'] ?? '-', $month['total'] ?? 0];
        }

        $rows[] = [];
        $rows[] = ['Upcoming Booking Activity', 'Date', 'Time', 'Status', 'Flow'];
        foreach (($data['upcomingBookings'] ?? []) as $booking) {
            $rows[] = [
                $booking['lab_name'] ?? '-',
                $booking['date'] ?? '-',
                trim(($booking['start_time'] ?? '-') . ' - ' . ($booking['end_time'] ?? '-')),
                $booking['status'] ?? '-',
                $booking['approval_flow'] ?? '-',
            ];
        }

        $rows[] = [];
        $rows[] = ['Lab Name', 'Room', 'PIC', 'PIC Email'];
        foreach (($data['labs'] ?? []) as $lab) {
            $rows[] = [
                $lab['name'] ?? '-',
                $lab['room'] ?? '-',
                $lab['pic_name'] ?? '-',
                $lab['pic_email'] ?? '-',
            ];
        }

        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv ?: '';
    }

    protected function reportRole(User $user): string
    {
        return $this->roleResolver->approvalRole($user) ?? 'user';
    }
}
