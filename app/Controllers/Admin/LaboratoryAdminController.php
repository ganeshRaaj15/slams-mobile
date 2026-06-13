<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\NotificationService;
use App\Models\LaboratoryModel;

class LaboratoryAdminController extends BaseController
{
    protected LaboratoryModel $labModel;

    public function __construct()
    {
        helper(['auth', 'filesystem']);

        if (! auth()->loggedIn() || (! auth()->user()->inGroup('admin') && ! auth()->user()->inGroup('pic'))) {
            redirect()->to('/')->with('error', 'You are not authorized to access this page.')->send();
            exit;
        }

        $this->labModel = new LaboratoryModel();
        foreach ([FCPATH . 'images/labs', FCPATH . 'images/pic'] as $directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
    }

    public function index()
    {
        $user = auth()->user();
        $filters = [
            'q' => trim((string) $this->request->getGet('q')),
            'pic' => trim((string) $this->request->getGet('pic')),
        ];
        if (! in_array($filters['pic'], ['assigned', 'unassigned'], true)) {
            $filters['pic'] = '';
        }

        $builder = $this->labModel;
        if ($filters['q'] !== '') {
            $builder = $builder->groupStart()
                ->like('name', $filters['q'])
                ->orLike('room', $filters['q'])
                ->orLike('pic_name', $filters['q'])
                ->orLike('pic_email', $filters['q'])
                ->groupEnd();
        }
        if ($filters['pic'] === 'assigned') {
            $builder = $builder->where('pic_email !=', '');
        } elseif ($filters['pic'] === 'unassigned') {
            $builder = $builder->groupStart()
                ->where('pic_email', null)
                ->orWhere('pic_email', '')
                ->groupEnd();
        }

        if ($user && $user->inGroup('pic') && ! $user->inGroup('admin')) {
            $builder = $builder->where('LOWER(TRIM(pic_email)) =', strtolower(trim((string) $user->email)));
        }

        $labs = $builder->orderBy('name', 'ASC')->findAll();
        $assetRows = $this->dbAssetSummary();
        $picAccountMap = $this->picAccountMap($labs);

        foreach ($labs as &$lab) {
            $summary = $assetRows[$lab['id']] ?? ['asset_total' => 0, 'assets_in_maintenance' => 0, 'faulty_assets' => 0];
            $lab['asset_total'] = (int) $summary['asset_total'];
            $lab['assets_in_maintenance'] = (int) $summary['assets_in_maintenance'];
            $lab['faulty_assets'] = (int) $summary['faulty_assets'];
            $lab['image_url'] = ! empty($lab['image']) ? base_url($lab['image']) : '';
            $lab['pic_image_url'] = ! empty($lab['pic_image']) ? base_url($lab['pic_image']) : '';
            $picEmail = strtolower(trim((string) ($lab['pic_email'] ?? '')));
            $lab['pic_account_linked'] = $picEmail !== '' && isset($picAccountMap[$picEmail]);
            $lab['pic_account_has_role'] = $lab['pic_account_linked'] && in_array('pic', $picAccountMap[$picEmail]['roles'], true);
        }
        unset($lab);

        return view('admin/labs/index', [
            'labs' => $labs,
            'filters' => $filters,
            'totalLabs' => (new LaboratoryModel())->countAllResults(),
            'canCreateLabs' => $this->isAdminUser(),
        ]);
    }

    public function create()
    {
        if (! $this->isAdminUser()) {
            return redirect()->to('/admin/labs')->with('error', 'Only administrators can create laboratories.');
        }

        return view('admin/labs/form', ['lab' => null]);
    }

    public function edit($id)
    {
        $lab = $this->labModel->find($id);
        if (! $lab) {
            return redirect()->to('/admin/labs')->with('error', 'Laboratory not found.');
        }
        if (! $this->canManageLab($lab)) {
            return redirect()->to('/admin/labs')->with('error', 'You are not allowed to edit this laboratory.');
        }

        $lab['image_url'] = ! empty($lab['image']) ? base_url($lab['image']) : '';
        $lab['pic_image_url'] = ! empty($lab['pic_image']) ? base_url($lab['pic_image']) : '';

        return view('admin/labs/form', ['lab' => $lab, 'canEditPicAssignment' => $this->isAdminUser()]);
    }

    public function store()
    {
        if (! $this->isAdminUser()) {
            return redirect()->to('/admin/labs')->with('error', 'Only administrators can create laboratories.');
        }

        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $payload = $this->collectPayload();
        if ($conflict = $this->picAssignmentConflict($payload['pic_email'])) {
            return redirect()->back()->withInput()->with('errors', ['pic_email' => $conflict]);
        }
        $picWarning = $this->picValidationWarning($payload['pic_email']);
        $payload['image'] = $this->handleUpload('image', 'images/labs');
        $payload['pic_image'] = $this->handleUpload('pic_image', 'images/pic');

        $labId = (int) $this->labModel->insert($payload, true);
        $this->syncPicAccountRoleAndNotify($labId, $payload['pic_email']);
        $redirect = redirect()->to('/admin/labs')->with('message', 'Laboratory created successfully.');
        return $picWarning ? $redirect->with('warning', $picWarning) : $redirect;
    }

    public function update($id)
    {
        $lab = $this->labModel->find($id);
        if (! $lab) {
            return redirect()->to('/admin/labs')->with('error', 'Laboratory not found.');
        }
        if (! $this->canManageLab($lab)) {
            return redirect()->to('/admin/labs')->with('error', 'You are not allowed to update this laboratory.');
        }

        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $payload = $this->collectPayload();
        if (! $this->isAdminUser()) {
            $payload['pic_name'] = (string) ($lab['pic_name'] ?? '');
            $payload['pic_email'] = strtolower(trim((string) ($lab['pic_email'] ?? '')));
            $payload['pic_phone'] = (string) ($lab['pic_phone'] ?? '');
        }
        if ($this->isAdminUser() && ($conflict = $this->picAssignmentConflict($payload['pic_email'], (int) $id))) {
            return redirect()->back()->withInput()->with('errors', ['pic_email' => $conflict]);
        }
        $picWarning = $this->picValidationWarning($payload['pic_email']);
        $payload['image'] = $this->handleUpload('image', 'images/labs', $lab['image'] ?? null, (bool) $this->request->getPost('remove_image'));
        $payload['pic_image'] = $this->handleUpload('pic_image', 'images/pic', $lab['pic_image'] ?? null, (bool) $this->request->getPost('remove_pic_image'));

        $this->labModel->update($id, $payload);
        if ($this->isAdminUser()) {
            $this->syncPicAccountRoleAndNotify((int) $id, $payload['pic_email']);
        }
        $redirect = redirect()->to('/admin/labs')->with('message', 'Laboratory updated successfully.');
        return $picWarning ? $redirect->with('warning', $picWarning) : $redirect;
    }

    public function delete($id)
    {
        if (! $this->isAdminUser()) {
            return redirect()->to('/admin/labs')->with('error', 'Only administrators can delete laboratories.');
        }

        $lab = $this->labModel->find($id);
        if (! $lab) {
            return redirect()->to('/admin/labs')->with('error', 'Laboratory not found.');
        }

        $assetCount = model('App\\Models\\AssetModel')->where('lab_id', $id)->countAllResults();
        if ($assetCount > 0) {
            return redirect()->to('/admin/labs')->with('error', 'Delete or reassign laboratory assets before removing this laboratory.');
        }

        foreach (['image', 'pic_image'] as $field) {
            if (! empty($lab[$field]) && is_file(FCPATH . $lab[$field])) {
                unlink(FCPATH . $lab[$field]);
            }
        }

        $this->labModel->delete($id);
        return redirect()->to('/admin/labs')->with('message', 'Laboratory deleted successfully.');
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|min_length[3]|max_length[255]',
            'room' => 'required|max_length[50]',
            'description' => 'permit_empty|string',
            'capacity' => 'permit_empty|integer|greater_than[0]',
            'availability_note' => 'permit_empty|max_length[255]',
            'safety_note' => 'permit_empty|string',
            'pic_name' => 'required|min_length[3]|max_length[255]',
            'pic_email' => 'permit_empty|valid_email|max_length[255]',
            'pic_phone' => 'permit_empty|max_length[30]',
            'image' => 'permit_empty|max_size[image,2048]|ext_in[image,jpg,jpeg,png,gif]',
            'pic_image' => 'permit_empty|max_size[pic_image,2048]|ext_in[pic_image,jpg,jpeg,png,gif]',
        ];
    }

