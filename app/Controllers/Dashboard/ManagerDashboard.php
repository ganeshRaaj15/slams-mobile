<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Models\BookingModel;
use App\Models\LaboratoryModel;
use App\Models\BookingAssetModel;

class ManagerDashboard extends BaseController
{
    public function index()
    {
        helper('auth');

        // Access control
        if (! auth()->loggedIn() || ! auth()->user()->inGroup('manager')) {
            return redirect()->to('/dashboard')->with('error', 'Access denied.');
        }

        $user = auth()->user();

        $labModel = new LaboratoryModel();
        $bookingModel = new BookingModel();

        // ------------------------------------------------------------
        // 1. Load all labs
        // ------------------------------------------------------------
        $labs = $labModel->findAll();
        $labIds = array_column($labs, 'id');

        if (empty($labIds)) {
            return view('dashboard/manager/index', [
                'user' => $user,
                'labs' => [],
                'stats' => $this->emptyStats(),
                'pendingMgr' => [],
                'analytics' => $this->emptyAnalytics(),
                'activeTab' => $this->request->getGet('tab') ?? 'approvals',
                'insightPeriod' => $this->parseInsightWeeks($this->request->getGet('insight_period')),
            ]);
        }

        // ------------------------------------------------------------
        // 2. PENDING MANAGER APPROVALS (Non-FKMP only)
        // ------------------------------------------------------------
        $pendingMgr = $this->getPendingManagerApprovals($labIds);

        // ------------------------------------------------------------
        // 3. COMPREHENSIVE STATISTICS
        // ------------------------------------------------------------
        $stats = $this->getComprehensiveStats($labIds);
        
        // ------------------------------------------------------------
        // 4. DETAILED ANALYTICS DATA FOR METRICS TAB
        // ------------------------------------------------------------
        $analytics = [
            'weeklyUtilization' => $this->getWeeklyUtilization($labIds, 8), // Last 8 weeks
            'peakHours' => $this->getPeakHoursAnalysis($labIds),
            'labPerformance' => $this->getLabPerformance($labs),
            'monthlyTrends' => $this->getMonthlyTrends($labIds, 6),
            'facultyDistribution' => $this->getFacultyDistribution($labIds),
            'assetUsage' => $this->getAssetUsageStats($labIds),
            'demandInsights' => $this->getDemandInsights(
                $labIds,
                $this->parseInsightWeeks($this->request->getGet('insight_period'))
            ),
        ];

        // ------------------------------------------------------------
        // 5. RENDER VIEW
        // ------------------------------------------------------------
        return view('dashboard/manager/index', [
            'user' => $user,
            'labs' => $labs,
            'pendingMgr' => $pendingMgr,
            'stats' => $stats,
            'analytics' => $analytics,
            'activeTab' => $this->request->getGet('tab') ?? 'approvals', // Default to approvals tab
            'insightPeriod' => $this->parseInsightWeeks($this->request->getGet('insight_period')),
        ]);
    }

