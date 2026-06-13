<?php

namespace App\Models;

use CodeIgniter\Model;

class LabReservationModel extends Model
{
    protected $table = 'lab_reservations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'lab_id',
        'title',
        'reservation_type',
        'start_at',
        'end_at',
        'notes',
        'status',
        'created_by',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function overlaps(int $labId, string $startAt, string $endAt, int $ignoreId = 0): bool
    {
        return $this->overlapBuilder($labId, $startAt, $endAt, $ignoreId)->countAllResults() > 0;
    }

    public function overlappingReservations(int $labId, string $startAt, string $endAt, int $ignoreId = 0): array
    {
        return $this->overlapBuilder($labId, $startAt, $endAt, $ignoreId)
            ->orderBy('start_at', 'ASC')
            ->findAll();
    }

    protected function overlapBuilder(int $labId, string $startAt, string $endAt, int $ignoreId = 0)
    {
        $builder = $this->builder()
            ->where('lab_id', $labId)
            ->where('status', 'active')
            ->where('start_at <', $endAt)
            ->where('end_at >', $startAt);

        if ($ignoreId > 0) {
            $builder->where('id !=', $ignoreId);
        }

        return $builder;
    }
}
