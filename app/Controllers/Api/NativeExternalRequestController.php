<?php

namespace App\Controllers\Api;

use App\Controllers\Dashboard\ExternalDashboard as WebExternalDashboard;
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
        $payload['status'] = 'submitted';

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

        $payload['status'] = 'submitted';
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

    protected function nativeRequestPayload(): array
    {
        $json = $this->request->getJSON(true);
        $data = is_array($json) && $json !== [] ? $json : ($this->request->getPost() ?: []);

        return [
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
    }

    protected function serializeRequest(array $request): array
    {
        return [
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
            'status' => (string) ($request['status'] ?? ''),
            'status_label' => $this->requestModel->statusLabel((string) ($request['status'] ?? '')),
            'review_notes' => (string) ($request['review_notes'] ?? ''),
            'can_edit' => $this->requestModel->canUserEdit($request),
            'created_at' => (string) ($request['created_at'] ?? ''),
            'updated_at' => (string) ($request['updated_at'] ?? ''),
        ];
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
}
