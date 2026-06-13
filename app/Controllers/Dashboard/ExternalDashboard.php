<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Libraries\BookingSlotService;
use App\Libraries\ExternalRequestNotificationService;
use App\Libraries\ServiceBundleService;
use App\Models\ExternalRequestModel;
use App\Models\LaboratoryModel;

class ExternalDashboard extends BaseController
{
    protected ExternalRequestModel $requestModel;
    protected LaboratoryModel $labModel;
    protected BookingSlotService $slotService;
    protected ServiceBundleService $bundleService;

    public function __construct()
    {
        helper('auth');
        $this->requestModel = new ExternalRequestModel();
        $this->labModel = new LaboratoryModel();
        $this->slotService = new BookingSlotService();
        $this->bundleService = new ServiceBundleService();
    }

    public function index()
    {
        if ($redirect = $this->ensureExternal()) {
            return $redirect;
        }

        $user = auth()->user();
        $filters = $this->requestFilters();

        $requestsQuery = $this->requestModel
            ->select('external_requests.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = external_requests.lab_id', 'left')
            ->where('external_requests.user_id', $user->id);

        $requests = $this->applyRequestFilters($requestsQuery, $filters)
            ->orderBy('external_requests.created_at', 'DESC')
            ->findAll();

        return view('dashboard/external/index', [
            'title' => 'External Requests | FKMP Smart Lab',
            'page' => 'External Requests',
            'user' => $user,
            'requests' => $requests,
            'stats' => $this->requestStats((int) $user->id),
            'filters' => $filters,
            'labs' => $this->labModel->orderBy('name', 'ASC')->findAll(),
            'statusLabels' => $this->requestModel->statusLabels(),
            'requestModel' => $this->requestModel,
        ]);
    }

    public function createRequest()
    {
        if ($redirect = $this->ensureExternal()) {
            return $redirect;
        }

        $user = auth()->user();
        $labId = (int) $this->request->getGet('lab_id');

        return view('dashboard/external/form', [
            'title' => 'Request Lab Access | FKMP Smart Lab',
            'page' => 'Request Lab Access',
            'user' => $user,
            'mode' => 'create',
            'requestRecord' => [
                'lab_id' => $labId > 0 ? $labId : null,
                'organization_name' => '',
                'contact_name' => (string) ($user->full_name ?: $user->username ?: ''),
                'contact_email' => trim((string) ($user->email ?? '')),
                'contact_phone' => trim((string) ($user->phone ?? '')),
                'participant_count' => 1,
                'preferred_date' => $this->validDate((string) $this->request->getGet('preferred_date')),
                'preferred_start_time' => $this->normalizeTimeForDisplay((string) $this->request->getGet('preferred_start_time')),
                'preferred_end_time' => $this->normalizeTimeForDisplay((string) $this->request->getGet('preferred_end_time')),
                'purpose' => '',
                'equipment_notes' => '',
                'service_id' => ($this->request->getGet('service_id') ?: null) ? (int) $this->request->getGet('service_id') : null,
                'selected_assets' => trim((string) $this->request->getGet('selected_assets')) ?: null,
                'status' => 'pending_pic_approval',
                'current_approval_stage' => 'pic',
                'information_requested_by' => null,
                'pic_notes' => '',
                'manager_notes' => '',
                'review_notes' => '',
            ],
            'labs' => $this->labModel->orderBy('name', 'ASC')->findAll(),
            'statusLabels' => $this->requestModel->statusLabels(),
            'requestModel' => $this->requestModel,
        ]);
    }

