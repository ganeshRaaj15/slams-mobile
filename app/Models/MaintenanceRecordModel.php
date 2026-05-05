<?php

namespace App\Models;

use CodeIgniter\Model;

class MaintenanceRecordModel extends Model
{
    protected $table = 'maintenance_records';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'asset_id',
        'quantity_affected',
        'unit_reference',
        'reported_by',
        'assigned_technician_id',
        'title',
        'issue_type',
        'priority',
        'description',
        'report_photo_path',
        'status',
        'asset_status_before',
        'asset_status_after',
        'scheduled_for',
        'accepted_at',
        'diagnosis_notes',
        'started_at',
        'work_notes',
        'tested_at',
        'test_notes',
        'completed_at',
        'resolution_notes',
        'completion_photo_path',
        'created_at',
        'updated_at',
    ];

    public function withRelations()
    {
        return $this->select('maintenance_records.*, assets.name AS asset_name, assets.asset_code, assets.status AS current_asset_status, laboratories.name AS laboratory_name, laboratories.room AS laboratory_room, reporter.username AS reported_by_username, reporter.full_name AS reported_by_name, technician.username AS technician_username, technician.full_name AS technician_name')
            ->join('assets', 'assets.id = maintenance_records.asset_id', 'left')
            ->join('laboratories', 'laboratories.id = assets.lab_id', 'left')
            ->join('users reporter', 'reporter.id = maintenance_records.reported_by', 'left')
            ->join('users technician', 'technician.id = maintenance_records.assigned_technician_id', 'left');
    }

    public function openStatuses(): array
    {
        return ['reported', 'scheduled', 'in_progress', 'testing'];
    }

    public function workflowLabels(): array
    {
        return [
            'reported' => 'Reported',
            'scheduled' => 'Scheduled',
            'in_progress' => 'Repair In Progress',
            'testing' => 'Testing And Verification',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }

    public function nextStatuses(?string $currentStatus): array
    {
        return match ($currentStatus) {
            'reported' => ['scheduled', 'cancelled'],
            'scheduled' => ['in_progress', 'cancelled'],
            'in_progress' => ['testing', 'cancelled'],
            'testing' => ['completed', 'in_progress'],
            default => [],
        };
    }

    public function statusLabel(string $status): string
    {
        return $this->workflowLabels()[$status] ?? ucwords(str_replace('_', ' ', $status));
    }
}