    /**
     * Get pending manager approvals
     */
    private function getPendingManagerApprovals(array $labIds): array
    {
        if (empty($labIds)) return [];

        $bookingModel = new BookingModel();
        $bookingAssetModel = new BookingAssetModel();

        $pendingMgr = $bookingModel
            ->select('bookings.*, 
                     laboratories.name AS lab_name, laboratories.room AS lab_room,
                     laboratories.pic_name, laboratories.pic_email,
                     faculties.name_en AS faculty_name, faculties.is_fkmp')
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->join('faculties', 'faculties.id = bookings.faculty_id', 'left')
            ->whereIn('bookings.lab_id', $labIds)
            ->where('bookings.status', 'PENDING')
            ->where('bookings.approved_by_pic', 1)
            ->where('bookings.approved_by_manager', 0)
            ->where('bookings.approval_flow !=', 'FKMP_APPROVAL')
            ->orderBy('bookings.date', 'ASC')
            ->orderBy('bookings.start_time', 'ASC')
            ->findAll();

        // Fetch assets for each pending manager booking
        foreach ($pendingMgr as &$booking) {
            $booking['assets'] = $bookingAssetModel
                ->select('assets.name, booking_assets.quantity_used')
                ->join('assets', 'assets.id = booking_assets.asset_id')
                ->where('booking_assets.booking_id', $booking['id'])
                ->findAll();
        }

        return $pendingMgr;
    }

    private function emptyStats(): array
    {
        return [
            'total' => 0,
            'approved' => 0,
            'pending' => 0,
            'rejected' => 0,
            'cancelled' => 0,
            'fkmp' => 0,
            'nonFkmp' => 0,
            'currentWeek' => 0,
            'lastWeek' => 0,
            'weekGrowth' => 0,
            'pendingManager' => 0,
            'maintenanceOpen' => 0,
            'maintenanceCompleted' => 0,
            'upcomingApproved' => 0,
        ];
    }

    private function emptyAnalytics(): array
    {
        return [
            'weeklyUtilization' => [],
            'peakHours' => ['timeSlots' => [], 'busyDays' => []],
            'labPerformance' => [],
            'monthlyTrends' => [],
            'facultyDistribution' => [],
            'assetUsage' => ['mostUsed' => []],
            'demandInsights' => ['period_weeks' => 8, 'total_bookings' => 0, 'top_slots' => []],
        ];
    }

    /**
     * Get comprehensive statistics
     */
    private function getComprehensiveStats(array $labIds): array
    {
        $db = \Config\Database::connect();
        
        $statusFilter = BookingModel::CORE_STATUSES;

        // Approved bookings
        $approved = $db->table('bookings')
            ->whereIn('lab_id', $labIds)
            ->where('status', 'APPROVED')
            ->countAllResults();
            
        // Pending bookings (all pending stages)
        $pending = $db->table('bookings')
            ->whereIn('lab_id', $labIds)
            ->where('status', 'PENDING')
            ->countAllResults();

        // Rejected bookings
        $rejected = $db->table('bookings')
            ->whereIn('lab_id', $labIds)
            ->where('status', 'REJECTED')
            ->countAllResults();

        $cancelled = $db->table('bookings')
            ->whereIn('lab_id', $labIds)
            ->where('status', 'CANCELLED')
            ->countAllResults();

        // Total bookings (only core statuses for consistency)
        $total = $pending + $approved + $rejected + $cancelled;
            
        // FKMP vs Non-FKMP
        $fkmp = $db->table('bookings b')
            ->join('faculties f', 'f.id = b.faculty_id', 'left')
            ->whereIn('b.lab_id', $labIds)
            ->where('f.is_fkmp', 1)
            ->whereIn('b.status', $statusFilter)
            ->countAllResults();
            
        $nonFkmp = $db->table('bookings b')
            ->join('faculties f', 'f.id = b.faculty_id', 'left')
            ->whereIn('b.lab_id', $labIds)
            ->where('f.is_fkmp', 0)
            ->whereIn('b.status', $statusFilter)
            ->countAllResults();
            
        // Current week vs last week
        $currentWeek = $db->table('bookings')
            ->whereIn('lab_id', $labIds)
            ->whereIn('status', $statusFilter)
            ->where('YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)')
            ->countAllResults();
            
        $lastWeek = $db->table('bookings')
            ->whereIn('lab_id', $labIds)
            ->whereIn('status', $statusFilter)
            ->where('YEARWEEK(date, 1) = YEARWEEK(CURDATE() - INTERVAL 1 WEEK, 1)')
            ->countAllResults();
            
        $weekGrowth = $lastWeek > 0 ? (($currentWeek - $lastWeek) / $lastWeek * 100) : ($currentWeek > 0 ? 100 : 0);
        
        return [
            'total' => $total,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'cancelled' => $cancelled,
            'fkmp' => $fkmp,
            'nonFkmp' => $nonFkmp,
            'currentWeek' => $currentWeek,
            'lastWeek' => $lastWeek,
            'weekGrowth' => round($weekGrowth, 1),
            'pendingManager' => $this->getPendingManagerCount($labIds),
            'maintenanceOpen' => empty($labIds) ? 0 : (int) \Config\Database::connect()->table('maintenance_records mr')->join('assets a', 'a.id = mr.asset_id', 'inner')->whereIn('a.lab_id', $labIds)->whereIn('mr.status', ['reported', 'scheduled', 'in_progress', 'testing'])->countAllResults(),
            'maintenanceCompleted' => empty($labIds) ? 0 : (int) \Config\Database::connect()->table('maintenance_records mr')->join('assets a', 'a.id = mr.asset_id', 'inner')->whereIn('a.lab_id', $labIds)->where('mr.status', 'completed')->countAllResults(),
            'upcomingApproved' => empty($labIds) ? 0 : (int) \Config\Database::connect()->table('bookings')->whereIn('lab_id', $labIds)->where('status', 'APPROVED')->where('date >=', date('Y-m-d'))->where('date <=', date('Y-m-d', strtotime('+7 days')))->countAllResults(),
        ];
    }
    
    /**
     * Get pending manager approval count
     */
    private function getPendingManagerCount(array $labIds): int
    {
        $db = \Config\Database::connect();
        
        return $db->table('bookings b')
            ->join('faculties f', 'f.id = b.faculty_id', 'left')
            ->whereIn('b.lab_id', $labIds)
            ->where('b.status', 'PENDING')
            ->where('b.approved_by_pic', 1)
            ->where('b.approved_by_manager', 0)
            ->where('f.is_fkmp', 0)
            ->countAllResults();
    }

    /**
 * Get weekly utilization data
 */
private function getWeeklyUtilization(array $labIds, int $weeks = 8): array
{
    if (empty($labIds)) return [];
    
    $db = \Config\Database::connect();
    
    $result = $db->table('bookings')
        ->select("
            YEARWEEK(date, 1) as week,
            COUNT(*) as total_bookings,
            COUNT(DISTINCT lab_id) as labs_used
        ")
        ->whereIn('lab_id', $labIds)
        ->where('status', 'APPROVED')
        ->where('date >=', date('Y-m-d', strtotime("-$weeks weeks")))
        ->groupBy('YEARWEEK(date, 1)')  // Group by the same expression
        ->orderBy('week', 'DESC')
        ->get()
        ->getResultArray();
        
    // Process results and add calculated fields
    $processed = [];
    foreach ($result as $row) {
        // Calculate average utilization: (bookings / (labs * 20 slots)) * 100
        $avgUtilization = $row['labs_used'] > 0 
            ? round(($row['total_bookings'] / ($row['labs_used'] * 20)) * 100, 1)
            : 0;
            
        // Create week label
        $year = substr($row['week'], 0, 4);
        $weekNum = substr($row['week'], 4);
        $weekLabel = "Week " . intval($weekNum);
        
        $processed[] = [
            'week' => $row['week'],
            'week_label' => $weekLabel,
            'total_bookings' => (int)$row['total_bookings'],
            'labs_used' => (int)$row['labs_used'],
            'avg_utilization' => $avgUtilization
        ];
    }
    
    return array_reverse($processed); // Oldest first
}
    
    /**
 * Get peak hours analysis
 */
private function getPeakHoursAnalysis(array $labIds): array
{
    $db = \Config\Database::connect();
    
    // Peak hours by time slot - FIXED
    $timeSlots = $db->table('bookings')
        ->select("
            HOUR(start_time) as hour,
            COUNT(*) as booking_count
        ")
        ->whereIn('lab_id', $labIds)
        ->where('status', 'APPROVED')
        ->where('start_time >=', '08:00:00')
        ->where('start_time <=', '17:00:00')
        ->groupBy('hour')  // Group by the alias or expression
        ->orderBy('hour', 'ASC')
        ->get()
        ->getResultArray();
        
    // Add hour labels
    foreach ($timeSlots as &$slot) {
        $slot['hour_label'] = sprintf('%02d:00', $slot['hour']);
    }
    
    // Busiest days - FIXED
    // First get total approved bookings for percentage calculation
    $totalApproved = $db->table('bookings')
        ->whereIn('lab_id', $labIds)
        ->where('status', 'APPROVED')
        ->countAllResults();
    
    $busyDays = $db->table('bookings')
        ->select("
            DAYNAME(date) as day,
            COUNT(*) as booking_count
        ")
        ->whereIn('lab_id', $labIds)
        ->where('status', 'APPROVED')
        ->groupBy('day')  // Group by the alias
        ->orderBy('booking_count', 'DESC')
        ->get()
        ->getResultArray();
        
    // Calculate percentages
    foreach ($busyDays as &$day) {
        $day['percentage'] = $totalApproved > 0 
            ? round(($day['booking_count'] / $totalApproved) * 100, 1)
            : 0;
    }
    
    return [
        'timeSlots' => $timeSlots,
        'busyDays' => $busyDays
    ];
}
    
    /**
     * Get lab performance metrics
     */
    private function getLabPerformance(array $labs): array
    {
        $db = \Config\Database::connect();
        $labPerformance = [];
        
        foreach ($labs as $lab) {
            // Last 30 days stats
            $stats = $db->table('bookings')
                ->select("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled
                ")
                ->where('lab_id', $lab['id'])
                ->whereIn('status', BookingModel::CORE_STATUSES)
                ->where('created_at >=', date('Y-m-d', strtotime('-30 days')))
                ->get()
                ->getRowArray();
                
            // Utilization for last 7 days
            $weeklyBookings = $db->table('bookings')
                ->where('lab_id', $lab['id'])
                ->where('status', 'APPROVED')
                ->where('date >=', date('Y-m-d', strtotime('-7 days')))
                ->countAllResults();
                
            // Max possible: 5 weekdays * 4 slots = 20 slots/week
            $weeklyUtilization = $weeklyBookings > 0 ? min(100, ($weeklyBookings / 20) * 100) : 0;
            
            $labPerformance[] = [
                'id' => $lab['id'],
                'name' => $lab['name'],
                'room' => $lab['room'],
                'pic_name' => $lab['pic_name'],
                'total' => (int)($stats['total'] ?? 0),
                'approved' => (int)($stats['approved'] ?? 0),
                'pending' => (int)($stats['pending'] ?? 0),
                'rejected' => (int)($stats['rejected'] ?? 0),
                'cancelled' => (int)($stats['cancelled'] ?? 0),
                'weekly_utilization' => round($weeklyUtilization, 1),
            ];
        }
        
        // Sort by weekly utilization descending
        usort($labPerformance, function($a, $b) {
            return $b['weekly_utilization'] - $a['weekly_utilization'];
        });
        
        return $labPerformance;
    }
    
    /**
     * Get monthly booking trends
     */
    private function getMonthlyTrends(array $labIds, int $months = 6): array
    {
        $db = \Config\Database::connect();
        
        $result = $db->table('bookings')
            ->select("DATE_FORMAT(date, '%Y-%m') as month, COUNT(*) as total")
            ->whereIn('lab_id', $labIds)
            ->whereIn('status', BookingModel::CORE_STATUSES)
            ->where('date >=', date('Y-m-01', strtotime("-$months months")))
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->get()
            ->getResultArray();
            
        $data = [];
        foreach ($result as $row) {
            $data[] = [
                'month' => date('M Y', strtotime($row['month'] . '-01')),
                'total' => (int)$row['total']
            ];
        }
        
        return $data;
    }
    
    /**
 * Get faculty distribution
 */
private function getFacultyDistribution(array $labIds): array
{
    $db = \Config\Database::connect();
    
    $result = $db->table('bookings b')
        ->select('f.name_en as faculty, f.is_fkmp, COUNT(*) as count')
        ->join('faculties f', 'f.id = b.faculty_id', 'left')
        ->whereIn('b.lab_id', $labIds)
        ->where('b.status', 'APPROVED')
        ->groupBy('b.faculty_id, f.name_en, f.is_fkmp')  // Include all non-aggregated columns
        ->orderBy('count', 'DESC')
        ->limit(5)
        ->get()
        ->getResultArray();
        
    return $result;
}
    
    /**
 * Get asset usage statistics
 */
    private function getAssetUsageStats(array $labIds): array
    {
        $db = \Config\Database::connect();
    
    // Most used assets in last 30 days - FIXED
    $mostUsed = $db->table('booking_assets ba')
        ->select('a.name, SUM(ba.quantity_used) as total_used')
        ->join('assets a', 'a.id = ba.asset_id')
        ->join('bookings b', 'b.id = ba.booking_id')
        ->whereIn('b.lab_id', $labIds)
        ->where('b.status', 'APPROVED')
        ->where('b.date >=', date('Y-m-d', strtotime('-30 days')))
        ->groupBy('ba.asset_id, a.name')  // Include asset name in GROUP BY
        ->orderBy('total_used', 'DESC')
        ->limit(5)
        ->get()
        ->getResultArray();
        
        return [
            'mostUsed' => $mostUsed
        ];
    }

    /**
     * Parse insight period (weeks) from query string.
     */
    private function parseInsightWeeks(?string $value): int
    {
        $allowed = [2, 4, 8, 12];
        $weeks = (int) $value;
        if (!in_array($weeks, $allowed, true)) {
            $weeks = 8;
        }
        return $weeks;
    }


    /**
     * Simple demand insight for manager dashboard.
     */
    private function getDemandInsights(array $labIds, int $weeks): array
    {
        if (empty($labIds)) {
            return [
                'period_weeks' => $weeks,
                'total_bookings' => 0,
                'top_slots' => []
            ];
        }

        $db = \Config\Database::connect();
        $since = date('Y-m-d', strtotime("-$weeks weeks"));

        $rows = $db->table('bookings')
            ->select('date, start_time')
            ->whereIn('lab_id', $labIds)
            ->where('status', 'APPROVED')
            ->where('date >=', $since)
            ->get()
            ->getResultArray();

        $counts = [];
        foreach ($rows as $row) {
            $day = date('D', strtotime($row['date']));
            $slot = $this->mapTimeToSlot($row['start_time']);
            if (!$slot) {
                continue;
            }
            $key = $day . ' ' . $slot;
            if (!isset($counts[$key])) {
                $counts[$key] = 0;
            }
            $counts[$key]++;
        }

        arsort($counts);
        $topSlots = [];
        foreach ($counts as $key => $count) {
            $topSlots[] = [
                'label' => $key,
                'count' => $count
            ];
            if (count($topSlots) >= 3) {
                break;
            }
        }

        return [
            'period_weeks' => $weeks,
            'total_bookings' => count($rows),
            'top_slots' => $topSlots
        ];
    }

    /**
     * Map a start time to a standard slot label.
     */
    private function mapTimeToSlot(string $startTime): ?string
    {
        $time = substr(trim($startTime), 0, 5);

        if ($time >= '08:00' && $time < '10:00') {
            return '08:00-10:00';
        }
        if ($time >= '10:00' && $time < '12:00') {
            return '10:00-12:00';
        }
        if ($time >= '13:00' && $time < '15:00') {
            return '13:00-15:00';
        }
        if ($time >= '15:00' && $time < '17:00') {
            return '15:00-17:00';
        }

        return null;
    }


    
    /**
     * Get booking details for modal (AJAX endpoint)
     */
    public function getBookingDetails($id)
    {
        helper('auth');
        
        if (! auth()->loggedIn() || ! auth()->user()->inGroup('manager')) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Unauthorized'
            ]);
        }
        
        $bookingModel = new BookingModel();
        $bookingAssetModel = new BookingAssetModel();
        
        $booking = $bookingModel
            ->select('bookings.*, 
                     laboratories.name AS lab_name, laboratories.room AS lab_room,
                     laboratories.pic_name, laboratories.pic_email, laboratories.pic_phone,
                     faculties.name_en AS faculty_name, faculties.is_fkmp')
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->join('faculties', 'faculties.id = bookings.faculty_id', 'left')
            ->where('bookings.id', $id)
            ->first();
            
        if (!$booking) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Booking not found'
            ]);
        }
        
        // Get assets
        $booking['assets'] = $bookingAssetModel
            ->select('assets.*, booking_assets.quantity_used')
            ->join('assets', 'assets.id = booking_assets.asset_id')
            ->where('booking_assets.booking_id', $id)
            ->findAll();
            
        // Add PDF URL for secure access
        if (!empty($booking['pdf_path'])) {
            $filename = basename($booking['pdf_path']);
            $booking['pdf_url'] = site_url('document/pdf/' . $filename);
        } else {
            $booking['pdf_url'] = null;
        }
        
        return $this->response->setJSON([
            'status' => 'success',
            'booking' => $booking
        ]);
    }
}

