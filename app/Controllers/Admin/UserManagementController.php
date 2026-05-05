<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\AccountRecoveryService;
use App\Libraries\UserReclaimService;
use App\Models\FacultyModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

class UserManagementController extends BaseController
{
    protected $users;
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

        return view('admin/users/index', [
            'users' => $userData,
            'filters' => array_merge($filters, ['page' => $page]),
            'allRoles' => $this->allowedRoles,
            'stats' => $stats,
            'pagination' => [
                'total' => $totalFiltered,
                'page' => $page,
                'per_page' => $filters['per_page'],
                'page_count' => $pageCount,
            ],
        ]);
    }

    public function exportCsv()
    {
        $filters = $this->userFilters();
        $rows = $this->filteredUserRows($filters);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['ID', 'Username', 'Full Name', 'Email', 'Phone', 'Roles', 'Status']);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['id'],
                $row['username'],
                $row['full_name'],
                $row['email'],
                $row['phone'],
                implode(', ', $row['roles']),
                $row['active'] ? 'Active' : 'Inactive',
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="slams-users-' . date('Ymd_His') . '.csv"')
            ->setBody($csv);
    }

    public function edit($id)
    {
        $user = $this->users->findById($id);
        if (! $user) {
            return redirect()->back()->with('error', 'User not found.');
        }

        $roles = $this->db->table('auth_groups_users')->where('user_id', $user->id)->get()->getResultArray();

        return view('admin/users/edit', [
            'user' => $user,
            'email' => $this->getEmailForUser((int) $user->id),
            'roles' => array_column($roles, 'group'),
            'allRoles' => $this->allowedRoles,
            'faculties' => $this->faculties->getAllForDropdown(),
        ]);
    }

    public function update($id)
    {
        $user = $this->users->findById($id);
        if (! $user) {
            return redirect()->back()->with('error', 'User not found.');
        }

        $data = $this->request->getPost();
        $username = trim((string) ($data['username'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $passwordConfirm = (string) ($data['password_confirm'] ?? '');
        $facultyId = ($data['faculty_id'] ?? '') !== '' ? (int) $data['faculty_id'] : null;
        $active = isset($data['active']) ? (int) $data['active'] : (int) $user->active;
        $newRoles = array_values(array_intersect($data['roles'] ?? [], $this->allowedRoles));
        $currentEmail = $this->getEmailForUser((int) $user->id);

        if ($username === '' || $email === '') {
            return redirect()->back()->withInput()->with('error', 'Username and email cannot be empty.');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->withInput()->with('error', 'Please provide a valid email address.');
        }
        if ($password !== '' && strlen($password) < 8) {
            return redirect()->back()->withInput()->with('error', 'Password must be at least 8 characters long.');
        }
        if ($password !== $passwordConfirm) {
            return redirect()->back()->withInput()->with('error', 'Passwords do not match.');
        }
        if ($newRoles === []) {
            return redirect()->back()->withInput()->with('error', 'Assign at least one role to the user.');
        }
        if (in_array('student', $newRoles, true) && ! $facultyId) {
            return redirect()->back()->withInput()->with('error', 'Student accounts must have a faculty assigned for booking approval routing.');
        }

        if (! $this->userReclaimService->usernameAvailable($username, (int) $user->id)) {
            return redirect()->back()->withInput()->with('error', 'Username already exists.');
        }

        $emailExists = $this->db->table('auth_identities')->where('type', 'email_password')->where('LOWER(secret) =', $email)->where('user_id !=', $user->id)->countAllResults();
        if ($emailExists > 0) {
            return redirect()->back()->withInput()->with('error', 'Email already exists.');
        }

        if ($this->isPicEmailInUse($currentEmail) && $email !== $currentEmail) {
            return redirect()->back()->withInput()->with('error', 'This user is currently assigned as a laboratory PIC. Update the laboratory PIC email first before changing this account email.');
        }
        if ($this->isPicEmailInUse($currentEmail) && ! in_array('pic', $newRoles, true)) {
            return redirect()->back()->withInput()->with('error', 'This user is currently assigned as a laboratory PIC. Update the laboratory record before removing the PIC role.');
        }

        $user->username = $username;
        $this->users->save($user);

        $this->db->table('users')->where('id', $user->id)->update([
            'username' => $username,
            'full_name' => trim((string) ($data['full_name'] ?? '')) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'faculty_id' => $facultyId,
            'active' => $active ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('auth_identities')->where('user_id', $user->id)->where('type', 'email_password')->set('secret', $email)->set('updated_at', date('Y-m-d H:i:s'))->update();

        if ($password !== '') {
            $this->db->table('auth_identities')->where('user_id', $user->id)->where('type', 'email_password')->set('secret2', password_hash($password, PASSWORD_DEFAULT))->set('updated_at', date('Y-m-d H:i:s'))->update();
        }

        $this->db->table('auth_groups_users')->where('user_id', $user->id)->delete();
        $now = date('Y-m-d H:i:s');
        foreach ($newRoles as $role) {
            $this->db->table('auth_groups_users')->insert(['user_id' => $user->id, 'group' => $role, 'created_at' => $now]);
        }

        return redirect()->to('/admin/users')->with('message', 'User updated successfully.');
    }

    public function delete($id)
    {
        $user = $this->users->findById($id);
        if (! $user) {
            return redirect()->back()->with('error', 'User not found.');
        }

        $email = $this->getEmailForUser((int) $user->id);
        $adminCheck = $this->db->table('auth_groups_users')->where('user_id', $user->id)->whereIn('group', ['admin', 'manager'])->countAllResults() > 0;
        if ($adminCheck) {
            return redirect()->back()->with('error', 'Cannot delete Admin or Manager accounts.');
        }
        if ($this->isPicEmailInUse($email)) {
            return redirect()->back()->with('error', 'Cannot delete this user while they are assigned as a laboratory PIC. Update the laboratory record first.');
        }
        if ($this->hasOperationalLinks((int) $user->id)) {
            return redirect()->back()->with('error', 'Cannot delete this user because linked booking or maintenance records exist. Deactivate the account instead.');
        }

        $this->db->table('auth_identities')->where('user_id', $user->id)->delete();
        $this->db->table('auth_groups_users')->where('user_id', $user->id)->delete();
        $this->users->delete($user->id, true);

        return redirect()->to('/admin/users')->with('message', 'User deleted successfully.');
    }

    public function sendRecovery($id)
    {
        $user = $this->users->findById($id);
        if (! $user) {
            return redirect()->back()->with('error', 'User not found.');
        }

        if ((int) ($user->active ?? 0) !== 1) {
            return redirect()->back()->with('error', 'Activate this user before sending a recovery link.');
        }

        $email = $this->getEmailForUser((int) $user->id);
        if ($email === '') {
            return redirect()->back()->with('error', 'This user does not have a registered email address.');
        }

        if (! (new AccountRecoveryService())->sendLoginLink($user)) {
            return redirect()->back()->with('error', 'Unable to send the recovery email. Check the email settings and try again.');
        }

        return redirect()->back()->with('message', 'Recovery link sent to the registered email address.');
    }

    public function create()
    {
        return view('admin/users/create', [
            'allRoles' => $this->allowedRoles,
            'faculties' => $this->faculties->getAllForDropdown(),
        ]);
    }

    public function store()
    {
        $data = $this->request->getPost();
        $username = trim((string) ($data['username'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $passwordConfirm = (string) ($data['password_confirm'] ?? '');
        $newRoles = array_values(array_intersect($data['roles'] ?? [], $this->allowedRoles));
        $facultyId = ($data['faculty_id'] ?? '') !== '' ? (int) $data['faculty_id'] : null;

        if ($username === '' || $email === '' || $password === '') {
            return redirect()->back()->withInput()->with('error', 'Username, email and password are required.');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->withInput()->with('error', 'Please provide a valid email address.');
        }
        if (strlen($password) < 8) {
            return redirect()->back()->withInput()->with('error', 'Password must be at least 8 characters long.');
        }
        if ($password !== $passwordConfirm) {
            return redirect()->back()->withInput()->with('error', 'Passwords do not match.');
        }
        if ($newRoles === []) {
            return redirect()->back()->withInput()->with('error', 'Assign at least one role to the user.');
        }
        if (in_array('student', $newRoles, true) && ! $facultyId) {
            return redirect()->back()->withInput()->with('error', 'Student accounts must have a faculty assigned for booking approval routing.');
        }

        if (! $this->userReclaimService->usernameAvailable($username)) {
            return redirect()->back()->withInput()->with('error', 'Username already exists.');
        }
        $emailExists = $this->db->table('auth_identities')->where('LOWER(secret) =', $email)->where('type', 'email_password')->countAllResults() > 0;
        if ($emailExists) {
            return redirect()->back()->withInput()->with('error', 'Email already exists.');
        }

        $this->db->transStart();
        try {
            $this->db->table('users')->insert([
                'username' => $username,
                'active' => 1,
                'full_name' => trim((string) ($data['full_name'] ?? '')) ?: null,
                'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                'faculty_id' => $facultyId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $userId = $this->db->insertID();
            if (empty($userId)) {
                throw new \RuntimeException('Failed to get user ID from database insert');
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
            foreach ($newRoles as $role) {
                $this->db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => $role, 'created_at' => $now]);
            }

            $this->db->transComplete();
            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Transaction failed');
            }

            return redirect()->to('/admin/users')->with('message', 'User created successfully.');
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'User creation failed: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Failed to create user: ' . $e->getMessage());
        }
    }

    protected function getEmailForUser(int $userId): string
    {
        $identity = $this->db->table('auth_identities')->where('user_id', $userId)->where('type', 'email_password')->get()->getRow();
        return isset($identity->secret) ? strtolower(trim((string) $identity->secret)) : '';
    }

    protected function userFilters(): array
    {
        $perPage = (int) $this->request->getGet('per_page');
        if (! in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
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
            ->select("u.id, u.username, u.full_name, u.phone, u.active, i.secret AS email, GROUP_CONCAT(DISTINCT agu.`group` ORDER BY agu.`group` SEPARATOR ',') AS role_list", false)
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->join('auth_groups_users agu', 'agu.user_id = u.id', 'left')
            ->where('u.deleted_at', null)
            ->groupBy('u.id, u.username, u.full_name, u.phone, u.active, i.secret')
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
            $userIds = array_map(static fn($row) => (int) $row['user_id'], $roleRows);
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
                'username' => (string) $row['username'],
                'email' => (string) ($row['email'] ?? ''),
                'roles' => $roles,
                'active' => (int) $row['active'],
                'full_name' => (string) ($row['full_name'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
            ];
        }, $rows);
    }

    protected function isPicEmailInUse(string $email): bool
    {
        if ($email === '') {
            return false;
        }
        return $this->db->table('laboratories')->where('LOWER(pic_email) =', strtolower(trim($email)))->countAllResults() > 0;
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
}
