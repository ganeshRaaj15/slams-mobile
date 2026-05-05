<?php

namespace App\Models;

use CodeIgniter\Model;

class MaintenanceLogModel extends Model
{
    protected $table = 'maintenance_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = false;

    protected $allowedFields = [
        'maintenance_id',
        'changed_by',
        'from_status',
        'to_status',
        'notes',
        'created_at',
    ];
}
