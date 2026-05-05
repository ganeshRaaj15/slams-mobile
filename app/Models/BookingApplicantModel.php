<?php

namespace App\Models;

use CodeIgniter\Model;

class BookingApplicantModel extends Model
{
    protected $table            = 'booking_applicants';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;

    protected $returnType       = 'array';

    protected $allowedFields = [
        'booking_id',
        'name',
        'matric_id',
        'email',
        'phone',
        'faculty',
    ];

    // Get all applicants for a booking
    public function getForBooking($bookingId)
    {
        return $this->where('booking_id', $bookingId)->findAll();
    }

    // Bulk-insert for convenience
    public function insertBatchApplicants($bookingId, $applicants)
    {
        $dataToInsert = [];

        foreach ($applicants as $a) {
            $dataToInsert[] = [
                'booking_id' => $bookingId,
                'name'       => $a['name'],
                'matric_id'  => $a['matric_id'],
                'email'      => $a['email'],
                'phone'      => $a['phone'],
                'faculty'    => $a['faculty'],
            ];
        }

        return $this->insertBatch($dataToInsert);
    }
}