    public function storeRequest()
    {
        if ($redirect = $this->ensureExternal()) {
            return $redirect;
        }

        $payload = $this->requestPayload();
        if ($error = $this->validateRequestPayload($payload)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $payload['user_id'] = auth()->id();
        $payload['status'] = 'pending_pic_approval';
        $payload['current_approval_stage'] = 'pic';
        $payload['information_requested_by'] = null;
        $payload['pic_approved'] = 0;
        $payload['pic_notes'] = null;
        $payload['pic_reviewed_by'] = null;
        $payload['pic_reviewed_at'] = null;
        $payload['manager_approved'] = 0;
        $payload['manager_notes'] = null;
        $payload['manager_reviewed_by'] = null;
        $payload['manager_reviewed_at'] = null;
        $payload['review_notes'] = null;
        $payload['reviewed_by'] = null;
        $payload['reviewed_at'] = null;

        $requestId = (int) $this->requestModel->insert($payload, true);

        try {
            (new ExternalRequestNotificationService())->notifySubmitted($requestId);
        } catch (\Throwable $e) {
            log_message('error', 'External request notification failed on submit: ' . $e->getMessage());
        }

        return redirect()->to('/dashboard/external')->with('success', 'Your external access request has been submitted for review.');
    }

    public function editRequest(int $id)
    {
        if ($redirect = $this->ensureExternal()) {
            return $redirect;
        }

        $requestRecord = $this->ownedRequest($id);
        if (! $requestRecord) {
            return redirect()->to('/dashboard/external')->with('error', 'External request not found.');
        }
        if (! $this->requestModel->canUserEdit($requestRecord)) {
            return redirect()->to('/dashboard/external')->with('error', 'This request can no longer be edited.');
        }

        return view('dashboard/external/form', [
            'title' => 'Update External Request | FKMP Smart Lab',
            'page' => 'Update External Request',
            'user' => auth()->user(),
            'mode' => 'edit',
            'requestRecord' => array_merge($requestRecord, [
                'preferred_start_time' => $this->normalizeTimeForDisplay((string) ($requestRecord['preferred_start_time'] ?? '')),
                'preferred_end_time' => $this->normalizeTimeForDisplay((string) ($requestRecord['preferred_end_time'] ?? '')),
            ]),
            'labs' => $this->labModel->orderBy('name', 'ASC')->findAll(),
            'statusLabels' => $this->requestModel->statusLabels(),
            'requestModel' => $this->requestModel,
        ]);
    }

    public function updateRequest(int $id)
    {
        if ($redirect = $this->ensureExternal()) {
            return $redirect;
        }

        $requestRecord = $this->ownedRequest($id);
        if (! $requestRecord) {
            return redirect()->to('/dashboard/external')->with('error', 'External request not found.');
        }
        if (! $this->requestModel->canUserEdit($requestRecord)) {
            return redirect()->to('/dashboard/external')->with('error', 'This request can no longer be edited.');
        }

        $payload = $this->requestPayload();
        if ($error = $this->validateRequestPayload($payload)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $returnStage = (string) ($requestRecord['information_requested_by'] ?? '') === 'manager' && (int) ($requestRecord['pic_approved'] ?? 0) === 1
            ? 'manager'
            : 'pic';

        $payload['status'] = $returnStage === 'manager' ? 'pending_manager_approval' : 'pending_pic_approval';
        $payload['current_approval_stage'] = $returnStage;
        $payload['information_requested_by'] = null;
        $payload['review_notes'] = null;
        $payload['reviewed_by'] = null;
        $payload['reviewed_at'] = null;

        $this->requestModel->update($id, $payload);

        try {
            (new ExternalRequestNotificationService())->notifySubmitted($id, true);
        } catch (\Throwable $e) {
            log_message('error', 'External request notification failed on resubmit: ' . $e->getMessage());
        }

        return redirect()->to('/dashboard/external')->with('success', 'Your external request has been updated and resubmitted for review.');
    }

    public function daySlots(int $labId, string $date)
    {
        if ($redirect = $this->ensureExternal()) {
            return $redirect;
        }

        $serviceId = (int) $this->request->getGet('service_id');
        if ($serviceId > 0) {
            $selected = $this->bundleService->requirementMapForService($labId, $serviceId);
            $bookingController = new \App\Controllers\Public\BookingController();

            return $this->response->setJSON([
                'status' => 'success',
                'slots' => $bookingController->dayAssetsInternal($labId, $date, $selected),
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'slots' => $this->externalDaySlotsInternal($labId, $date),
        ]);
    }

    public function services(int $labId)
    {
        if ($redirect = $this->ensureExternal()) {
            return $redirect;
        }

        return $this->response->setJSON([
            'status' => 'success',
            'services' => $this->bundleService->serviceSummariesForLab($labId),
        ]);
    }

    protected function ensureExternal()
    {
        if (! auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        if (! auth()->user()->inGroup('external')) {
            return redirect()->to('/dashboard')->with('error', 'Access denied.');
        }

        return null;
    }

    protected function ownedRequest(int $id): ?array
    {
        return $this->requestModel
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();
    }

    protected function requestFilters(): array
    {
        $status = trim((string) $this->request->getGet('status'));
        if (! in_array($status, ExternalRequestModel::STATUSES, true)) {
            $status = '';
        }

        return [
            'q' => trim((string) $this->request->getGet('q')),
            'status' => $status,
            'lab_id' => max((int) $this->request->getGet('lab_id'), 0),
        ];
    }

    protected function applyRequestFilters($query, array $filters)
    {
        if ($filters['q'] !== '') {
            $query->groupStart()
                ->like('laboratories.name', $filters['q'])
                ->orLike('external_requests.organization_name', $filters['q'])
                ->orLike('external_requests.contact_name', $filters['q'])
                ->orLike('external_requests.purpose', $filters['q'])
                ->groupEnd();
        }

        if ($filters['status'] !== '') {
            $query->where('external_requests.status', $filters['status']);
        }

        if ($filters['lab_id'] > 0) {
            $query->where('external_requests.lab_id', $filters['lab_id']);
        }

        return $query;
    }

    protected function requestStats(int $userId): array
    {
        $stats = ['total' => 0];
        foreach (ExternalRequestModel::STATUSES as $status) {
            $stats[$status] = (int) $this->requestModel
                ->where('user_id', $userId)
                ->where('status', $status)
                ->countAllResults();
            $stats['total'] += $stats[$status];
        }

        return $stats;
    }

    protected function requestPayload(): array
    {
        $payload = [
            'lab_id' => (int) $this->request->getPost('lab_id'),
            'organization_name' => trim((string) $this->request->getPost('organization_name')),
            'contact_name' => trim((string) $this->request->getPost('contact_name')),
            'contact_email' => strtolower(trim((string) $this->request->getPost('contact_email'))),
            'contact_phone' => trim((string) $this->request->getPost('contact_phone')),
            'participant_count' => max((int) $this->request->getPost('participant_count'), 0),
            'preferred_date' => trim((string) $this->request->getPost('preferred_date')),
            'preferred_start_time' => $this->normalizeTimeForStorage((string) $this->request->getPost('preferred_start_time')),
            'preferred_end_time' => $this->normalizeTimeForStorage((string) $this->request->getPost('preferred_end_time')),
            'purpose' => trim((string) $this->request->getPost('purpose')),
            'equipment_notes' => trim((string) $this->request->getPost('equipment_notes')),
        ];

        if (db_connect()->fieldExists('service_id', 'external_requests')) {
            $payload['service_id'] = ($this->request->getPost('service_id') ?: null) ? (int) $this->request->getPost('service_id') : null;
        }
        if (db_connect()->fieldExists('selected_assets', 'external_requests')) {
            $payload['selected_assets'] = trim((string) $this->request->getPost('selected_assets')) ?: null;
        }

        return $payload;
    }

    protected function validateRequestPayload(array $payload): ?string
    {
        $rules = [
            'lab_id' => 'required|integer',
            'organization_name' => 'required|min_length[2]|max_length[255]',
            'contact_name' => 'required|min_length[2]|max_length[255]',
            'contact_email' => 'required|valid_email|max_length[255]',
            'contact_phone' => 'required|min_length[6]|max_length[50]',
            'participant_count' => 'required|integer|greater_than[0]',
            'preferred_date' => 'required|valid_date[Y-m-d]',
            'preferred_start_time' => 'required|regex_match[/^\d{2}:\d{2}:\d{2}$/]',
            'preferred_end_time' => 'required|regex_match[/^\d{2}:\d{2}:\d{2}$/]',
            'purpose' => 'required|min_length[10]',
            'equipment_notes' => 'permit_empty|string',
        ];

        if (! $this->validateData($payload, $rules)) {
            return implode(' ', $this->validator->getErrors());
        }

        $lab = $this->labModel->find((int) $payload['lab_id']);
        if (! $lab) {
            return 'Selected laboratory was not found.';
        }

        $services = $this->bundleService->serviceSummariesForLab((int) $payload['lab_id']);
        if ($services !== [] && (int) ($payload['service_id'] ?? 0) <= 0) {
            return 'Please choose one of the configured laboratory services.';
        }

        if ($payload['preferred_date'] < date('Y-m-d')) {
            return 'Preferred date cannot be in the past.';
        }

        if ($this->slotService->findMatchingDefinition((string) $payload['preferred_start_time'], (string) $payload['preferred_end_time']) === null) {
            return 'Please choose one of the configured booking slots.';
        }

        $availability = $this->slotService->slotAvailabilityForLab(
            (int) $payload['lab_id'],
            (string) $payload['preferred_date'],
            (string) $payload['preferred_start_time'],
            (string) $payload['preferred_end_time']
        );

        if (! ($availability['can_book'] ?? false)) {
            return (string) ($availability['reason'] ?? 'Selected booking slot is no longer available.');
        }

        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId > 0) {
            $requirements = $this->bundleService->requirementMapForService((int) $payload['lab_id'], $serviceId);
            if ($requirements === []) {
                return 'The selected service is not linked to a valid equipment bundle.';
            }

            $bookingController = new \App\Controllers\Public\BookingController();
            $remaining = (function () use ($bookingController, $payload, $requirements) {
                return $bookingController->computeRemainingForSlot(
                    (int) $payload['lab_id'],
                    (string) $payload['preferred_date'],
                    (string) $payload['preferred_start_time'],
                    (string) $payload['preferred_end_time'],
                    $requirements
                );
            })();

            foreach ($requirements as $assetId => $quantityRequired) {
                if (($remaining[$assetId] ?? 0) < $quantityRequired) {
                    return 'The selected service is not fully available for this slot.';
                }
            }
        }

        return null;
    }

    protected function externalDaySlotsInternal(int $labId, string $date): array
    {
        if ($labId <= 0 || $this->labModel->find($labId) === null) {
            return [];
        }

        if ($this->validDate($date) === '') {
            return [];
        }

        return $this->slotService->daySlotsForLab($labId, $date);
    }

    protected function normalizeTimeForStorage(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value . ':00';
        }

        return preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1 ? $value : null;
    }

    protected function normalizeTimeForDisplay(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return substr($value, 0, 5);
        }

        return preg_match('/^\d{2}:\d{2}$/', $value) === 1 ? $value : '';
    }

    protected function validDate(string $value): string
    {
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }
}
