<?php

namespace App\Controllers\Api;

use App\Controllers\Technician\MaintenanceController as WebMaintenanceController;
use App\Libraries\NotificationService;
use App\Libraries\MaintenancePredictionService;
use CodeIgniter\Shield\Entities\User;

class NativeMaintenanceController extends WebMaintenanceController
{
    public function index()
    {
        $user = $this->technicianUser();
        if (! $user instanceof User) {
            return $this->unauthenticatedOrForbidden();
        }

        $status = trim((string) $this->request->getGet('status'));
        $assetId = (int) $this->request->getGet('asset_id');
        $scope = trim((string) $this->request->getGet('scope'));

        $recordsQuery = $this->maintenanceModel->withRelations();
        if ($status !== '') {
            $recordsQuery->where('maintenance_records.status', $status);
        }
        if ($assetId > 0) {
            $recordsQuery->where('maintenance_records.asset_id', $assetId);
        }
        if ($scope === 'mine') {
            $recordsQuery->where('maintenance_records.assigned_technician_id', $user->id);
        }

        $records = $recordsQuery->orderBy('maintenance_records.created_at', 'DESC')->findAll();
        $statusLabels = $this->maintenanceModel->workflowLabels();
        $openStatuses = $this->maintenanceModel->openStatuses();

        return $this->response->setJSON([
            'status' => 'success',
            'stats' => [
                'assigned' => (int) (new \App\Models\MaintenanceRecordModel())
                    ->where('assigned_technician_id', $user->id)
                    ->whereIn('status', $openStatuses)
                    ->countAllResults(),
                'open_total' => (int) (new \App\Models\MaintenanceRecordModel())
                    ->whereIn('status', $openStatuses)
                    ->countAllResults(),
                'testing' => (int) (new \App\Models\MaintenanceRecordModel())
                    ->where('status', 'testing')
                    ->countAllResults(),
            ],
            'status_labels' => $statusLabels,
            'issue_types' => ['preventive', 'inspection', 'calibration', 'other'],
            'priorities' => ['low', 'medium', 'high', 'critical'],
            'assets' => array_map(fn(array $asset): array => $this->serializeAssetOption($asset), $this->assetOptions()),
            'records' => array_map(fn(array $record): array => $this->serializeRecord($record), $records),
        ]);
    }

    public function show(int $id)
    {
        $user = $this->technicianUser();
        if (! $user instanceof User) {
            return $this->unauthenticatedOrForbidden();
        }

        $record = $this->maintenanceModel->withRelations()->where('maintenance_records.id', $id)->first();
        if (! $record) {
            return $this->notFound('Maintenance record not found.');
        }

        $logs = $this->logModel
            ->select('maintenance_logs.*, users.full_name, users.username')
            ->join('users', 'users.id = maintenance_logs.changed_by', 'left')
            ->where('maintenance_id', $id)
            ->orderBy('maintenance_logs.created_at', 'DESC')
            ->findAll();

        $isLocked = in_array((string) ($record['status'] ?? ''), ['completed', 'cancelled'], true);
        $predictionService = new MaintenancePredictionService();

        return $this->response->setJSON([
            'status' => 'success',
            'record' => array_merge($this->serializeRecord($record), [
                'description' => (string) ($record['description'] ?? ''),
                'asset_status_before' => (string) ($record['asset_status_before'] ?? ''),
                'asset_status_after' => (string) ($record['asset_status_after'] ?? ''),
                'diagnosis_notes' => (string) ($record['diagnosis_notes'] ?? ''),
                'work_notes' => (string) ($record['work_notes'] ?? ''),
                'test_notes' => (string) ($record['test_notes'] ?? ''),
                'resolution_notes' => (string) ($record['resolution_notes'] ?? ''),
                'reported_by_name' => (string) ($record['reported_by_name'] ?? $record['reported_by_username'] ?? ''),
                'technician_name' => (string) ($record['technician_name'] ?? $record['technician_username'] ?? ''),
                'report_photo_url' => $this->mediaUrl((string) ($record['report_photo_path'] ?? '')),
                'completion_photo_url' => $this->mediaUrl((string) ($record['completion_photo_path'] ?? '')),
                'logs' => array_map(static function (array $log): array {
                    return [
                        'id' => (int) $log['id'],
                        'from_status' => (string) ($log['from_status'] ?? ''),
                        'to_status' => (string) ($log['to_status'] ?? ''),
                        'notes' => (string) ($log['notes'] ?? ''),
                        'changed_by' => (string) ($log['full_name'] ?? $log['username'] ?? 'System'),
                        'created_at' => (string) ($log['created_at'] ?? ''),
                    ];
                }, $logs),
                'next_statuses' => $this->maintenanceModel->nextStatuses((string) ($record['status'] ?? '')),
                'is_locked' => $isLocked,
                'asset_prediction' => $predictionService->predictAsset((int) ($record['asset_id'] ?? 0)),
            ]),
            'status_labels' => $this->maintenanceModel->workflowLabels(),
            'issue_types' => (string) ($record['issue_type'] ?? '') === 'corrective'
                ? ['corrective']
                : ['preventive', 'inspection', 'calibration', 'other'],
            'priorities' => ['low', 'medium', 'high', 'critical'],
            'assets' => array_map(fn(array $asset): array => $this->serializeAssetOption($asset), $this->assetOptions()),
        ]);
    }

