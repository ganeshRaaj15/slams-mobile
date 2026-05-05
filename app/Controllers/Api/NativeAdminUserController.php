<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\AccountRecoveryService;
use App\Libraries\UserReclaimService;
use App\Models\FacultyModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

class NativeAdminUserController extends BaseController
{
    protected UserModel $users;
    protected BaseConnection $db;
    protected FacultyModel $faculties;
    protected UserReclaimService $userReclaimService;
    protected array $allowedRoles = ['student', 'staff', 'external', 'technician', 'pic', 'manager', 'admin'];

    public function __construct()
    {
        helper('auth');
        $this->users = model(UserModel::class);
        $this->db = db_connect();
        $this->faculties = new FacultyModel();
        $this->userReclaimService = new UserReclaimService();
    }

    public function index()
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $filters = $this->userFilters();
        $allRows = $this->filteredUserRows($filters);
        $totalFiltered = count($allRows);
        $pageCount = max((int) ceil($totalFiltered / $filters['per_page']), 1);
        $page = min($filters['page'], $pageCount);
        $offset = ($page - 1) * $filters['per_page'];
        $userData = array_slice($allRows, $offset, $filters['per_page']);

        $stats = [
            'total' => (int) $this->activeUsersTable()->countAllResults(),
            'active' => (int) $this->activeUsersTable()->where('active', 1)->countAllResults(),
        ];

