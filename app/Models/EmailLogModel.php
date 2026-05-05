<?php

namespace App\Models;

use CodeIgniter\Model;

class EmailLogModel extends Model
{
    protected $table = 'email_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'user_id',
        'to_email',
        'subject',
        'body',
        'notification_type',
        'entity_type',
        'entity_id',
        'has_attachment',
        'attachment_name',
        'created_at',
        'updated_at',
    ];
}