    protected function collectPayload(): array
    {
        return [
            'name' => trim((string) $this->request->getPost('name')),
            'room' => trim((string) $this->request->getPost('room')),
            'description' => trim((string) $this->request->getPost('description')),
            'capacity' => $this->request->getPost('capacity') !== '' ? (int) $this->request->getPost('capacity') : null,
            'availability_note' => trim((string) $this->request->getPost('availability_note')),
            'safety_note' => trim((string) $this->request->getPost('safety_note')),
            'pic_name' => trim((string) $this->request->getPost('pic_name')),
            'pic_email' => strtolower(trim((string) $this->request->getPost('pic_email'))),
            'pic_phone' => trim((string) $this->request->getPost('pic_phone')),
        ];
    }

    protected function picValidationWarning(string $email): ?string
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return 'No PIC email is assigned. The lab was saved, but PIC dashboard and approval routing will not include this lab until a PIC email is set.';
        }

        $account = $this->picAccountForEmail($email);
        if ($account === null) {
            return 'PIC email ' . $email . ' is not linked to a user account. The lab was saved, but create or update the PIC user account before the demo.';
        }

        if (! in_array('pic', $account['roles'], true)) {
            return 'PIC email ' . $email . ' belongs to a user account without the PIC role. The lab was saved, but assign the PIC role before approvals are demonstrated.';
        }

        return null;
    }

    protected function handleUpload(string $field, string $targetDir, ?string $current = null, bool $remove = false): ?string
    {
        if ($remove) {
            if ($current && is_file(FCPATH . $current)) {
                unlink(FCPATH . $current);
            }
            return null;
        }

        $file = $this->request->getFile($field);
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return $current;
        }

        if ($current && is_file(FCPATH . $current)) {
            unlink(FCPATH . $current);
        }

        $newName = $file->getRandomName();
        if ($file->move(FCPATH . trim($targetDir, '/'), $newName)) {
            return trim($targetDir, '/') . '/' . $newName;
        }

        return $current;
    }

    protected function dbAssetSummary(): array
    {
        $rows = model('App\\Models\\AssetModel')
            ->select("lab_id, COUNT(*) AS asset_total, SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) AS assets_in_maintenance, SUM(CASE WHEN status = 'faulty' THEN 1 ELSE 0 END) AS faulty_assets")
            ->groupBy('lab_id')
            ->findAll();

        $summary = [];
        foreach ($rows as $row) {
            $summary[$row['lab_id']] = $row;
        }

        return $summary;
    }

    protected function picAccountMap(array $labs): array
    {
        $emails = [];
        foreach ($labs as $lab) {
            $email = strtolower(trim((string) ($lab['pic_email'] ?? '')));
            if ($email !== '') {
                $emails[] = $email;
            }
        }
        $emails = array_values(array_unique($emails));
        if ($emails === []) {
            return [];
        }

        $db = db_connect();
        $identities = $db->table('auth_identities')
            ->select('user_id, secret')
            ->where('type', 'email_password')
            ->whereIn('LOWER(secret)', $emails)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($identities as $identity) {
            $email = strtolower(trim((string) $identity['secret']));
            $roles = $db->table('auth_groups_users')
                ->select('group')
                ->where('user_id', (int) $identity['user_id'])
                ->get()
                ->getResultArray();
            $map[$email] = [
                'user_id' => (int) $identity['user_id'],
                'roles' => array_column($roles, 'group'),
            ];
        }

        return $map;
    }

    protected function picAccountForEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $db = db_connect();
        $identity = $db->table('auth_identities')
            ->select('user_id, secret')
            ->where('type', 'email_password')
            ->where('LOWER(secret) =', $email)
            ->get()
            ->getRowArray();

        if (! $identity) {
            return null;
        }

        $roles = $db->table('auth_groups_users')
            ->select('group')
            ->where('user_id', (int) $identity['user_id'])
            ->get()
            ->getResultArray();

        return [
            'user_id' => (int) $identity['user_id'],
            'email' => strtolower(trim((string) $identity['secret'])),
            'roles' => array_column($roles, 'group'),
        ];
    }

    protected function isAdminUser(): bool
    {
        return auth()->loggedIn() && auth()->user()->inGroup('admin');
    }

    protected function canManageLab(array $lab): bool
    {
        if ($this->isAdminUser()) {
            return true;
        }

        $email = strtolower(trim((string) auth()->user()->email));
        return $email !== '' && $email === strtolower(trim((string) ($lab['pic_email'] ?? '')));
    }

    protected function picAssignmentConflict(string $email, int $ignoreLabId = 0): ?string
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $conflicts = $this->labModel
            ->where('LOWER(TRIM(pic_email)) =', $email);

        if ($ignoreLabId > 0) {
            $conflicts->where('id !=', $ignoreLabId);
        }

        $labs = $conflicts->findAll();
        if ($labs === []) {
            return null;
        }

        $names = array_map(static fn(array $lab): string => trim(((string) ($lab['name'] ?? '')) . ' (' . ((string) ($lab['room'] ?? '-')) . ')'), $labs);
        return 'This user is already assigned as PIC for ' . implode(', ', $names) . '. Reassign that laboratory first before using the same PIC here.';
    }

    protected function syncPicAccountRoleAndNotify(int $labId, string $email): void
    {
        $email = strtolower(trim($email));
        if ($labId <= 0 || $email === '') {
            return;
        }

        $account = $this->picAccountForEmail($email);
        if ($account === null) {
            return;
        }

        $db = db_connect();
        $roles = $account['roles'] ?? [];
        $promoted = false;
        if (! in_array('pic', $roles, true)) {
            $db->table('auth_groups_users')->insert([
                'user_id' => (int) $account['user_id'],
                'group' => 'pic',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $db->table('auth_groups_users')
                ->where('user_id', (int) $account['user_id'])
                ->where('group', 'staff')
                ->delete();
            $promoted = true;
        }

        $lab = $this->labModel->find($labId);
        if (is_array($lab)) {
            NotificationService::dispatchSafely(
                fn(NotificationService $notifications) => $notifications->notifyPicAssignedToLab($lab, $email, $promoted),
                'pic assigned to lab'
            );
        }
    }
}
