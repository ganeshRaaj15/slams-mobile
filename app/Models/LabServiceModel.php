<?php

namespace App\Models;

use CodeIgniter\Model;

class LabServiceModel extends Model
{
    protected $table = 'lab_services';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'laboratory_id',
        'field_name',
        'service_name',
        'acceptance_criteria',
        'calibration_status',
        'service_notes',
        'source_row_no',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
