<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Libraries\ExternalRequestNotificationService;
use App\Libraries\UserRoleResolver;
use App\Models\ExternalRequestModel;
use App\Models\LaboratoryModel;

class ExternalRequestsController extends BaseController
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

        $status = trim((string) $this->request->getPost('status'));
        $reviewNotes = trim((string) $this->request->getPost('review_notes'));

        if (! in_array($status, ExternalRequestModel::STATUSES, true)) {
            return redirect()->back()->withInput()->with('error', 'Please choose a valid request status.');
        }

        if (in_array($status, ['needs_information', 'rejected'], true) && $reviewNotes === '') {
            return redirect()->back()->withInput()->with('error', 'Please add review notes when requesting more information or rejecting a request.');
        }

        $this->requestModel->update($id, [
            'status' => $status,
            'review_notes' => $reviewNotes !== '' ? $reviewNotes : null,
            'reviewed_by' => $user->id,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            (new ExternalRequestNotificationService())->notifyStatusUpdated($id);
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
            }

            $stats[$status] = (int) $builder->countAllResults();
            $stats['total'] += $stats[$status];
        }

        return $stats;
    }

    protected function authorizedRequest(int $id, string $role, string $userEmail): ?array
    {
        $builder = $this->requestModel
            ->select('external_requests.*, laboratories.name AS lab_name, laboratories.room AS lab_room, laboratories.pic_email, users.username AS requester_username, users.full_name AS requester_full_name, reviewer.username AS reviewer_username, reviewer.full_name AS reviewer_full_name')
            ->join('laboratories', 'laboratories.id = external_requests.lab_id', 'left')
            ->join('users', 'users.id = external_requests.user_id', 'left')
            ->join('users reviewer', 'reviewer.id = external_requests.reviewed_by', 'left')
            ->where('external_requests.id', $id);

        if ($role === 'pic') {
            $builder->where('LOWER(TRIM(laboratories.pic_email)) =', $userEmail);
        }

        return $builder->first();
    }

    protected function resolveLayout($user): string
    {
        return $user->inGroup('admin') ? 'layouts/main_admin' : 'layouts/main_user';
    }
}
