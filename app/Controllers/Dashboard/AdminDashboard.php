<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Models\BookingModel;
use App\Models\LaboratoryModel;
use Config\Database;

class AdminDashboard extends BaseController
{
    protected $bookings;
    protected $labs;
    protected $db;

    public function __construct()
    {
        helper('auth');

        $this->bookings = new BookingModel();
        $this->labs     = new LaboratoryModel();
        $this->db       = Database::connect();
    }

    /**
     * Admin Dashboard
     * Shows:
     * - KPI counts
     * - Monthly booking trends
     * - Faculty booking breakdown
     * - Approval queues (PIC + Manager)
     * - Rejected + approved lists
     */
    public function index()
    {
        // Access control
        if (!auth()->loggedIn() || !auth()->user()->inGroup('admin')) {
            return redirect()
                ->to('/dashboard')
                ->with('error', 'Access denied.');
        }

        $user = auth()->user();

        // ------------------------------------------------------------
        // 1. KPI STATISTICS
        // ------------------------------------------------------------
        $stats = $this->bookings->countByStatus();

        // Ensure all keys exist (prevents undefined index errors)
        $stats = array_merge([
            'pending'      => 0,
            'pending_mgr'  => 0,
            'approved'     => 0,
            'rejected'     => 0,
            'cancelled'    => 0,
        ], $stats);

        // Add pending manager KPI (was missing before)
        $stats['pending_mgr'] = $this->bookings
            ->where('status', 'PENDING')
            ->where('approved_by_pic', 1)
            ->where('approved_by_manager', 0)
            ->countAllResults();

        $stats['total'] = $stats['pending'] + $stats['pending_mgr'] + $stats['approved'] + $stats['rejected'] + $stats['cancelled'];

        // ------------------------------------------------------------
        // 2. MONTHLY TRENDS (chart)
        // ------------------------------------------------------------
        $trends = $this->bookings->monthlyTrend();

        // ------------------------------------------------------------
        // 3. FACULTY BREAKDOWN (pie chart)
        // ------------------------------------------------------------
        $facultyBreakdown = $this->db->table('bookings')
            ->select('faculties.name_en AS faculty, COUNT(bookings.id) AS total')
            ->join('faculties', 'faculties.id = bookings.faculty_id', 'left')
            ->groupBy('faculties.name_en')
            ->orderBy('total', 'DESC')
            ->get()
            ->getResultArray();

        // ------------------------------------------------------------
        // 4. LAB LIST
        // ------------------------------------------------------------
        $labs = $this->labs->orderBy('name', 'ASC')->findAll();

        // ------------------------------------------------------------
        // 5. APPROVAL QUEUE LISTS
        // ------------------------------------------------------------

        // Stage 1: Pending PIC
        $pendingPic = $this->bookings
    ->select("
        bookings.*,
        laboratories.name AS lab_name,
        laboratories.room AS lab_room,
        faculties.name_en AS faculty_name
    ")
    ->join("laboratories", "laboratories.id = bookings.lab_id", "left")
    ->join("faculties", "faculties.id = bookings.faculty_id", "left")
    ->where('status', 'PENDING')
    ->where('approved_by_pic', 0)
    ->orderBy('date', 'ASC')
    ->findAll();


        // Stage 2: Pending Manager
        $pendingMgr = $this->bookings
    ->select("
        bookings.*,
        laboratories.name AS lab_name,
        laboratories.room AS lab_room,
        faculties.name_en AS faculty_name
    ")
    ->join("laboratories", "laboratories.id = bookings.lab_id", "left")
    ->join("faculties", "faculties.id = bookings.faculty_id", "left")
    ->where('status', 'PENDING')
    ->where('approved_by_pic', 1)
    ->where('approved_by_manager', 0)
    ->orderBy('date', 'ASC')
    ->findAll();

        // Fully approved
        $approved = $this->bookings
    ->select("
        bookings.*,
        laboratories.name AS lab_name,
        laboratories.room AS lab_room,
        faculties.name_en AS faculty_name
    ")
    ->join("laboratories", "laboratories.id = bookings.lab_id", "left")
    ->join("faculties", "faculties.id = bookings.faculty_id", "left")
    ->where('status', 'APPROVED')
    ->orderBy('date', 'DESC')
    ->findAll();


        // Rejected
        $rejected = $this->bookings
    ->select("
        bookings.*,
        laboratories.name AS lab_name,
        laboratories.room AS lab_room,
        faculties.name_en AS faculty_name
    ")
    ->join("laboratories", "laboratories.id = bookings.lab_id", "left")
    ->join("faculties", "faculties.id = bookings.faculty_id", "left")
    ->where('status', 'REJECTED')
    ->orderBy('date', 'DESC')
    ->findAll();


        $maintenanceStats = [
            'open' => (int) $this->db->table('maintenance_records')
                ->whereIn('status', ['reported', 'scheduled', 'in_progress', 'testing'])
                ->countAllResults(),
            'completed' => (int) $this->db->table('maintenance_records')
                ->where('status', 'completed')
                ->countAllResults(),
            'upcoming' => (int) $this->db->table('bookings')
                ->where('status', 'APPROVED')
                ->where('date >=', date('Y-m-d'))
                ->where('date <=', date('Y-m-d', strtotime('+7 days')))
                ->countAllResults(),
        ];
        // ------------------------------------------------------------
        // 6. RETURN VIEW
        // ------------------------------------------------------------
        return view('dashboard/admin/index', [
            'user'             => $user,
            'labs'             => $labs,

            // Approval queues
            'pendingPic'       => $pendingPic,
            'pendingMgr'       => $pendingMgr,
            'approved'         => $approved,
            'rejected'         => $rejected,

            // Dashboard widgets
            'stats'            => $stats,
            'trends'           => $trends,

            // Charts
            'facultyBreakdown' => $facultyBreakdown,
            'maintenanceStats' => $maintenanceStats,
        ]);
    }
}

