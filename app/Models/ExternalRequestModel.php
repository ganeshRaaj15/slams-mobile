<?php

namespace App\Models;

use CodeIgniter\Model;

class ExternalRequestModel extends Model
{
    public const STATUSES = [
        'pending_pic_approval',
        'pending_manager_approval',
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
        'service_id',
        'selected_assets',
        'purpose',
        'equipment_notes',
        'status',
        'current_approval_stage',
        'information_requested_by',
        'pic_approved',
        'pic_notes',
        'pic_reviewed_by',
        'pic_reviewed_at',
        'manager_approved',
        'manager_notes',
        'manager_reviewed_by',
        'manager_reviewed_at',
        'booking_id',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'created_at',
        'updated_at',
    ];

    public function statusLabels(): array
    {
        return [
            'pending_pic_approval' => 'Pending PIC Approval',
            'pending_manager_approval' => 'Pending Lab Manager Approval',
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
            'pending_pic_approval' => 'warning text-dark',
            'pending_manager_approval' => 'info text-dark',
            'needs_information' => 'secondary',
            'approved_for_scheduling' => 'success',
            'rejected' => 'danger',
            'completed' => 'primary',
            default => 'secondary',
        };
    }

    public function userEditableStatuses(): array
    {
        return ['pending_pic_approval', 'needs_information'];
    }

    public function canUserEdit(array $request): bool
    {
        return in_array((string) ($request['status'] ?? ''), $this->userEditableStatuses(), true);
    }

    public function stageLabels(): array
    {
        return [
            'pic' => 'PIC Review',
            'manager' => 'Lab Manager Review',
            'completed' => 'Completed',
        ];
    }

    public function stageLabel(string $stage): string
    {
        return $this->stageLabels()[$stage] ?? ucwords(str_replace('_', ' ', $stage));
    }

    public function currentApprovalStage(array $request): string
    {
        $stage = trim((string) ($request['current_approval_stage'] ?? ''));
        if ($stage !== '') {
            return $stage;
        }

        if ((string) ($request['status'] ?? '') === 'pending_manager_approval' || (int) ($request['pic_approved'] ?? 0) === 1) {
            return 'manager';
        }

        if (in_array((string) ($request['status'] ?? ''), ['approved_for_scheduling', 'rejected', 'completed'], true)) {
            return 'completed';
        }

        return 'pic';
    }

    public function latestRequesterNote(array $request): string
    {
        $status = (string) ($request['status'] ?? '');
        $requestedBy = (string) ($request['information_requested_by'] ?? '');

        if ($status === 'needs_information') {
            return $requestedBy === 'manager'
                ? trim((string) ($request['manager_notes'] ?? ''))
                : trim((string) ($request['pic_notes'] ?? ''));
        }

        if (! empty($request['manager_notes'])) {
            return trim((string) $request['manager_notes']);
        }

        if (! empty($request['pic_notes'])) {
            return trim((string) $request['pic_notes']);
        }

        return trim((string) ($request['review_notes'] ?? ''));
    }
}
