<?php

namespace App\Controllers\Api;

use App\Controllers\Dashboard\ExternalRequestsController as WebExternalRequestsController;
use App\Libraries\ExternalRequestNotificationService;
use App\Models\ExternalRequestModel;
use CodeIgniter\Shield\Entities\User;
use Config\Database;

class NativeExternalRequestReviewController extends WebExternalRequestsController
{
    public function index()
    {
        $reviewer = $this->reviewerContext();
        if ($reviewer instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $reviewer;
        }

        $filters = $this->requestFilters();
        $scope = $this->labScopeForRole($reviewer['role'], $reviewer['email']);
        $labIds = $scope['labIds'];

        $requestsQuery = $this->requestModel
            ->select('external_requests.*, laboratories.name AS lab_name, laboratories.room AS lab_room, users.username AS requester_username, users.full_name AS requester_full_name')
            ->join('laboratories', 'laboratories.id = external_requests.lab_id', 'left')
            ->join('users', 'users.id = external_requests.user_id', 'left');

        if ($reviewer['role'] === 'pic') {
            if ($labIds === []) {
                $requestsQuery->where('1 = 0');
            } else {
                $requestsQuery->whereIn('external_requests.lab_id', $labIds);
            }
        } elseif ($reviewer['role'] === 'manager') {
            $requestsQuery->where('external_requests.pic_approved', 1);
        }

        $requests = $this->applyRequestFilters($requestsQuery, $filters)
            ->orderBy('external_requests.created_at', 'DESC')
            ->findAll();

        return $this->response->setJSON([
            'status' => 'success',
            'role' => $reviewer['role'],
            'stats' => $this->requestStats($reviewer['role'], $labIds),
            'status_labels' => $this->requestModel->statusLabels(),
            'labs' => array_map(static fn(array $lab): array => [
                'id' => (int) $lab['id'],
                'name' => (string) ($lab['name'] ?? ''),
                'room' => (string) ($lab['room'] ?? ''),
            ], $scope['labs']),
            'filters' => $filters,
            'requests' => array_map(fn(array $request): array => $this->serializeReviewRequest($request), $requests),
        ]);
    }

