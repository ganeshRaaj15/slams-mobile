<?php

namespace App\Models;

use CodeIgniter\Model;

class ExternalRequestModel extends Model
{
    public const STATUSES = [
        'submitted',
        'under_review',
        'needs_information',
        'approved_for_scheduling',
        'rejected',
        'completed',
    ];

    protected $table = 'external_requests';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'user_id',
        'lab_id',
        'organization_name',
        'contact_name',
        'contact_email',
        'contact_phone',
        'participant_count',
        'preferred_date',
        'preferred_start_time',
        'preferred_end_time',
        'purpose',
        'equipment_notes',
        'status',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'created_at',
        'updated_at',
    ];

    public function statusLabels(): array
    {
        return [
            'submitted' => 'Submitted',
            'under_review' => 'Under Review',
            'needs_information' => 'Needs Information',
            'approved_for_scheduling' => 'Approved For Scheduling',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
        ];
    }

    public function statusLabel(string $status): string
    {
        return $this->statusLabels()[$status] ?? ucwords(str_replace('_', ' ', $status));
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'submitted' => 'warning text-dark',
            'under_review' => 'info text-dark',
            'needs_information' => 'secondary',
            'approved_for_scheduling' => 'success',
            'rejected' => 'danger',
            'completed' => 'primary',
            default => 'secondary',
        };
    }

    public function userEditableStatuses(): array
    {
        return ['submitted', 'needs_information'];
    }

    public function canUserEdit(array $request): bool
    {
        return in_array((string) ($request['status'] ?? ''), $this->userEditableStatuses(), true);
    }
}