    public function store()
    {
        $user = $this->technicianUser();
        if (! $user instanceof User) {
            return $this->unauthenticatedOrForbidden();
        }

        $input = $this->collectPayload();
        if (! $this->validate($this->rules())) {
            return $this->validationError();
        }

        if ($input['issue_type'] === 'corrective') {
            return $this->unprocessable('Corrective maintenance must start from a student or PIC issue report. Use this screen only for planned maintenance work.');
        }

        $asset = $this->assetModel->find($input['asset_id']);
        if (! $asset) {
            return $this->unprocessable('Selected asset was not found.');
        }

        if ($message = $this->validatePreMaintenanceInput($input, $asset, null)) {
            return $this->unprocessable($message);
        }

        $data = [
            'asset_id' => $input['asset_id'],
            'quantity_affected' => $input['quantity_affected'],
            'unit_reference' => $input['unit_reference'] !== '' ? $input['unit_reference'] : null,
            'reported_by' => $user->id,
            'assigned_technician_id' => $user->id,
            'title' => $input['title'],
            'issue_type' => $input['issue_type'],
            'priority' => $input['priority'],
            'description' => $input['description'],
            'report_photo_path' => null,
            'status' => 'scheduled',
            'asset_status_before' => $asset['status'],
            'asset_status_after' => null,
            'scheduled_for' => $this->toSqlDateTime($input['scheduled_for']),
            'accepted_at' => date('Y-m-d H:i:s'),
            'diagnosis_notes' => $input['diagnosis_notes'],
            'started_at' => null,
            'work_notes' => null,
            'tested_at' => null,
            'test_notes' => null,
            'completed_at' => null,
            'resolution_notes' => null,
            'completion_photo_path' => null,
        ];

        $db = \Config\Database::connect();
        $db->transStart();
        $this->maintenanceModel->insert($data);
        $maintenanceId = $this->maintenanceModel->getInsertID();
        $this->logModel->insert([
            'maintenance_id' => $maintenanceId,
            'changed_by' => $user->id,
            'from_status' => null,
            'to_status' => 'scheduled',
            'notes' => 'Preventive maintenance accepted and scheduled. Diagnosis recorded.',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->assetModel->syncManagedAvailability((int) $input['asset_id']);
        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->unprocessable('Unable to create maintenance record at the moment.');
        }

        NotificationService::dispatchSafely(
            fn(NotificationService $notifications) => $notifications->notifyMaintenanceScheduled((int) $maintenanceId),
            'maintenance scheduled'
        );

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Maintenance case created and scheduled successfully.',
            'maintenance_id' => (int) $maintenanceId,
        ]);
    }

    public function update(int $id)
    {
        $user = $this->technicianUser();
        if (! $user instanceof User) {
            return $this->unauthenticatedOrForbidden();
        }

        $record = $this->maintenanceModel->find($id);
        if (! $record) {
            return $this->notFound('Maintenance record not found.');
        }
        if (in_array((string) ($record['status'] ?? ''), ['completed', 'cancelled'], true)) {
            return $this->unprocessable('This record is closed and can no longer be changed.');
        }

        $input = $this->collectPayload();
        if (! $this->validate($this->rules())) {
            return $this->validationError();
        }

        $asset = $this->assetModel->find($input['asset_id']);
        if (! $asset) {
            return $this->unprocessable('Selected asset was not found.');
        }

        $targetStatus = $this->targetStatus((string) $record['status']);
        if (! in_array($targetStatus, $this->maintenanceModel->nextStatuses((string) $record['status']), true)) {
            return $this->unprocessable('Invalid maintenance workflow transition.');
        }

        $message = $this->validateTransitionInput($targetStatus, $input, $asset, $record);
        if ($message) {
            return $this->unprocessable($message);
        }

        $completionPhotoPath = $record['completion_photo_path'] ?? null;
        if ($targetStatus === 'completed') {
            $completionPhotoPath = $this->handlePhotoUpload('completion_photo', $completionPhotoPath);
        }

        $updateData = [
            'asset_id' => $input['asset_id'],
            'quantity_affected' => $input['quantity_affected'],
            'unit_reference' => $input['unit_reference'] !== '' ? $input['unit_reference'] : null,
            'assigned_technician_id' => $user->id,
            'title' => $input['title'],
            'issue_type' => $record['issue_type'] === 'corrective' ? 'corrective' : $input['issue_type'],
            'priority' => $input['priority'],
            'description' => $input['description'],
        ];

        $updateData = array_merge($updateData, $this->transitionPayload($targetStatus, $input, $record, $completionPhotoPath));

        $oldAssetId = (int) $record['asset_id'];
        $newAssetId = (int) $input['asset_id'];

        $db = \Config\Database::connect();
        $db->transStart();
        $this->maintenanceModel->update($id, $updateData);
        $this->logModel->insert([
            'maintenance_id' => $id,
            'changed_by' => $user->id,
            'from_status' => $record['status'],
            'to_status' => $targetStatus,
            'notes' => $this->transitionLogMessage((string) $record['status'], $targetStatus),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if ($oldAssetId !== $newAssetId) {
            $this->assetModel->syncManagedAvailability($oldAssetId);
        }
        $this->assetModel->syncManagedAvailability($newAssetId);
        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->unprocessable('Unable to update maintenance record at the moment.');
        }

        if ($targetStatus === 'scheduled') {
            NotificationService::dispatchSafely(
                fn(NotificationService $notifications) => $notifications->notifyMaintenanceScheduled($id),
                'maintenance rescheduled'
            );
        } elseif ($targetStatus === 'completed') {
            NotificationService::dispatchSafely(
                fn(NotificationService $notifications) => $notifications->notifyMaintenanceCompleted($id),
                'maintenance completed'
            );
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Maintenance case moved to ' . $this->maintenanceModel->statusLabel($targetStatus) . '.',
            'maintenance_id' => $id,
            'new_status' => $targetStatus,
        ]);
    }

    private function technicianUser(): ?User
    {
        helper('auth');
        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        return $user->inGroup('technician') ? $user : null;
    }

    private function serializeRecord(array $record): array
    {
        return [
            'id' => (int) $record['id'],
            'asset_id' => (int) ($record['asset_id'] ?? 0),
            'asset_name' => (string) ($record['asset_name'] ?? ''),
            'asset_code' => (string) ($record['asset_code'] ?? ''),
            'lab_name' => (string) ($record['laboratory_name'] ?? ''),
            'lab_room' => (string) ($record['laboratory_room'] ?? ''),
            'title' => (string) ($record['title'] ?? ''),
            'issue_type' => (string) ($record['issue_type'] ?? ''),
            'priority' => (string) ($record['priority'] ?? ''),
            'status' => (string) ($record['status'] ?? ''),
            'status_label' => $this->maintenanceModel->statusLabel((string) ($record['status'] ?? '')),
            'quantity_affected' => (int) ($record['quantity_affected'] ?? 0),
            'unit_reference' => (string) ($record['unit_reference'] ?? ''),
            'scheduled_for' => (string) ($record['scheduled_for'] ?? ''),
            'accepted_at' => (string) ($record['accepted_at'] ?? ''),
            'started_at' => (string) ($record['started_at'] ?? ''),
            'tested_at' => (string) ($record['tested_at'] ?? ''),
            'completed_at' => (string) ($record['completed_at'] ?? ''),
            'created_at' => (string) ($record['created_at'] ?? ''),
            'updated_at' => (string) ($record['updated_at'] ?? ''),
            'is_locked' => in_array((string) ($record['status'] ?? ''), ['completed', 'cancelled'], true),
        ];
    }

    private function serializeAssetOption(array $asset): array
    {
        return [
            'id' => (int) $asset['id'],
            'name' => (string) ($asset['name'] ?? ''),
            'asset_code' => (string) ($asset['asset_code'] ?? ''),
            'status' => (string) ($asset['status'] ?? ''),
            'quantity' => (int) ($asset['quantity'] ?? 0),
            'total_quantity' => (int) ($asset['total_quantity'] ?? 0),
            'lab_name' => (string) ($asset['lab_name'] ?? ''),
            'requires_unit_reference' => (int) ($asset['total_quantity'] ?? 0) > 1,
        ];
    }

    private function mediaUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        $scheme = $this->request->getUri()->getScheme();
        $host = $this->request->getHeaderLine('Host');

        return rtrim($scheme . '://' . $host, '/') . '/' . ltrim($path, '/');
    }

    private function validationError()
    {
        return $this->response
            ->setStatusCode(422)
            ->setJSON([
                'status' => 'error',
                'message' => implode(' ', $this->validator->getErrors()),
                'errors' => $this->validator->getErrors(),
            ]);
    }

    private function unauthenticatedOrForbidden()
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Unauthenticated.',
                ]);
        }

        return $this->response
            ->setStatusCode(403)
            ->setJSON([
                'status' => 'error',
                'message' => 'Maintenance access is limited to technician users.',
            ]);
    }

    private function notFound(string $message)
    {
        return $this->response
            ->setStatusCode(404)
            ->setJSON([
                'status' => 'error',
                'message' => $message,
            ]);
    }

    private function unprocessable(string $message)
    {
        return $this->response
            ->setStatusCode(422)
            ->setJSON([
                'status' => 'error',
                'message' => $message,
            ]);
    }
}
