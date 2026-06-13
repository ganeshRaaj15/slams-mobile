<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceAssetRequirementModel extends Model
{
    protected $table = 'service_asset_requirements';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'lab_service_id',
        'asset_id',
        'quantity_required',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
