<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Libraries\ExternalRequestBookingService;
use App\Libraries\ExternalRequestNotificationService;
use App\Libraries\UserRoleResolver;
use App\Models\ExternalRequestModel;
use App\Models\LaboratoryModel;
use Config\Database;

class ExternalRequestsController extends BaseController
{
    protected ExternalRequestModel $requestModel;
    protected LaboratoryModel $labModel;
    protected ExternalRequestBookingService $requestBookingService;

    public function __construct()
    {
        helper('auth');
        $this->requestModel = new ExternalRequestModel();
        $this->labModel = new LaboratoryModel();
        $this->requestBookingService = new ExternalRequestBookingService();
    }

    public function index()
    {
        $role = $this->reviewerRole();
        if ($role === null) {
            return redirect()->to('/dashboard')->with('error', 'Access denied.');
        }

        $user = auth()->user();
        $filters = $this->requestFilters();
        $scope = $this->labScopeForRole($role, strtolower(trim((string) $user->email)));
        $labIds = $scope['labIds'];

        $requestsQuery = $this->requestModel
            ->select('external_requests.*, laboratories.name AS lab_name, laboratories.room AS lab_room, users.username AS requester_username, users.full_name AS requester_full_name')
            ->join('laboratories', 'laboratories.id = external_requests.lab_id', 'left')
            ->join('users', 'users.id = external_requests.user_id', 'left');

        if ($role === 'pic') {
            if ($labIds === []) {
                $requestsQuery->where('1 = 0');
            } else {
                $requestsQuery->whereIn('external_requests.lab_id', $labIds);
            }
        } elseif ($role === 'manager') {
            $requestsQuery->where('external_requests.pic_approved', 1);
        }

        $requests = $this->applyRequestFilters($requestsQuery, $filters)
            ->orderBy('external_requests.created_at', 'DESC')
            ->findAll();

        return view('dashboard/external_requests/index', [
            'title' => 'External Request Queue | FKMP Smart Lab',
            'page' => 'External Request Queue',
            'layout' => $this->resolveLayout($user),
            'user' => $user,
            'role' => $role,
            'requests' => $requests,
            'filters' => $filters,
            'labs' => $scope['labs'],
            'stats' => $this->requestStats($role, $labIds),
            'statusLabels' => $this->requestModel->statusLabels(),
            'requestModel' => $this->requestModel,
        ]);
    }

    public function show(int $id)
    {
        $role = $this->reviewerRole();
        if ($role === null) {
            return redirect()->to('/dashboard')->with('error', 'Access denied.');
        }

        $user = auth()->user();
        $requestRecord = $this->authorizedRequest($id, $role, strtolower(trim((string) $user->email)));
        if (! $requestRecord) {
            return redirect()->to('/dashboard/external-requests')->with('error', 'External request not found.');
        }

        return view('dashboard/external_requests/show', [
            'title' => 'Review External Request | FKMP Smart Lab',
            'page' => 'Review External Request',
            'layout' => $this->resolveLayout($user),
            'user' => $user,
            'role' => $role,
            'requestRecord' => $requestRecord,
            'statusLabels' => $this->requestModel->statusLabels(),
            'requestModel' => $this->requestModel,
        ]);
    }

