<?php

namespace App\Controllers\Technician;

use App\Controllers\BaseController;
use App\Libraries\NotificationService;
use App\Libraries\MaintenanceForecastService;
use App\Libraries\MaintenancePredictionService;
use App\Models\AssetModel;
use App\Models\MaintenanceLogModel;
use App\Models\MaintenanceRecordModel;

class MaintenanceController extends BaseController
{
    protected MaintenanceRecordModel $maintenanceModel;
    protected MaintenanceLogModel $logModel;
    protected AssetModel $assetModel;

    public function __construct()
    {
        helper(['auth', 'filesystem']);
        $this->maintenanceModel = new MaintenanceRecordModel();
        $this->logModel = new MaintenanceLogModel();
        $this->assetModel = new AssetModel();

        $directory = FCPATH . 'images/maintenance';
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    public function index()
    {
        if ($redirect = $this->ensureTechnician()) {
            return $redirect;
        }

        $user = auth()->user();
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
        $labels = $this->maintenanceModel->workflowLabels();

        $forecastService = new MaintenanceForecastService();
        $upcomingForecasts = $forecastService->getUpcomingForecasts(90);
        if ($assetId > 0) {
            $upcomingForecasts = array_values(array_filter($upcomingForecasts, fn(array $row): bool => (int) ($row['asset_id'] ?? 0) === $assetId));
        }
        $predictionService = new MaintenancePredictionService();


        return view('technician/maintenance/index', [
            'title' => 'Maintenance Records | FKMP Smart Lab',
            'page' => 'Maintenance Records',
            'roleLabel' => 'Technician',
            'user' => $user,
            'records' => $records,
            'upcomingForecasts' => $upcomingForecasts,
            'modelSummary' => $predictionService->getModelSummary(),
            'assets' => $this->assetOptions(),
            'filters' => ['status' => $status, 'asset_id' => $assetId, 'scope' => $scope],
            'statusOptions' => array_keys($labels),
            'statusLabels' => $labels,
        ]);
    }

    public function create(?int $assetId = null)
    {
        if ($redirect = $this->ensureTechnician()) {
            return $redirect;
        }

        $assetId = $assetId ?? (int) $this->request->getGet('asset_id');

        $record = [
            'asset_id' => $assetId,
            'quantity_affected' => 1,
            'unit_reference' => '',
            'title' => '',
            'issue_type' => 'preventive',
            'priority' => 'medium',
            'status' => 'reported',
            'description' => '',
            'scheduled_for' => '',
            'diagnosis_notes' => '',
            'work_notes' => '',
            'test_notes' => '',
            'resolution_notes' => '',
            'report_photo_path' => null,
            'completion_photo_path' => null,
        ];

        $record = array_merge($record, $this->prefillMaintenance($assetId));
        $predictionService = new MaintenancePredictionService();

        return view('technician/maintenance/form', [
            'title' => 'Plan Maintenance | FKMP Smart Lab',
            'page' => 'Plan Maintenance',
            'roleLabel' => 'Technician',
            'user' => auth()->user(),
            'mode' => 'create',
            'record' => $record,
            'assets' => $this->assetOptions(),
            'logs' => [],
            'issueTypes' => ['preventive', 'inspection', 'calibration', 'other'],
            'priorities' => ['low', 'medium', 'high', 'critical'],
            'statusLabels' => $this->maintenanceModel->workflowLabels(),
            'stageMode' => 'pre',
            'isLocked' => false,
            'assetPrediction' => $assetId ? $predictionService->predictAsset($assetId) : null,
            'modelSummary' => $predictionService->getModelSummary(),
        ]);
    }

    public function store()
    {
        if ($redirect = $this->ensureTechnician()) {
            return $redirect;
        }

        $user = auth()->user();
        $input = $this->collectPayload();
        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        if ($input['issue_type'] === 'corrective') {
            return redirect()->back()->withInput()->with('error', 'Corrective maintenance must start from a student or PIC issue report. Use this screen only for planned maintenance work.');
        }

        $asset = $this->assetModel->find($input['asset_id']);
        if (! $asset) {
            return redirect()->back()->withInput()->with('error', 'Selected asset was not found.');
        }

        if ($message = $this->validatePreMaintenanceInput($input, $asset, null)) {
            return redirect()->back()->withInput()->with('error', $message);
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
            return redirect()->back()->withInput()->with('error', 'Unable to create maintenance record at the moment.');
        }

        NotificationService::dispatchSafely(
            fn(NotificationService $notifications) => $notifications->notifyMaintenanceScheduled((int) $maintenanceId),
            'maintenance scheduled'
        );

        return redirect()->to('/technician/maintenance')->with('success', 'Maintenance case created and scheduled successfully.');
    }

    public function edit(int $id)
    {
        if ($redirect = $this->ensureTechnician()) {
            return $redirect;
        }

        $record = $this->maintenanceModel->withRelations()->where('maintenance_records.id', $id)->first();
        if (! $record) {
            return redirect()->to('/technician/maintenance')->with('error', 'Maintenance record not found.');
        }

        $logs = $this->logModel
            ->select('maintenance_logs.*, users.full_name, users.username')
            ->join('users', 'users.id = maintenance_logs.changed_by', 'left')
            ->where('maintenance_id', $id)
            ->orderBy('maintenance_logs.created_at', 'DESC')
            ->findAll();

        $isLocked = in_array($record['status'], ['completed', 'cancelled'], true);
        $stageMode = $isLocked ? 'locked' : $record['status'];
        $predictionService = new MaintenancePredictionService();

        return view('technician/maintenance/form', [
            'title' => 'Update Maintenance | FKMP Smart Lab',
            'page' => 'Update Maintenance',
            'roleLabel' => 'Technician',
            'user' => auth()->user(),
            'mode' => 'edit',
            'record' => $record,
            'assets' => $this->assetOptions(),
            'logs' => $logs,
            'issueTypes' => $record['issue_type'] === 'corrective' ? ['corrective'] : ['preventive', 'inspection', 'calibration', 'other'],
            'priorities' => ['low', 'medium', 'high', 'critical'],
            'statusLabels' => $this->maintenanceModel->workflowLabels(),
            'stageMode' => $stageMode,
            'isLocked' => $isLocked,
            'assetPrediction' => $predictionService->predictAsset((int) ($record['asset_id'] ?? 0)),
            'modelSummary' => $predictionService->getModelSummary(),
        ]);
    }

    public function update(int $id)
    {
        if ($redirect = $this->ensureTechnician()) {
            return $redirect;
        }

        $user = auth()->user();
        $record = $this->maintenanceModel->find($id);
        if (! $record) {
            return redirect()->to('/technician/maintenance')->with('error', 'Maintenance record not found.');
        }
        if (in_array($record['status'], ['completed', 'cancelled'], true)) {
            return redirect()->to('/technician/maintenance/edit/' . $id)->with('error', 'This record is closed and can no longer be changed.');
        }

        $input = $this->collectPayload();
        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $asset = $this->assetModel->find($input['asset_id']);
        if (! $asset) {
            return redirect()->back()->withInput()->with('error', 'Selected asset was not found.');
        }

        $targetStatus = $this->targetStatus((string) $record['status']);
        if (! in_array($targetStatus, $this->maintenanceModel->nextStatuses((string) $record['status']), true)) {
            return redirect()->back()->withInput()->with('error', 'Invalid maintenance workflow transition.');
        }

        $message = $this->validateTransitionInput($targetStatus, $input, $asset, $record);
        if ($message) {
            return redirect()->back()->withInput()->with('error', $message);
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
            return redirect()->back()->withInput()->with('error', 'Unable to update maintenance record at the moment.');
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

        return redirect()->to('/technician/maintenance/edit/' . $id)
            ->with('success', 'Maintenance case moved to ' . $this->maintenanceModel->statusLabel($targetStatus) . '.');
    }

    protected function ensureTechnician()
    {
        helper('auth');
        if (! auth()->loggedIn()) {
            return redirect()->to('/login');
        }
        if (! auth()->user()->inGroup('technician')) {
            return redirect()->to('/dashboard')->with('error', 'Access denied.');
        }
        return null;
    }

    protected function rules(): array
    {
        return [
            'asset_id' => 'required|integer',
            'quantity_affected' => 'required|integer|greater_than[0]',
            'unit_reference' => 'permit_empty|max_length[120]',
            'title' => 'required|min_length[3]|max_length[255]',
            'issue_type' => 'required|in_list[preventive,corrective,inspection,calibration,other]',
            'priority' => 'required|in_list[low,medium,high,critical]',
            'description' => 'required|min_length[10]',
            'scheduled_for' => 'permit_empty|valid_date[Y-m-d\TH:i]',
            'diagnosis_notes' => 'permit_empty|string',
            'work_notes' => 'permit_empty|string',
            'test_notes' => 'permit_empty|string',
            'resolution_notes' => 'permit_empty|string',
            'completion_photo' => 'permit_empty|max_size[completion_photo,4096]|is_image[completion_photo]|mime_in[completion_photo,image/jpg,image/jpeg,image/png,image/webp]',
        ];
    }

    protected function collectPayload(): array
    {
        return [
            'asset_id' => (int) $this->request->getPost('asset_id'),
            'quantity_affected' => max((int) $this->request->getPost('quantity_affected'), 0),
            'unit_reference' => trim((string) $this->request->getPost('unit_reference')),
            'title' => trim((string) $this->request->getPost('title')),
            'issue_type' => trim((string) $this->request->getPost('issue_type')),
            'priority' => trim((string) $this->request->getPost('priority')),
            'description' => trim((string) $this->request->getPost('description')),
            'scheduled_for' => trim((string) $this->request->getPost('scheduled_for')),
            'diagnosis_notes' => trim((string) $this->request->getPost('diagnosis_notes')),
            'work_notes' => trim((string) $this->request->getPost('work_notes')),
            'test_notes' => trim((string) $this->request->getPost('test_notes')),
            'resolution_notes' => trim((string) $this->request->getPost('resolution_notes')),
        ];
    }

    protected function targetStatus(string $currentStatus): string
    {
        $requested = trim((string) $this->request->getPost('transition'));
        if ($requested !== '') {
            return $requested;
        }

        return match ($currentStatus) {
            'reported' => 'scheduled',
            'scheduled' => 'in_progress',
            'in_progress' => 'testing',
            'testing' => 'completed',
            default => '',
        };
    }

    protected function validateTransitionInput(string $targetStatus, array $input, array $asset, array $record): ?string
    {
        return match ($targetStatus) {
            'scheduled' => $this->validatePreMaintenanceInput($input, $asset, $record),
            'testing' => $input['work_notes'] === '' ? 'Please enter repair work notes before moving the case to testing.' : null,
            'completed' => $this->validatePostMaintenanceInput($input, $asset, $record),
            default => null,
        };
    }

    protected function transitionPayload(string $targetStatus, array $input, array $record, ?string $completionPhotoPath): array
    {
        $now = date('Y-m-d H:i:s');

        return match ($targetStatus) {
            'scheduled' => [
                'status' => 'scheduled',
                'scheduled_for' => $this->toSqlDateTime($input['scheduled_for']),
                'accepted_at' => $record['accepted_at'] ?: $now,
                'diagnosis_notes' => $input['diagnosis_notes'],
            ],
            'in_progress' => [
                'status' => 'in_progress',
                'scheduled_for' => $this->toSqlDateTime($input['scheduled_for'] ?: (string) ($record['scheduled_for'] ?? '')),
                'diagnosis_notes' => $input['diagnosis_notes'] !== '' ? $input['diagnosis_notes'] : ($record['diagnosis_notes'] ?? null),
                'started_at' => $record['started_at'] ?: $now,
            ],
            'testing' => [
                'status' => 'testing',
                'scheduled_for' => $this->toSqlDateTime($input['scheduled_for'] ?: (string) ($record['scheduled_for'] ?? '')),
                'diagnosis_notes' => $input['diagnosis_notes'] !== '' ? $input['diagnosis_notes'] : ($record['diagnosis_notes'] ?? null),
                'work_notes' => $input['work_notes'],
                'started_at' => $record['started_at'] ?: $now,
                'tested_at' => $now,
            ],
            'completed' => [
                'status' => 'completed',
                'scheduled_for' => $this->toSqlDateTime($input['scheduled_for'] ?: (string) ($record['scheduled_for'] ?? '')),
                'diagnosis_notes' => $input['diagnosis_notes'] !== '' ? $input['diagnosis_notes'] : ($record['diagnosis_notes'] ?? null),
                'work_notes' => $input['work_notes'],
                'test_notes' => $input['test_notes'],
                'resolution_notes' => $input['resolution_notes'],
                'completion_photo_path' => $completionPhotoPath,
                'started_at' => $record['started_at'] ?: $now,
                'tested_at' => $record['tested_at'] ?: $now,
                'completed_at' => $now,
                'asset_status_after' => 'available',
            ],
            'cancelled' => [
                'status' => 'cancelled',
                'resolution_notes' => $input['resolution_notes'] ?: ($record['resolution_notes'] ?? 'Maintenance case cancelled.'),
            ],
            default => [],
        };
    }

    protected function transitionLogMessage(string $fromStatus, string $targetStatus): string
    {
        return match ($targetStatus) {
            'scheduled' => 'Technician accepted the case, added diagnosis, and scheduled the maintenance work.',
            'in_progress' => $fromStatus === 'testing'
                ? 'Technician returned the case from testing to repair work.'
                : 'Technician started the maintenance work.',
            'testing' => 'Technician completed repair notes and moved the case to testing.',
            'completed' => 'Technician completed testing, resolution notes, and final evidence.',
            'cancelled' => 'Technician cancelled the maintenance case.',
            default => 'Maintenance case updated.',
        };
    }

    protected function toSqlDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return str_replace('T', ' ', strlen($value) === 16 ? $value . ':00' : $value);
    }

    protected function prefillMaintenance(?int $assetId = null): array
    {
        $assetId = $assetId ?: 0;
        $asset = $assetId > 0 ? $this->assetModel->find($assetId) : null;

        $issueType = trim((string) $this->request->getGet('issue_type'));
        $issueTypes = ['preventive', 'inspection', 'calibration', 'other'];
        if (! in_array($issueType, $issueTypes, true)) {
            $issueType = 'preventive';
        }

        $priority = trim((string) $this->request->getGet('priority'));
        $priorities = ['low', 'medium', 'high', 'critical'];
        if (! in_array($priority, $priorities, true)) {
            $priority = 'medium';
        }

        $quantity = (int) $this->request->getGet('quantity_affected');
        if ($quantity <= 0) {
            $quantity = 1;
        }

        $title = trim((string) $this->request->getGet('title'));
        if ($title === '' && $asset) {
            $title = 'Preventive Maintenance - ' . $asset['name'];
        }

        $scheduledFor = $this->normalizeScheduledFor((string) $this->request->getGet('scheduled_for'));

        $prefill = [
            'quantity_affected' => $quantity,
            'title' => $title,
            'issue_type' => $issueType,
            'priority' => $priority,
        ];

        if ($assetId > 0) {
            $prefill['asset_id'] = $assetId;
        }

        if ($scheduledFor !== '') {
            $prefill['scheduled_for'] = $scheduledFor;
        }

        return $prefill;
    }

    protected function normalizeScheduledFor(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $raw) === 1) {
            return $raw . 'T09:00';
        }

