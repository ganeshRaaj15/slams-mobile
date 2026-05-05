<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Libraries\ExternalRequestNotificationService;
use App\Models\ExternalRequestModel;
use App\Models\LaboratoryModel;

class ExternalDashboard extends BaseController
{
    protected ExternalRequestModel $requestModel;
    protected LaboratoryModel $labModel;

    public function __construct()
    {
        helper('auth');
        $this->requestModel = new ExternalRequestModel();
        $this->labModel = new LaboratoryModel();
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
                'status' => 'submitted',
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
        $payload['status'] = 'submitted';

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

        $payload['status'] = 'submitted';
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
        return [
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
            'preferred_start_time' => 'permit_empty|regex_match[/^\d{2}:\d{2}:\d{2}$/]',
            'preferred_end_time' => 'permit_empty|regex_match[/^\d{2}:\d{2}:\d{2}$/]',
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

        if ($payload['preferred_date'] < date('Y-m-d')) {
            return 'Preferred date cannot be in the past.';
        }

        $hasStart = ! empty($payload['preferred_start_time']);
        $hasEnd = ! empty($payload['preferred_end_time']);
        if ($hasStart xor $hasEnd) {
            return 'Please provide both preferred start and end times, or leave both empty.';
        }
        if ($hasStart && $payload['preferred_start_time'] >= $payload['preferred_end_time']) {
            return 'Preferred end time must be later than preferred start time.';
        }

        return null;
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
