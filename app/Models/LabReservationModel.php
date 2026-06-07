<?php

namespace App\Models;

use CodeIgniter\Model;

class LabReservationModel extends Model
{
    protected $table      = 'lab_reservations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'lab_id', 'type', 'title', 'recurrence',
        'date', 'day_of_week', 'start_time', 'end_time',
        'valid_from', 'valid_until', 'created_by', 'notes',
        'created_at', 'updated_at',
    ];

    /**
     * Return the first reservation that overlaps the requested lab/date/time,
     * or null if the slot is free.
     *
     * Day-of-week uses MySQL WEEKDAY() convention: 0=Monday … 6=Sunday.
     */
    public function conflictsWithSlot(int $labId, string $date, string $start, string $end): ?array
    {
        $row = $this->db->query(
            "SELECT id, title, type
             FROM lab_reservations
             WHERE lab_id = ?
               AND (
                     (recurrence = 'none'
                      AND `date` = ?
                      AND start_time < ?
                      AND end_time > ?)
                  OR
                     (recurrence = 'weekly'
                      AND day_of_week = WEEKDAY(?)
                      AND start_time < ?
                      AND end_time > ?
                      AND (valid_from  IS NULL OR valid_from  <= ?)
                      AND (valid_until IS NULL OR valid_until >= ?))
               )
             LIMIT 1",
            [$labId, $date, $end, $start, $date, $end, $start, $date, $date]
        )->getRowArray();

        return $row ?: null;
    }
}