        if (preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}$/', $raw) === 1) {
            return str_replace(' ', 'T', $raw);
        }

        if (preg_match('/^\\d{4}-\\d{2}-\\d{2}[ T]\\d{2}:\\d{2}:\\d{2}$/', $raw) === 1) {
            $raw = str_replace(' ', 'T', $raw);
            return substr($raw, 0, 16);
        }

        return $raw;
    }

    protected function assetOptions(): array
    {
        return $this->assetModel
            ->select('assets.id, assets.name, assets.asset_code, assets.status, assets.quantity, assets.total_quantity, laboratories.name AS lab_name')
            ->join('laboratories', 'laboratories.id = assets.lab_id', 'left')
            ->orderBy('laboratories.name', 'ASC')
            ->orderBy('assets.name', 'ASC')
            ->findAll();
    }

    protected function editableQuantityCapacity(int $assetId, ?array $record = null): int
    {
        $asset = $this->assetModel->find($assetId);
        if (! $asset) {
            return 0;
        }

        $ignoreRecordId = 0;
        if ($record !== null && (int) $record['asset_id'] === $assetId) {
            $ignoreRecordId = (int) ($record['id'] ?? 0);
        }

        $total = $this->assetModel->totalQuantity($asset);
        $openOtherUnits = min($this->assetModel->openMaintenanceUnits($assetId, $ignoreRecordId), $total);

        return max($total - $openOtherUnits, 0);
    }

    protected function validatePreMaintenanceInput(array $input, array $asset, ?array $record): ?string
    {
        $availableCapacity = $this->editableQuantityCapacity($input['asset_id'], $record);
        if ($input['quantity_affected'] > $availableCapacity) {
            return 'Affected quantity cannot exceed the units currently available for maintenance on this asset.';
        }

        $totalUnits = $this->assetModel->totalQuantity($asset);
        if ($totalUnits > 1 && $input['unit_reference'] === '') {
            return 'Please identify the exact workstation, unit label, or physical equipment reference for multi-unit assets.';
        }
        if ($input['scheduled_for'] === '') {
            return 'Please set the maintenance schedule before saving the pre-maintenance stage.';
        }
        if ($input['diagnosis_notes'] === '') {
            return 'Please record the diagnosis before completing the pre-maintenance stage.';
        }

        return null;
    }

    protected function validatePostMaintenanceInput(array $input, array $asset, array $record): ?string
    {
        $totalUnits = $this->assetModel->totalQuantity($asset);
        if ($totalUnits > 1 && trim((string) ($record['unit_reference'] ?? $input['unit_reference'])) === '') {
            return 'A unit reference is required for multi-unit assets before maintenance can be completed.';
        }
        if ($input['work_notes'] === '') {
            return 'Please enter the repair work notes before completing the post-maintenance stage.';
        }
        if ($input['test_notes'] === '') {
            return 'Please enter the testing or verification notes before completing the post-maintenance stage.';
        }
        if ($input['resolution_notes'] === '') {
            return 'Please enter the completion summary before completing the post-maintenance stage.';
        }

        $existingPhoto = $record['completion_photo_path'] ?? null;
        $newPhoto = $this->request->getFile('completion_photo');
        $hasNewPhoto = $newPhoto && $newPhoto->isValid() && ! $newPhoto->hasMoved();
        if (! $hasNewPhoto && empty($existingPhoto)) {
            return 'Please attach a completion photo before completing the post-maintenance stage.';
        }

        return null;
    }

    protected function handlePhotoUpload(string $fieldName, ?string $currentPath = null): ?string
    {
        $file = $this->request->getFile($fieldName);
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return $currentPath;
        }

        if ($currentPath && is_file(FCPATH . $currentPath)) {
            unlink(FCPATH . $currentPath);
        }

        $newName = $file->getRandomName();
        if ($file->move(FCPATH . 'images/maintenance', $newName)) {
            return 'images/maintenance/' . $newName;
        }

        return $currentPath;
    }
}