    public function updateStatus(int $id)
    {
        $role = $this->reviewerRole();
        if ($role === null) {
            return redirect()->to('/dashboard')->with('error', 'Access denied.');
        }

        $user = auth()->user();
        $requestRecord = $this->authorizedRequest($id, $role, strtolower(trim((string) $user->email)));
        if (! $requestRecord) {
            return redirect()->to('/dashboard/external-requests')->with('error', 'External request not found.');
        }

        $actingRole = $this->actingReviewerRole($role, $requestRecord);
        if ($actingRole === null) {
            return redirect()->back()->with('error', 'This external request is not waiting for your approval stage.');
        }

        $status = trim((string) ($this->request->getPost('status') ?: $this->request->getPost('decision')));
        $reviewNotes = trim((string) $this->request->getPost('review_notes'));
        $allowedStatuses = $this->allowedStatusesForActor($actingRole);

        if (! in_array($status, $allowedStatuses, true)) {
            return redirect()->back()->withInput()->with('error', 'Please choose a valid external request decision.');
        }

        if (in_array($status, ['needs_information', 'rejected'], true) && $reviewNotes === '') {
            return redirect()->back()->withInput()->with('error', 'Please add notes when requesting more information or rejecting a request.');
        }

        $payload = $this->approvalUpdatePayload($requestRecord, $actingRole, $status, $reviewNotes, (int) $user->id);

        if ($status === 'approved_for_scheduling') {
            $db = Database::connect();

            try {
                $db->transBegin();
                $payload['booking_id'] = $this->requestBookingService->createApprovedBooking($requestRecord);
                if (! $this->requestModel->update($id, $payload)) {
                    throw new \RuntimeException('Could not update the external request after reserving the slot.');
                }
                $db->transCommit();
            } catch (\Throwable $e) {
                if ($db->transStatus()) {
                    $db->transRollback();
                }

                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }
        } else {
            $this->requestModel->update($id, $payload);
        }

        try {
            (new ExternalRequestNotificationService())->notifyStatusUpdated($id, $actingRole);
        } catch (\Throwable $e) {
            log_message('error', 'External request notification failed on review update: ' . $e->getMessage());
        }

        return redirect()->to('/dashboard/external-requests/' . $id)
            ->with('success', 'External request updated to ' . $this->requestModel->statusLabel($status) . '.');
    }

    protected function reviewerRole(): ?string
    {
        if (! auth()->loggedIn()) {
            return null;
        }

        return (new UserRoleResolver())->approvalRole(auth()->user());
    }

