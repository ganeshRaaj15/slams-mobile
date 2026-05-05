<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Models\BookingModel;
use App\Models\BookingAssetModel;
use App\Models\AssetModel;

class StudentDashboard extends BaseController
{
    public function index()
    {
        helper('auth');

        if (!auth()->loggedIn() || (! auth()->user()->inGroup('student') && ! auth()->user()->inGroup('staff'))) {
            return redirect()->to('/dashboard')->with('error', 'Access denied.');
        }

        $user   = auth()->user();
        $userId = $user->id;
        $dashboardLabel = $user->inGroup('staff') ? 'Staff Dashboard' : 'Student Dashboard';

        $bookingModel = new BookingModel();
        $filters = $this->bookingFilters();

        $baseSelect = "
            bookings.*,
            laboratories.name AS lab_name,
            laboratories.room AS lab_room
        ";

        // All bookings
        $bookingsQuery = $bookingModel
            ->select($baseSelect)
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->where('bookings.user_id', $userId)
            ->whereIn('bookings.status', BookingModel::CORE_STATUSES);

        $bookings = $this->applyBookingFilters($bookingsQuery, $filters)
            ->orderBy('bookings.date', 'DESC')
            ->orderBy('bookings.start_time', 'ASC')
            ->findAll();

        // Stats
        $stats = [
            'pending'  => (new BookingModel())->where('user_id', $userId)->where('status', 'PENDING')->countAllResults(),
            'approved' => (new BookingModel())->where('user_id', $userId)->where('status', 'APPROVED')->countAllResults(),
            'rejected' => (new BookingModel())->where('user_id', $userId)->where('status', 'REJECTED')->countAllResults(),
            'cancelled' => (new BookingModel())->where('user_id', $userId)->where('status', 'CANCELLED')->countAllResults(),
        ];
        $stats['total'] = $stats['pending'] + $stats['approved'] + $stats['rejected'] + $stats['cancelled'];

        // Upcoming bookings
        $today = date('Y-m-d');
        $upcomingBookings = $bookingModel
            ->select($baseSelect)
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->where('bookings.user_id', $userId)
            ->whereIn('bookings.status', BookingModel::ACTIVE_STATUSES)
            ->where('bookings.date >=', $today)
            ->orderBy('bookings.date', 'ASC')
            ->orderBy('bookings.start_time', 'ASC')
            ->limit(10)
            ->findAll();

        $nextBooking = $upcomingBookings[0] ?? null;

        // Monthly trend (ONLY_FULL_GROUP_BY safe)
        $db = \Config\Database::connect();
        $monthlyRows = $db->table('bookings')
            ->select("DATE_FORMAT(date, '%b %Y') AS month, COUNT(*) AS count", false)
            ->where('user_id', $userId)
            ->whereIn('status', BookingModel::CORE_STATUSES)
            ->groupBy("DATE_FORMAT(date, '%b %Y')", false)
            ->orderBy("MIN(date)", "DESC", false)
            ->limit(6)
            ->get()
            ->getResultArray();

        $monthlyCounts = array_reverse($monthlyRows);

        // Personalized hints
        $db = \Config\Database::connect();
        $topLab = $db->table('bookings b')
            ->select('b.lab_id, laboratories.name AS lab_name, COUNT(*) AS total', false)
            ->join('laboratories', 'laboratories.id = b.lab_id', 'left')
            ->where('b.user_id', $userId)
            ->where('b.status', 'APPROVED')
            ->groupBy('b.lab_id')
            ->orderBy('total', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        $timeRows = $db->table('bookings')
            ->select('start_time')
            ->where('user_id', $userId)
            ->where('status', 'APPROVED')
            ->get()
            ->getResultArray();

        $slotCounts = [];
        foreach ($timeRows as $row) {
            $slot = $this->mapTimeToSlot($row['start_time']);
            if (!$slot) continue;
            if (!isset($slotCounts[$slot])) {
                $slotCounts[$slot] = 0;
            }
            $slotCounts[$slot]++;
        }
        arsort($slotCounts);
        $topSlot = null;
        if (!empty($slotCounts)) {
            $topSlot = array_key_first($slotCounts);
        }

        $personalizedHints = [
            'lab_name' => $topLab['lab_name'] ?? null,
            'slot' => $topSlot,
        ];

        return view('dashboard/student/index', [
            'user'             => $user,
            'bookings'         => $bookings,
            'stats'            => $stats,
            'upcomingBookings' => $upcomingBookings,
            'nextBooking'      => $nextBooking,
            'monthlyCounts'    => $monthlyCounts,
            'personalizedHints'=> $personalizedHints,
            'dashboardLabel'   => $dashboardLabel,
            'filters'          => $filters,
        ]);
    }

    private function bookingFilters(): array
    {
        $status = trim((string) $this->request->getGet('status'));
        if (! in_array($status, BookingModel::CORE_STATUSES, true)) {
            $status = '';
        }

        return [
            'q' => trim((string) $this->request->getGet('q')),
            'status' => $status,
            'date_from' => $this->validDate((string) $this->request->getGet('date_from')),
            'date_to' => $this->validDate((string) $this->request->getGet('date_to')),
        ];
    }

    private function applyBookingFilters($query, array $filters)
    {
        if ($filters['q'] !== '') {
            $query->groupStart()
                ->like('laboratories.name', $filters['q'])
                ->orLike('laboratories.room', $filters['q'])
                ->orLike('bookings.activity', $filters['q'])
                ->groupEnd();
        }
        if ($filters['status'] !== '') {
            $query->where('bookings.status', $filters['status']);
        }
        if ($filters['date_from'] !== '') {
            $query->where('bookings.date >=', $filters['date_from']);
        }
        if ($filters['date_to'] !== '') {
            $query->where('bookings.date <=', $filters['date_to']);
        }

        return $query;
    }

    private function validDate(string $value): string
    {
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }

    private function mapTimeToSlot(string $startTime): ?string
    {
        $time = substr(trim($startTime), 0, 5);

        if ($time >= '08:00' && $time < '10:00') return '08:00-10:00';
        if ($time >= '10:00' && $time < '12:00') return '10:00-12:00';
        if ($time >= '13:00' && $time < '15:00') return '13:00-15:00';
        if ($time >= '15:00' && $time < '17:00') return '15:00-17:00';

        return null;
    }

    // =====================================================
    // BOOKING DETAILS (MODAL AJAX)
    // =====================================================
    public function bookingDetails($id)
    {
        helper('auth');

        $userId = auth()->id();
        $bookingModel = new BookingModel();
        $bookingAssetModel = new BookingAssetModel();

        $booking = $bookingModel
            ->select("
                bookings.*,
                laboratories.name AS lab_name,
                laboratories.room AS lab_room
            ")
            ->join('laboratories', 'laboratories.id = bookings.lab_id', 'left')
            ->where('bookings.id', $id)
            ->where('bookings.user_id', $userId)
            ->first();

        if (!$booking) {
            return $this->response->setJSON(['success' => false, 'message' => 'Booking not found']);
        }

        $assets = $bookingAssetModel
            ->select("booking_assets.*, assets.name")
            ->join("assets", "assets.id = booking_assets.asset_id", "left")
            ->where("booking_id", $id)
            ->findAll();

        return $this->response->setJSON([
            'success' => true,
            'booking' => $booking,
            'assets'  => $assets
        ]);
    }

    // =====================================================
    // CANCEL BOOKING
    // =====================================================
    public function cancelBooking($id)
    {
        helper('auth');

        $userId = auth()->id();
        $bookingModel = new BookingModel();

        $booking = $bookingModel->where('id', $id)->where('user_id', $userId)->first();

        if (!$booking) {
            return $this->response->setJSON(['success' => false, 'message' => 'Booking not found']);
        }

        if ($booking['status'] !== 'PENDING') {
            return $this->response->setJSON(['success' => false, 'message' => 'Only pending bookings can be cancelled.']);
        }

        $bookingModel->update($id, [
            'status' => 'CANCELLED',
            'approved_by_pic' => 0,
            'approved_by_manager' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Booking cancelled successfully.'
        ]);
    }
}

