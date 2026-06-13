<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceEquipmentModel extends Model
{
    protected $table = 'service_equipment_models';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'lab_service_id',
        'equipment_model',
        'criteria_note',
        'calibration_status',
        'source_row_no',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