    protected function labScopeForRole(string $role, string $userEmail): array
    {
        if ($role === 'pic') {
            $labs = $this->labModel
                ->where('LOWER(TRIM(pic_email)) =', $userEmail)
                ->orderBy('name', 'ASC')
                ->findAll();

            return [
                'labs' => $labs,
                'labIds' => array_map(static fn(array $lab): int => (int) $lab['id'], $labs),
            ];
        }

        $labs = $this->labModel->orderBy('name', 'ASC')->findAll();
        return [
            'labs' => $labs,
            'labIds' => array_map(static fn(array $lab): int => (int) $lab['id'], $labs),
        ];
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
                ->orLike('external_requests.contact_email', $filters['q'])
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

    protected function requestStats(string $role, array $labIds): array
    {
        $stats = ['total' => 0];

        foreach (ExternalRequestModel::STATUSES as $status) {
            $builder = $this->requestModel->where('status', $status);
            if ($role === 'pic') {
                if ($labIds === []) {
                    $stats[$status] = 0;
                    continue;
                }
                $builder->whereIn('lab_id', $labIds);
            } elseif ($role === 'manager') {
                $builder->where('pic_approved', 1);
            }

            $stats[$status] = (int) $builder->countAllResults();
            $stats['total'] += $stats[$status];
        }

        return $stats;
    }

    protected function authorizedRequest(int $id, string $role, string $userEmail): ?array
    {
        $builder = $this->requestModel
            ->select('external_requests.*, laboratories.name AS lab_name, laboratories.room AS lab_room, laboratories.pic_email, users.username AS requester_username, users.full_name AS requester_full_name, pic_reviewer.username AS pic_reviewer_username, pic_reviewer.full_name AS pic_reviewer_full_name, manager_reviewer.username AS manager_reviewer_username, manager_reviewer.full_name AS manager_reviewer_full_name')
            ->join('laboratories', 'laboratories.id = external_requests.lab_id', 'left')
            ->join('users', 'users.id = external_requests.user_id', 'left')
            ->join('users pic_reviewer', 'pic_reviewer.id = external_requests.pic_reviewed_by', 'left')
            ->join('users manager_reviewer', 'manager_reviewer.id = external_requests.manager_reviewed_by', 'left')
            ->where('external_requests.id', $id);

        if ($role === 'pic') {
            $builder->where('LOWER(TRIM(laboratories.pic_email)) =', $userEmail);
        } elseif ($role === 'manager') {
            $builder->where('external_requests.pic_approved', 1);
        }

        return $builder->first();
    }

    protected function actingReviewerRole(string $role, array $requestRecord): ?string
    {
        if ($role === 'pic') {
            return $this->requestAwaitingStage($requestRecord) === 'pic' ? 'pic' : null;
        }

        if ($role === 'manager') {
            return $this->requestAwaitingStage($requestRecord) === 'manager' ? 'manager' : null;
        }

        if ($role === 'admin') {
            return $this->requestAwaitingStage($requestRecord);
        }

        return null;
    }

    protected function requestAwaitingStage(array $requestRecord): ?string
    {
        $status = (string) ($requestRecord['status'] ?? '');
        $currentStage = (string) ($requestRecord['current_approval_stage'] ?? '');

        if ($status === 'pending_pic_approval') {
            return 'pic';
        }

        if ($status === 'pending_manager_approval') {
            return 'manager';
        }

        if ($status === 'needs_information' && in_array($currentStage, ['pic', 'manager'], true)) {
            return $currentStage;
        }

        return null;
    }

    protected function allowedStatusesForActor(string $actingRole): array
    {
        return $actingRole === 'pic'
            ? ['pending_manager_approval', 'needs_information', 'rejected']
            : ['approved_for_scheduling', 'needs_information', 'rejected'];
    }

    protected function approvalUpdatePayload(array $requestRecord, string $actingRole, string $status, string $reviewNotes, int $userId): array
    {
        $now = date('Y-m-d H:i:s');
        $payload = [
            'status' => $status,
            'review_notes' => $reviewNotes !== '' ? $reviewNotes : ($requestRecord['review_notes'] ?? null),
            'reviewed_by' => $userId,
            'reviewed_at' => $now,
            'information_requested_by' => null,
        ];

        if ($actingRole === 'pic') {
            $payload['pic_notes'] = $reviewNotes !== '' ? $reviewNotes : ($requestRecord['pic_notes'] ?? null);
            $payload['pic_reviewed_by'] = $userId;
            $payload['pic_reviewed_at'] = $now;

            if ($status === 'pending_manager_approval') {
                $payload['pic_approved'] = 1;
                $payload['current_approval_stage'] = 'manager';
            } elseif ($status === 'needs_information') {
                $payload['pic_approved'] = 0;
                $payload['current_approval_stage'] = 'pic';
                $payload['information_requested_by'] = 'pic';
            } else {
                $payload['pic_approved'] = 0;
                $payload['current_approval_stage'] = 'completed';
                $payload['manager_approved'] = 0;
            }

            return $payload;
        }

        $payload['manager_notes'] = $reviewNotes !== '' ? $reviewNotes : ($requestRecord['manager_notes'] ?? null);
        $payload['manager_reviewed_by'] = $userId;
        $payload['manager_reviewed_at'] = $now;

        if ($status === 'approved_for_scheduling') {
            $payload['manager_approved'] = 1;
            $payload['current_approval_stage'] = 'completed';
        } elseif ($status === 'needs_information') {
            $payload['manager_approved'] = 0;
            $payload['current_approval_stage'] = 'manager';
            $payload['information_requested_by'] = 'manager';
        } else {
            $payload['manager_approved'] = 0;
            $payload['current_approval_stage'] = 'completed';
        }

        return $payload;
    }

    protected function resolveLayout($user): string
    {
        return $user->inGroup('admin') ? 'layouts/main_admin' : 'layouts/main_user';
    }
}
