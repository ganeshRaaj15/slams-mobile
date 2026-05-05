<?php

namespace App\Models;

use CodeIgniter\Model;

class BookingModel extends Model
{
    public const ACTIVE_STATUSES = ['PENDING', 'APPROVED'];
    public const CORE_STATUSES = ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'];

    protected $table            = 'bookings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;

    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'user_id',
        'lab_id',
        'service_id',
        'user_type',
        'faculty_id',
        'approval_flow',
        'approved_by_pic',
        'approved_by_manager',
        'date',
        'start_time',
        'end_time',
        'activity',
        'supervisor_name',
        'supervisor_email',
        'supervisor_phone',
        'pdf_path',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * ---------------------------------------------------------
     * BASIC HELPERS
     * ---------------------------------------------------------
     */

    public function getBookingsForDate($labId, $date)
    {
        return $this->where('lab_id', $labId)
                    ->where('date', $date)
                    ->findAll();
    }

    public function slotTaken($labId, $date, $start, $end, ?int $ignoreBookingId = null)
    {
        return $this->hasLabConflict((int) $labId, (string) $date, (string) $start, (string) $end, $ignoreBookingId);
    }

    public function hasLabConflict(int $labId, string $date, string $start, string $end, ?int $ignoreBookingId = null): bool
    {
        return $this->labConflictBuilder($labId, $date, $start, $end, $ignoreBookingId)
            ->countAllResults() > 0;
    }

    public function findLabConflicts(int $labId, string $date, string $start, string $end, ?int $ignoreBookingId = null): array
    {
        return $this->labConflictBuilder($labId, $date, $start, $end, $ignoreBookingId)
            ->orderBy('start_time', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function activeLabConflictsForUpdate(int $labId, string $date, string $start, string $end, ?int $ignoreBookingId = null): array
    {
        $params = [$labId, $date, $end, $start];
        $ignoreSql = '';

        if ($ignoreBookingId !== null && $ignoreBookingId > 0) {
            $ignoreSql = ' AND id != ?';
            $params[] = $ignoreBookingId;
        }

        $sql = "
            SELECT id, lab_id, date, start_time, end_time, status
            FROM bookings
            WHERE lab_id = ?
              AND date = ?
              AND status IN ('PENDING', 'APPROVED')
              AND start_time < ?
              AND end_time > ?
              {$ignoreSql}
            FOR UPDATE
        ";

        return $this->db->query($sql, $params)->getResultArray();
    }

    protected function labConflictBuilder(int $labId, string $date, string $start, string $end, ?int $ignoreBookingId = null)
    {
        $builder = $this->db->table($this->table)
            ->select('id, lab_id, date, start_time, end_time, status')
            ->where('lab_id', $labId)
            ->where('date', $date)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where('start_time <', $end)
            ->where('end_time >', $start);

        if ($ignoreBookingId !== null && $ignoreBookingId > 0) {
            $builder->where('id !=', $ignoreBookingId);
        }

        return $builder;
    }

    /**
     * =========================================================
     * 🔹 ANALYTICS HELPERS (FOR DASHBOARDS)
     * =========================================================
     */

    public function countByStatus(): array
    {
        $db = \Config\Database::connect();

        return [
            'pending' => $db->table('bookings')
                ->where('status', 'PENDING')
                ->where('approved_by_pic', 0)
                ->countAllResults(),

            'pending_mgr' => $db->table('bookings')
                ->where('status', 'PENDING')
                ->where('approved_by_pic', 1)
                ->where('approved_by_manager', 0)
                ->countAllResults(),

            'approved' => $db->table('bookings')
                ->where('status', 'APPROVED')
                ->countAllResults(),

            'rejected' => $db->table('bookings')
                ->where('status', 'REJECTED')
                ->countAllResults(),

            'cancelled' => $db->table('bookings')
                ->where('status', 'CANCELLED')
                ->countAllResults(),
        ];
    }

    public function picApprovalCount(): int
    {
        return $this->where('approved_by_pic', 1)->countAllResults();
    }

    public function managerApprovalCount(): int
    {
        return $this->where('approved_by_manager', 1)->countAllResults();
    }

    /**
     * Trend by month
     */
    public function monthlyTrend(): array
    {
        return $this->select("DATE_FORMAT(date, '%Y-%m') AS month, COUNT(*) AS total")
                    ->whereIn('status', self::CORE_STATUSES)
                    ->groupBy("DATE_FORMAT(date, '%Y-%m')")
                    ->orderBy("month", "ASC")
                    ->findAll();
    }

    /**
     * Returns bookings grouped per month for past X months
     */
    public function getMonthlyBookings(int $months = 6)
    {
        $sql = "
            SELECT DATE_FORMAT(date, '%Y-%m') AS month, COUNT(*) AS count
            FROM bookings
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
              AND status IN ('PENDING', 'APPROVED', 'REJECTED', 'CANCELLED')
            GROUP BY DATE_FORMAT(date, '%Y-%m')
            ORDER BY month ASC
        ";

        return $this->db->query($sql, [$months])->getResultArray();
    }

    /**
     * Count bookings grouped by faculty (JOIN faculties table)
     */
    public function getBookingsPerFaculty()
    {
        $sql = "
            SELECT 
                faculties.name_en AS faculty_name,
                COUNT(*) AS count
            FROM bookings
            LEFT JOIN faculties ON faculties.id = bookings.faculty_id
            GROUP BY faculties.name_en
            ORDER BY count DESC
        ";

        return $this->db->query($sql)->getResultArray();
    }

    /**
     * Count bookings grouped per laboratory (JOIN labs table)
     */
    public function getBookingsPerLab()
    {
        $sql = "
            SELECT 
                laboratories.name AS lab_name,
                COUNT(*) AS count
            FROM bookings
            LEFT JOIN laboratories ON laboratories.id = bookings.lab_id
            GROUP BY laboratories.name
            ORDER BY count DESC
        ";

        return $this->db->query($sql)->getResultArray();
    }
}

