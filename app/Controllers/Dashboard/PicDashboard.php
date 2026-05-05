<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Models\BookingModel;
use App\Models\LaboratoryModel;
use App\Models\FacultyModel;
use App\Models\BookingAssetModel;
use App\Models\AssetModel;

class PicDashboard extends BaseController
{
    public function index()
    {
        helper('auth');

        // Must be PIC
        if (! auth()->loggedIn() || ! auth()->user()->inGroup('pic')) {
            return redirect()->to('/dashboard')->with('error', 'Access denied.');
        }

        $user     = auth()->user();
        $picEmail = strtolower(trim((string) $user->email));

        $labModel     = new LaboratoryModel();
        $bookingModel = new BookingModel();
        $facultyModel = new FacultyModel();
        $bookingAssetModel = new BookingAssetModel();

        // -----------------------------------------------------
        // 1. Labs owned by this PIC
        // -----------------------------------------------------
        $labs = $labModel
            ->where("LOWER(TRIM(pic_email)) =", $picEmail)
            ->findAll();

        $labIds = array_column($labs, 'id');

        if (empty($labIds)) {
            return view('dashboard/pic/index', [
                'user'          => $user,
                'labs'          => [],
                'pendingPic'    => [],
                'pendingMgr'    => [],
                'approved'      => [],
                'rejected'      => [],
                'widget'        => ['pending' => 0, 'pending_mgr' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0, 'total' => 0],
                'monthlyCounts' => [],
                'facultyCounts' => [],
                'labCounts'     => [],
                'usageData'     => [],
                'facultyData'   => [],
                'maintenanceStats' => ['open' => 0, 'completed' => 0, 'upcoming' => 0],
            ]);
        }

        // -----------------------------------------------------
        // 2. PIC-relevant bookings with more details
        // -----------------------------------------------------

        // Stage 1: awaiting PIC approval (FKMP + non-FKMP) - with faculty info
        $pendingPic = $bookingModel
            ->select('bookings.*, laboratories.name AS lab_name, laboratories.room AS lab_room, 
                     faculties.name_en AS faculty_name, faculties.is_fkmp')
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->join('faculties', 'faculties.id = bookings.faculty_id', 'left')
            ->where("LOWER(TRIM(laboratories.pic_email)) =", $picEmail)
            ->where('bookings.status', 'PENDING')
            ->where('bookings.approved_by_pic', 0)
            ->orderBy('bookings.date', 'ASC')
            ->orderBy('bookings.start_time', 'ASC')
            ->findAll();

        // Fetch assets for each pending booking
        foreach ($pendingPic as &$booking) {
            $booking['assets'] = $bookingAssetModel
                ->select('assets.name, booking_assets.quantity_used, assets.image')
                ->join('assets', 'assets.id = booking_assets.asset_id')
                ->where('booking_assets.booking_id', $booking['id'])
                ->findAll();
        }

        // Stage 2: PIC approved, waiting Manager (non-FKMP only)
        $pendingMgr = $bookingModel
            ->select('bookings.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->where("LOWER(TRIM(laboratories.pic_email)) =", $picEmail)
            ->where('bookings.status', 'PENDING')
            ->where('bookings.approved_by_pic', 1)
            ->where('bookings.approved_by_manager', 0)
            ->where('bookings.approval_flow !=', 'FKMP_APPROVAL')
            ->orderBy('bookings.date', 'ASC')
            ->orderBy('bookings.start_time', 'ASC')
            ->findAll();

        // Fully approved bookings (anything approved for this PIC's labs)
        $approved = $bookingModel
            ->select('bookings.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->where("LOWER(TRIM(laboratories.pic_email)) =", $picEmail)
            ->where('bookings.status', 'APPROVED')
            ->orderBy('bookings.date', 'DESC')
            ->limit(10) // Show only recent ones
            ->findAll();

        // Rejected bookings
        $rejected = $bookingModel
            ->select('bookings.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->where("LOWER(TRIM(laboratories.pic_email)) =", $picEmail)
            ->where('bookings.status', 'REJECTED')
            ->orderBy('bookings.date', 'DESC')
            ->limit(5)
            ->findAll();

        $approvedCount = $bookingModel
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->where("LOWER(TRIM(laboratories.pic_email)) =", $picEmail)
            ->where('bookings.status', 'APPROVED')
            ->countAllResults();

        $rejectedCount = $bookingModel
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->where("LOWER(TRIM(laboratories.pic_email)) =", $picEmail)
            ->where('bookings.status', 'REJECTED')
            ->countAllResults();

        $cancelledCount = $bookingModel
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->where("LOWER(TRIM(laboratories.pic_email)) =", $picEmail)
            ->where('bookings.status', 'CANCELLED')
            ->countAllResults();

        // -----------------------------------------------------
        // 3. Dashboard widget numbers
        // -----------------------------------------------------
        $widget = [
            'pending'  => count($pendingPic),
            'pending_mgr' => count($pendingMgr),
            'approved' => $approvedCount,
            'rejected' => $rejectedCount,
            'cancelled' => $cancelledCount,
            'total'    => count($pendingPic) + count($pendingMgr) + $approvedCount + $rejectedCount + $cancelledCount,
        ];

        // -----------------------------------------------------
        // 4. Chart data (FILTERED for PIC's labs only)
        // -----------------------------------------------------
        $monthlyCounts = $this->getMonthlyBookingsForLabs($labIds, 6);
        $facultyCounts = $this->getFacultyDistributionForLabs($labIds);
        $usageData = $this->getUsageTrends($labIds);
        $maintenanceStats = $this->getMaintenanceStatsForLabs($labIds);
        
        // -----------------------------------------------------
        // 5. Render view
        // -----------------------------------------------------
        return view('dashboard/pic/index', [
            'user'          => $user,
            'labs'          => $labs,
            'pendingPic'    => $pendingPic,
            'pendingMgr'    => $pendingMgr,
            'approved'      => $approved,
            'rejected'      => $rejected,
            'widget'        => $widget,
            'monthlyCounts' => $monthlyCounts,
            'facultyCounts' => $facultyCounts,
            'usageData'     => $usageData,
            'maintenanceStats' => $maintenanceStats,
        ]);
    }

    /**
     * Get monthly bookings for specific labs
     */
    /**
 * Get monthly bookings for specific labs
 */
private function getMonthlyBookingsForLabs(array $labIds, int $months = 6): array
{
    if (empty($labIds)) return [];
    
    $bookingModel = new BookingModel();
    $db = \Config\Database::connect();
    
    $result = $db->table('bookings')
        ->select("DATE_FORMAT(date, '%Y-%m') as month, COUNT(*) as count")
        ->whereIn('lab_id', $labIds)
        ->whereIn('status', BookingModel::CORE_STATUSES)
        ->where('date >=', date('Y-m-01', strtotime("-$months months")))
        ->groupBy('month')  // Group by the alias
        ->orderBy('month', 'ASC')
        ->get()
        ->getResultArray();
        
    $data = [];
    foreach ($result as $row) {
        $data[] = [
            'month' => date('M Y', strtotime($row['month'] . '-01')),
            'count' => (int)$row['count']
        ];
    }
    
    return $data;
}
    
    /**
     * Get faculty distribution for specific labs
     */
    private function getFacultyDistributionForLabs(array $labIds): array
    {
        if (empty($labIds)) return [];
        
        $db = \Config\Database::connect();
        
        $result = $db->table('bookings b')
            ->select('f.name_en as faculty, COUNT(*) as count')
            ->join('faculties f', 'f.id = b.faculty_id', 'left')
            ->whereIn('b.lab_id', $labIds)
            ->where('b.status', 'APPROVED')
            ->groupBy('b.faculty_id')
            ->orderBy('count', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();
            
        return $result;
    }
    
    /**
     * Get usage trends (by day of week, time slots)
     */
    /**
 * Get usage trends (by day of week, time slots)
 */
private function getUsageTrends(array $labIds): array
{
    if (empty($labIds)) return [];
    
    $db = \Config\Database::connect();
    
    // Day of week distribution - FIXED QUERY
    $dayOfWeek = $db->table('bookings')
        ->select("DAYNAME(date) as day, COUNT(*) as count")
        ->whereIn('lab_id', $labIds)
        ->where('status', 'APPROVED')
        ->groupBy('day')  // Group by the alias instead of the function
        ->orderBy('FIELD(day, "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday")')
        ->get()
        ->getResultArray();
        
    // Time slot distribution - FIXED QUERY
    $timeSlots = $db->table('bookings')
        ->select("
            CASE 
                WHEN start_time >= '08:00:00' AND end_time <= '10:00:00' THEN '08:00-10:00'
                WHEN start_time >= '10:00:00' AND end_time <= '12:00:00' THEN '10:00-12:00'
                WHEN start_time >= '13:00:00' AND end_time <= '15:00:00' THEN '13:00-15:00'
                WHEN start_time >= '15:00:00' AND end_time <= '17:00:00' THEN '15:00-17:00'
                ELSE 'Other'
            END as slot,
            COUNT(*) as count
        ")
        ->whereIn('lab_id', $labIds)
        ->where('status', 'APPROVED')
        ->groupBy('slot')  // Group by the alias
        ->orderBy('slot', 'ASC')
        ->get()
        ->getResultArray();
        
    return [
        'dayOfWeek' => $dayOfWeek,
        'timeSlots' => $timeSlots
    ];
}
    
    private function getMaintenanceStatsForLabs(array $labIds): array
    {
        if (empty($labIds)) {
            return ['open' => 0, 'completed' => 0, 'upcoming' => 0];
        }

        $db = \Config\Database::connect();

        $open = (int) $db->table('maintenance_records mr')
            ->join('assets a', 'a.id = mr.asset_id', 'inner')
            ->whereIn('a.lab_id', $labIds)
            ->whereIn('mr.status', ['reported', 'scheduled', 'in_progress', 'testing'])
            ->countAllResults();

        $completed = (int) $db->table('maintenance_records mr')
            ->join('assets a', 'a.id = mr.asset_id', 'inner')
            ->whereIn('a.lab_id', $labIds)
            ->where('mr.status', 'completed')
            ->countAllResults();

        $upcoming = (int) $db->table('bookings')
            ->whereIn('lab_id', $labIds)
            ->where('status', 'APPROVED')
            ->where('date >=', date('Y-m-d'))
            ->where('date <=', date('Y-m-d', strtotime('+7 days')))
            ->countAllResults();

        return [
            'open' => $open,
            'completed' => $completed,
            'upcoming' => $upcoming,
        ];
    }
    /**
     * Get booking details for modal (AJAX endpoint)
     */
    public function getBookingDetails($id)
{
    helper('auth');
    
    if (! auth()->loggedIn() || ! auth()->user()->inGroup('pic')) {
        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'Unauthorized'
        ]);
    }
    
    $bookingModel = new BookingModel();
    $bookingAssetModel = new BookingAssetModel();
    $facultyModel = new FacultyModel();
    $labModel = new LaboratoryModel();
    
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

    $picEmail = strtolower(trim((string) auth()->user()->email));
    if (strtolower(trim((string) ($booking['pic_email'] ?? ''))) !== $picEmail) {
        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'Unauthorized'
        ])->setStatusCode(403);
    }
    
    // Get assets
    $booking['assets'] = $bookingAssetModel
        ->select('assets.*, booking_assets.quantity_used')
        ->join('assets', 'assets.id = booking_assets.asset_id')
        ->where('booking_assets.booking_id', $id)
        ->findAll();
        
    // Get applicant info (if from users table)
    $db = \Config\Database::connect();
    $applicant = $db->table('auth_identities')
        ->select('secret as email')
        ->where('user_id', $booking['user_id'])
        ->where('type', 'email_password')
        ->get()
        ->getRowArray();
        
    if ($applicant) {
        $booking['applicant_email'] = $applicant['email'];
    }
    
    // Add PDF URL for secure access
    if (!empty($booking['pdf_path'])) {
        // Extract filename from path
        $filename = basename($booking['pdf_path']);
        // Create secure URL route
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

