<?php

namespace App\Models;

use CodeIgniter\Model;

class FacultyModel extends Model
{
    protected $table            = 'faculties';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;

    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'code',
        'name_bm',
        'name_en',
        'is_fkmp',
    ];

    public function getAllForDropdown(): array
    {
        return $this->orderBy('name_bm', 'ASC')->findAll();
    }
}
