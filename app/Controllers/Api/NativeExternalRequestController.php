<?php

namespace App\Controllers\Api;

use App\Controllers\Dashboard\ExternalDashboard as WebExternalDashboard;
use App\Libraries\ServiceBundleService;
use App\Models\ExternalRequestModel;
use CodeIgniter\Shield\Entities\User;

class NativeExternalRequestController extends WebExternalDashboard
{
    public function __construct()
    {
        parent::__construct();
        helper('auth');
    }

    public function index()
    {
        $user = $this->externalUser();
        if ($user instanceof \CodeIgniter\HTTP\RedirectResponse || $user instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $user;
        }

        $filters = $this->requestFilters();
        $query = $this->requestModel
            ->select('external_requests.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = external_requests.lab_id', 'left')
            ->where('external_requests.user_id', $user->id);

        $requests = $this->applyRequestFilters($query, $filters)
            ->orderBy('external_requests.created_at', 'DESC')
            ->findAll();

        return $this->response->setJSON([
            'status' => 'success',
            'stats' => $this->requestStats((int) $user->id),
            'status_labels' => $this->requestModel->statusLabels(),
            'requests' => array_map(fn(array $request): array => $this->serializeRequest($request), $requests),
        ]);
    }

    public function show(int $id)
    {
        $user = $this->externalUser();
        if ($user instanceof \CodeIgniter\HTTP\RedirectResponse || $user instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $user;
        }

        $requestRecord = $this->requestModel
            ->select('external_requests.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = external_requests.lab_id', 'left')
            ->where('external_requests.id', $id)
            ->where('external_requests.user_id', $user->id)
            ->first();

        if (! $requestRecord) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'External request not found.',
                ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'request' => $this->serializeRequest($requestRecord),
        ]);
    }

    public function store()
    {
        $user = $this->externalUser();
        if ($user instanceof \CodeIgniter\HTTP\RedirectResponse || $user instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $user;
        }

        $payload = $this->nativeRequestPayload();
        if ($error = $this->validateRequestPayload($payload)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => $error,
                ]);
        }

        $payload['user_id'] = $user->id;
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
            (new \App\Libraries\ExternalRequestNotificationService())->notifySubmitted($requestId);
        } catch (\Throwable $e) {
            log_message('error', 'External request notification failed on submit: ' . $e->getMessage());
        }

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'status' => 'success',
                'message' => 'External access request submitted.',
                'request_id' => $requestId,
            ]);
    }

    public function update(int $id)
    {
        $user = $this->externalUser();
        if ($user instanceof \CodeIgniter\HTTP\RedirectResponse || $user instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $user;
        }

        $requestRecord = $this->ownedRequest($id);
        if (! $requestRecord) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'External request not found.',
                ]);
        }

        if (! $this->requestModel->canUserEdit($requestRecord)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'This request can no longer be edited.',
                ]);
        }

        $payload = $this->nativeRequestPayload();
        if ($error = $this->validateRequestPayload($payload)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => $error,
                ]);
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
            (new \App\Libraries\ExternalRequestNotificationService())->notifySubmitted($id, true);
        } catch (\Throwable $e) {
            log_message('error', 'External request notification failed on resubmit: ' . $e->getMessage());
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'External request updated and resubmitted.',
        ]);
    }

    public function daySlots(int $labId, string $date)
    {
        $user = $this->externalUser();
        if ($user instanceof \CodeIgniter\HTTP\RedirectResponse || $user instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $user;
        }

        $serviceId = (int) $this->request->getGet('service_id');
        if ($serviceId > 0) {
            $selected = (new ServiceBundleService())->requirementMapForService($labId, $serviceId);
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

    public function labServices(int $labId)
    {
        $user = $this->externalUser();
        if ($user instanceof \CodeIgniter\HTTP\RedirectResponse || $user instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $user;
        }

        return $this->response->setJSON([
            'status' => 'success',
            'services' => $this->servicesForLab($labId),
        ]);
    }

    public function serviceAssets(int $serviceId)
    {
        $user = $this->externalUser();
        if ($user instanceof \CodeIgniter\HTTP\RedirectResponse || $user instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $user;
        }

        $serviceId = max($serviceId, 0);
        if ($serviceId <= 0) {
            return $this->response->setJSON([
                'status' => 'success',
                'assets' => [],
            ]);
        }

        $assets = [];
        $service = db_connect()->table('lab_services')->select('id, laboratory_id')->where('id', $serviceId)->get()->getRowArray();
        if (is_array($service)) {
            $assets = (new ServiceBundleService())->requirementsForService((int) $service['laboratory_id'], $serviceId);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'assets' => array_map(static fn(array $asset): array => [
                'id' => (int) ($asset['asset_id'] ?? 0),
                'name' => (string) ($asset['name'] ?? ''),
                'category' => (string) ($asset['category'] ?? ''),
                'quantity' => (int) ($asset['available_quantity'] ?? 0),
                'quantity_required' => (int) ($asset['quantity_required'] ?? 1),
                'status' => (string) ($asset['status'] ?? ''),
            ], $assets),
        ]);
    }

    protected function nativeRequestPayload(): array
    {
        $json = $this->request->getJSON(true);
        $data = is_array($json) && $json !== [] ? $json : ($this->request->getPost() ?: []);

        $payload = [
            'lab_id' => (int) ($data['lab_id'] ?? 0),
            'organization_name' => trim((string) ($data['organization_name'] ?? '')),
            'contact_name' => trim((string) ($data['contact_name'] ?? '')),
            'contact_email' => strtolower(trim((string) ($data['contact_email'] ?? ''))),
            'contact_phone' => trim((string) ($data['contact_phone'] ?? '')),
            'participant_count' => max((int) ($data['participant_count'] ?? 0), 0),
            'preferred_date' => trim((string) ($data['preferred_date'] ?? '')),
            'preferred_start_time' => $this->normalizeTimeForStorage((string) ($data['preferred_start_time'] ?? '')),
            'preferred_end_time' => $this->normalizeTimeForStorage((string) ($data['preferred_end_time'] ?? '')),
            'purpose' => trim((string) ($data['purpose'] ?? '')),
            'equipment_notes' => trim((string) ($data['equipment_notes'] ?? '')),
        ];

        if ($this->supportsServiceSelectionFields()) {
            $payload['service_id'] = ($data['service_id'] ?? null) ? (int) $data['service_id'] : null;
            $payload['selected_assets'] = trim((string) ($data['selected_assets'] ?? '')) ?: null;
        }

        return $payload;
    }

    protected function serializeRequest(array $request): array
    {
        $payload = [
            'id' => (int) $request['id'],
            'lab_id' => (int) ($request['lab_id'] ?? 0),
            'lab_name' => (string) ($request['lab_name'] ?? ''),
            'lab_room' => (string) ($request['lab_room'] ?? ''),
            'organization_name' => (string) ($request['organization_name'] ?? ''),
            'contact_name' => (string) ($request['contact_name'] ?? ''),
            'contact_email' => (string) ($request['contact_email'] ?? ''),
            'contact_phone' => (string) ($request['contact_phone'] ?? ''),
            'participant_count' => (int) ($request['participant_count'] ?? 0),
            'preferred_date' => (string) ($request['preferred_date'] ?? ''),
            'preferred_start_time' => $this->normalizeTimeForDisplay((string) ($request['preferred_start_time'] ?? '')),
            'preferred_end_time' => $this->normalizeTimeForDisplay((string) ($request['preferred_end_time'] ?? '')),
            'purpose' => (string) ($request['purpose'] ?? ''),
            'equipment_notes' => (string) ($request['equipment_notes'] ?? ''),
            'booking_id' => (int) ($request['booking_id'] ?? 0),
            'status' => (string) ($request['status'] ?? ''),
            'status_label' => $this->requestModel->statusLabel((string) ($request['status'] ?? '')),
            'current_approval_stage' => $this->requestModel->currentApprovalStage($request),
            'current_approval_stage_label' => $this->requestModel->stageLabel($this->requestModel->currentApprovalStage($request)),
            'information_requested_by' => (string) ($request['information_requested_by'] ?? ''),
            'review_notes' => (string) ($request['review_notes'] ?? ''),
            'latest_requester_note' => $this->requestModel->latestRequesterNote($request),
            'pic_approved' => (bool) ($request['pic_approved'] ?? false),
            'pic_notes' => (string) ($request['pic_notes'] ?? ''),
            'pic_reviewed_by' => (int) ($request['pic_reviewed_by'] ?? 0),
            'pic_reviewed_at' => (string) ($request['pic_reviewed_at'] ?? ''),
            'manager_approved' => (bool) ($request['manager_approved'] ?? false),
            'manager_notes' => (string) ($request['manager_notes'] ?? ''),
            'manager_reviewed_by' => (int) ($request['manager_reviewed_by'] ?? 0),
            'manager_reviewed_at' => (string) ($request['manager_reviewed_at'] ?? ''),
            'can_edit' => $this->requestModel->canUserEdit($request),
            'created_at' => (string) ($request['created_at'] ?? ''),
            'updated_at' => (string) ($request['updated_at'] ?? ''),
        ];

        if ($this->supportsServiceSelectionFields()) {
            $payload['service_id'] = isset($request['service_id']) ? (int) $request['service_id'] : null;
            $payload['selected_assets'] = (string) ($request['selected_assets'] ?? '');
        }

        return $payload;
    }

    protected function externalUser()
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

        if (! $user->inGroup('external')) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'External role required.',
                ]);
        }

        return $user;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function servicesForLab(int $labId): array
    {
        return (new ServiceBundleService())->serviceSummariesForLab($labId);
    }

    protected function supportsServiceSelectionFields(): bool
    {
        $db = db_connect();

        return $db->fieldExists('service_id', 'external_requests')
            && $db->fieldExists('selected_assets', 'external_requests');
    }
}