    public function show(int $id)
    {
        $reviewer = $this->reviewerContext();
        if ($reviewer instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $reviewer;
        }

        $requestRecord = $this->authorizedRequest($id, $reviewer['role'], $reviewer['email']);
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
            'role' => $reviewer['role'],
            'request' => $this->serializeReviewRequest($requestRecord),
        ]);
    }

    public function updateStatus(int $id)
    {
        $reviewer = $this->reviewerContext();
        if ($reviewer instanceof \CodeIgniter\HTTP\ResponseInterface) {
            return $reviewer;
        }

        $requestRecord = $this->authorizedRequest($id, $reviewer['role'], $reviewer['email']);
        if (! $requestRecord) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'External request not found.',
                ]);
        }

        $json = $this->request->getJSON(true);
        $payload = is_array($json) && $json !== [] ? $json : ($this->request->getPost() ?: []);

        $actingRole = $this->actingReviewerRole($reviewer['role'], $requestRecord);
        if ($actingRole === null) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'This external request is not waiting for your approval stage.',
                ]);
        }

        $status = trim((string) (($payload['status'] ?? '') ?: ($payload['decision'] ?? '')));
        $reviewNotes = trim((string) ($payload['review_notes'] ?? ''));
        $allowedStatuses = $this->allowedStatusesForActor($actingRole);

        if (! in_array($status, $allowedStatuses, true)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Please choose a valid external request decision.',
                ]);
        }

        if (in_array($status, ['needs_information', 'rejected'], true) && $reviewNotes === '') {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Please add review notes when requesting more information or rejecting a request.',
                ]);
        }

        /** @var User $user */
        $user = $reviewer['user'];

        $updatePayload = $this->approvalUpdatePayload($requestRecord, $actingRole, $status, $reviewNotes, (int) $user->id);

        if ($status === 'approved_for_scheduling') {
            $db = Database::connect();

            try {
                $db->transBegin();
                $updatePayload['booking_id'] = $this->requestBookingService->createApprovedBooking($requestRecord);
                if (! $this->requestModel->update($id, $updatePayload)) {
                    throw new \RuntimeException('Could not update the external request after reserving the slot.');
                }
                $db->transCommit();
            } catch (\Throwable $e) {
                $db->transRollback();

                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ]);
            }
        } else {
            $this->requestModel->update($id, $updatePayload);
        }

        try {
            (new ExternalRequestNotificationService())->notifyStatusUpdated($id, $actingRole);
        } catch (\Throwable $e) {
            log_message('error', 'External request notification failed on native review update: ' . $e->getMessage());
        }

        $updated = $this->authorizedRequest($id, $reviewer['role'], $reviewer['email']);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'External request updated to ' . $this->requestModel->statusLabel($status) . '.',
            'request' => $updated ? $this->serializeReviewRequest($updated) : null,
        ]);
    }

    /**
     * @return array<string, mixed>|\CodeIgniter\HTTP\ResponseInterface
     */
    protected function reviewerContext()
    {
        helper('auth');

        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Unauthenticated.',
                ]);
        }

        $role = $this->reviewerRole();
        if ($role === null) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'External request review is limited to PIC, Manager, and Admin roles.',
                ]);
        }

        return [
            'user' => $user,
            'role' => $role,
            'email' => strtolower(trim((string) $user->email)),
        ];
    }

    protected function serializeReviewRequest(array $request): array
    {
        $requesterName = trim((string) ($request['requester_full_name'] ?? '')) ?: (string) ($request['contact_name'] ?? $request['requester_username'] ?? '-');
        $reviewerName = trim((string) ($request['reviewer_full_name'] ?? '')) ?: (string) ($request['reviewer_username'] ?? '');

        $payload = [
            'id' => (int) $request['id'],
            'lab_id' => (int) ($request['lab_id'] ?? 0),
            'lab_name' => (string) ($request['lab_name'] ?? ''),
            'lab_room' => (string) ($request['lab_room'] ?? ''),
            'requester_name' => $requesterName,
            'requester_username' => (string) ($request['requester_username'] ?? ''),
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
            'current_approval_stage' => $this->requestModel->currentApprovalStage($request),
            'current_approval_stage_label' => $this->requestModel->stageLabel($this->requestModel->currentApprovalStage($request)),
            'information_requested_by' => (string) ($request['information_requested_by'] ?? ''),
            'review_notes' => (string) ($request['review_notes'] ?? ''),
            'latest_requester_note' => $this->requestModel->latestRequesterNote($request),
            'pic_approved' => (bool) ($request['pic_approved'] ?? false),
            'pic_notes' => (string) ($request['pic_notes'] ?? ''),
            'pic_reviewer_name' => trim((string) ($request['pic_reviewer_full_name'] ?? '')) ?: (string) ($request['pic_reviewer_username'] ?? ''),
            'pic_reviewed_at' => (string) ($request['pic_reviewed_at'] ?? ''),
            'manager_approved' => (bool) ($request['manager_approved'] ?? false),
            'manager_notes' => (string) ($request['manager_notes'] ?? ''),
            'manager_reviewer_name' => trim((string) ($request['manager_reviewer_full_name'] ?? '')) ?: (string) ($request['manager_reviewer_username'] ?? ''),
            'manager_reviewed_at' => (string) ($request['manager_reviewed_at'] ?? ''),
            'reviewer_name' => $reviewerName,
            'reviewed_at' => (string) ($request['reviewed_at'] ?? ''),
            'created_at' => (string) ($request['created_at'] ?? ''),
            'updated_at' => (string) ($request['updated_at'] ?? ''),
        ];

        if (db_connect()->fieldExists('service_id', 'external_requests')) {
            $payload['service_id'] = isset($request['service_id']) ? (int) $request['service_id'] : null;
        }
        if (db_connect()->fieldExists('selected_assets', 'external_requests')) {
            $payload['selected_assets'] = (string) ($request['selected_assets'] ?? '');
        }

        return $payload;
    }
}