        return $this->response->setJSON([
            'status' => 'success',
            'users' => $userData,
            'filters' => array_merge($filters, ['page' => $page]),
            'all_roles' => $this->allowedRoles,
            'faculties' => $this->serializedFaculties(),
            'stats' => $stats,
            'pagination' => [
                'total' => $totalFiltered,
                'page' => $page,
                'per_page' => $filters['per_page'],
                'page_count' => $pageCount,
            ],
        ]);
    }

    public function show(int $id)
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $target = $this->users->findById($id);
        if (! $target) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'User not found.',
                ]);
        }

        $roles = $this->db->table('auth_groups_users')->where('user_id', $target->id)->get()->getResultArray();
        $row = $this->db->table('users')->where('id', $target->id)->get()->getRowArray() ?? [];

        return $this->response->setJSON([
            'status' => 'success',
            'user' => [
                'id' => (int) $target->id,
                'username' => (string) ($row['username'] ?? $target->username ?? ''),
                'full_name' => (string) ($row['full_name'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'faculty_id' => isset($row['faculty_id']) ? (int) $row['faculty_id'] : null,
                'active' => isset($row['active']) ? (bool) $row['active'] : true,
                'email' => $this->getEmailForUser((int) $target->id),
                'roles' => array_values(array_column($roles, 'group')),
            ],
            'all_roles' => $this->allowedRoles,
            'faculties' => $this->serializedFaculties(),
        ]);
    }

    public function store()
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $payload = $this->requestPayload();
        $validationError = $this->validateUserPayload($payload, null);
        if ($validationError !== null) {
            return $validationError;
        }

        $username = trim((string) ($payload['username'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $roles = $this->normalizedRoles($payload['roles'] ?? []);
        $facultyId = ($payload['faculty_id'] ?? '') !== '' ? (int) $payload['faculty_id'] : null;

        $this->db->transStart();
        try {
            $this->db->table('users')->insert([
                'username' => $username,
                'active' => 1,
                'full_name' => trim((string) ($payload['full_name'] ?? '')) ?: null,
                'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
                'faculty_id' => $facultyId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $userId = (int) $this->db->insertID();
            if ($userId <= 0) {
                throw new \RuntimeException('Failed to get user ID from database insert.');
            }

            $this->db->table('auth_identities')->insert([
                'user_id' => $userId,
                'type' => 'email_password',
                'secret' => $email,
                'secret2' => password_hash($password, PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $now = date('Y-m-d H:i:s');
            foreach ($roles as $role) {
                $this->db->table('auth_groups_users')->insert([
                    'user_id' => $userId,
                    'group' => $role,
                    'created_at' => $now,
                ]);
            }

            $this->db->transComplete();
            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Transaction failed.');
            }
        } catch (\Throwable $e) {
            $this->db->transRollback();

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Failed to create user: ' . $e->getMessage(),
                ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'User created successfully.',
            'user_id' => $userId,
        ]);
    }

    public function update(int $id)
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $target = $this->users->findById($id);
        if (! $target) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'User not found.',
                ]);
        }

        $payload = $this->requestPayload();
        $validationError = $this->validateUserPayload($payload, $id, true);
        if ($validationError !== null) {
            return $validationError;
        }

        $username = trim((string) ($payload['username'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $facultyId = ($payload['faculty_id'] ?? '') !== '' ? (int) $payload['faculty_id'] : null;
        $active = isset($payload['active']) ? ((int) (bool) $payload['active']) : 1;
        $newRoles = $this->normalizedRoles($payload['roles'] ?? []);
        $currentEmail = $this->getEmailForUser((int) $target->id);

        if ($this->isPicEmailInUse($currentEmail) && $email !== $currentEmail) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'This user is currently assigned as a laboratory PIC. Update the laboratory PIC email first before changing this account email.',
                ]);
        }
        if ($this->isPicEmailInUse($currentEmail) && ! in_array('pic', $newRoles, true)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'This user is currently assigned as a laboratory PIC. Update the laboratory record before removing the PIC role.',
                ]);
        }

        $target->username = $username;
        $this->users->save($target);

        $this->db->table('users')->where('id', $target->id)->update([
            'username' => $username,
            'full_name' => trim((string) ($payload['full_name'] ?? '')) ?: null,
            'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
            'faculty_id' => $facultyId,
            'active' => $active,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('auth_identities')->where('user_id', $target->id)->where('type', 'email_password')
            ->set('secret', $email)
            ->set('updated_at', date('Y-m-d H:i:s'))
            ->update();

        if ($password !== '') {
            $this->db->table('auth_identities')->where('user_id', $target->id)->where('type', 'email_password')
                ->set('secret2', password_hash($password, PASSWORD_DEFAULT))
                ->set('updated_at', date('Y-m-d H:i:s'))
                ->update();
        }

        $this->db->table('auth_groups_users')->where('user_id', $target->id)->delete();
        $now = date('Y-m-d H:i:s');
        foreach ($newRoles as $role) {
            $this->db->table('auth_groups_users')->insert([
                'user_id' => $target->id,
                'group' => $role,
                'created_at' => $now,
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'User updated successfully.',
        ]);
    }

    public function delete(int $id)
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $target = $this->users->findById($id);
        if (! $target) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'User not found.',
                ]);
        }

        $email = $this->getEmailForUser((int) $target->id);
        $adminCheck = $this->db->table('auth_groups_users')
            ->where('user_id', $target->id)
            ->whereIn('group', ['admin', 'manager'])
            ->countAllResults() > 0;
        if ($adminCheck) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Cannot delete Admin or Manager accounts.',
                ]);
        }
        if ($this->isPicEmailInUse($email)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Cannot delete this user while they are assigned as a laboratory PIC. Update the laboratory record first.',
                ]);
        }
        if ($this->hasOperationalLinks((int) $target->id)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Cannot delete this user because linked booking or maintenance records exist. Deactivate the account instead.',
                ]);
        }

        $this->db->table('auth_identities')->where('user_id', $target->id)->delete();
        $this->db->table('auth_groups_users')->where('user_id', $target->id)->delete();
        $this->users->delete($target->id, true);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'User deleted successfully.',
        ]);
    }

    public function sendRecovery(int $id)
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $target = $this->users->findById($id);
        if (! $target) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'User not found.',
                ]);
        }

        if ((int) ($target->active ?? 0) !== 1) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Activate this user before sending a recovery link.',
                ]);
        }

        $email = $this->getEmailForUser((int) $target->id);
        if ($email === '') {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'This user does not have a registered email address.',
                ]);
        }

        if (! (new AccountRecoveryService())->sendLoginLink($target)) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Unable to send the recovery email. Check the email settings and try again.',
                ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Recovery link sent to the registered email address.',
        ]);
    }

    protected function validateUserPayload(array $payload, ?int $userId = null, bool $passwordOptional = false)
    {
        $username = trim((string) ($payload['username'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $passwordConfirm = (string) ($payload['password_confirm'] ?? '');
        $roles = $this->normalizedRoles($payload['roles'] ?? []);
        $facultyId = ($payload['faculty_id'] ?? '') !== '' ? (int) $payload['faculty_id'] : null;

        if ($username === '' || $email === '' || (! $passwordOptional && $password === '')) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => $passwordOptional
                        ? 'Username and email are required.'
                        : 'Username, email and password are required.',
                ]);
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Please provide a valid email address.',
                ]);
        }
        if ($password !== '' && strlen($password) < 8) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Password must be at least 8 characters long.',
                ]);
        }
        if ($password !== $passwordConfirm) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Passwords do not match.',
                ]);
        }
        if ($roles === []) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Assign at least one role to the user.',
                ]);
        }
        if (in_array('student', $roles, true) && ! $facultyId) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Student accounts must have a faculty assigned for booking approval routing.',
                ]);
        }

        if (! $this->userReclaimService->usernameAvailable($username, $userId)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Username already exists.',
                ]);
        }

        $emailQuery = $this->db->table('auth_identities')
            ->where('type', 'email_password')
            ->where('LOWER(secret) =', $email);
        if ($userId !== null) {
            $emailQuery->where('user_id !=', $userId);
        }
        if ($emailQuery->countAllResults() > 0) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Email already exists.',
                ]);
        }

        return null;
    }

    protected function normalizedRoles($roles): array
    {
        if (! is_array($roles)) {
            $roles = [$roles];
        }

        return array_values(array_intersect(array_map('strval', $roles), $this->allowedRoles));
    }

    protected function getEmailForUser(int $userId): string
    {
        $identity = $this->db->table('auth_identities')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->get()
            ->getRow();

        return isset($identity->secret) ? strtolower(trim((string) $identity->secret)) : '';
    }

    protected function userFilters(): array
    {
        $perPage = (int) $this->request->getGet('per_page');
        if (! in_array($perPage, [10, 25, 50], true)) {
            $perPage = 25;
        }

        $role = trim((string) $this->request->getGet('role'));
        if (! in_array($role, $this->allowedRoles, true)) {
            $role = '';
        }

        $status = trim((string) $this->request->getGet('status'));
        if (! in_array($status, ['active', 'inactive'], true)) {
            $status = '';
        }

        return [
            'q' => trim((string) $this->request->getGet('q')),
            'role' => $role,
            'status' => $status,
            'per_page' => $perPage,
            'page' => max((int) $this->request->getGet('page'), 1),
        ];
    }

    protected function filteredUserRows(array $filters): array
    {
        $builder = $this->db->table('users u')
            ->select("u.id, u.username, u.full_name, u.phone, u.faculty_id, u.active, i.secret AS email, GROUP_CONCAT(DISTINCT agu.`group` ORDER BY agu.`group` SEPARATOR ',') AS role_list", false)
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id', 'left')
            ->where('u.deleted_at', null)
            ->groupBy('u.id, u.username, u.full_name, u.phone, u.faculty_id, u.active, i.secret')
            ->orderBy('u.username', 'ASC');

        if ($filters['q'] !== '') {
            $builder->groupStart()
                ->like('u.username', $filters['q'])
                ->orLike('u.full_name', $filters['q'])
                ->orLike('u.phone', $filters['q'])
                ->orLike('i.secret', $filters['q'])
                ->groupEnd();
        }

        if ($filters['status'] === 'active') {
            $builder->where('u.active', 1);
        } elseif ($filters['status'] === 'inactive') {
            $builder->where('u.active', 0);
        }

        if ($filters['role'] !== '') {
            $roleRows = $this->db->table('auth_groups_users')
                ->select('user_id')
                ->where('group', $filters['role'])
                ->get()
                ->getResultArray();
            $userIds = array_map(static fn(array $row): int => (int) $row['user_id'], $roleRows);
            if ($userIds === []) {
                $builder->where('u.id', 0);
            } else {
                $builder->whereIn('u.id', $userIds);
            }
        }

        $rows = $builder->get()->getResultArray();

        return array_map(static function (array $row): array {
            $roles = array_values(array_filter(explode(',', (string) ($row['role_list'] ?? ''))));

            return [
                'id' => (int) $row['id'],
                'username' => (string) ($row['username'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'roles' => $roles,
                'active' => (bool) ($row['active'] ?? false),
                'full_name' => (string) ($row['full_name'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'faculty_id' => isset($row['faculty_id']) ? (int) $row['faculty_id'] : null,
            ];
        }, $rows);
    }

    protected function isPicEmailInUse(string $email): bool
    {
        if ($email === '') {
            return false;
        }

        return $this->db->table('laboratories')
            ->where('LOWER(pic_email) =', strtolower(trim($email)))
            ->countAllResults() > 0;
    }

    protected function hasOperationalLinks(int $userId): bool
    {
        $bookingCount = $this->db->table('bookings')->where('user_id', $userId)->countAllResults();
        $reportedCount = $this->db->table('maintenance_records')->where('reported_by', $userId)->countAllResults();
        $assignedCount = $this->db->table('maintenance_records')->where('assigned_technician_id', $userId)->countAllResults();

        return ($bookingCount + $reportedCount + $assignedCount) > 0;
    }

    protected function activeUsersTable(string $alias = 'users')
    {
        return $this->db->table($alias)->where(($alias === 'users' ? 'deleted_at' : $alias . '.deleted_at'), null);
    }

    protected function authorizedAdmin()
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

        if (! $user->inGroup('admin')) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Unauthorized access.',
                ]);
        }

        return $user;
    }

    protected function requestPayload(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json) && $json !== []) {
            return $json;
        }

        $post = $this->request->getPost();

        return is_array($post) ? $post : [];
    }

    protected function serializedFaculties(): array
    {
        return array_map(static function (array $faculty): array {
            return [
                'id' => (int) $faculty['id'],
                'code' => (string) ($faculty['code'] ?? ''),
                'name_bm' => (string) ($faculty['name_bm'] ?? ''),
                'name_en' => (string) ($faculty['name_en'] ?? ''),
                'is_fkmp' => (bool) ($faculty['is_fkmp'] ?? false),
                'label' => trim(((string) ($faculty['code'] ?? '')) . ' - ' . ((string) ($faculty['name_en'] ?? ''))),
            ];
        }, $this->faculties->orderBy('name_bm', 'ASC')->findAll());
    }
}
