<?php

namespace App\Models;

use CodeIgniter\Model;

class LaboratoryModel extends Model
{
    protected $table = 'laboratories';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'name',
        'room',
        'description',
        'capacity',
        'availability_note',
        'safety_note',
        'pic_name',
        'pic_email',
        'pic_phone',
        'image',
        'pic_image',
    ];
}