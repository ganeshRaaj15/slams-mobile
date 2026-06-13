<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\NotificationService;
use App\Models\AssetModel;
use App\Models\LaboratoryModel;
use CodeIgniter\Shield\Entities\User;

class NativeAdminLaboratoryController extends BaseController
{
    protected LaboratoryModel $labModel;
    protected AssetModel $assetModel;

    public function __construct()
    {
        helper(['auth', 'filesystem']);
        $this->labModel = new LaboratoryModel();
        $this->assetModel = new AssetModel();

        foreach ([FCPATH . 'images/labs', FCPATH . 'images/pic'] as $directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
    }

    public function index()
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

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
        if ($user->inGroup('pic') && ! $user->inGroup('admin')) {
            $builder = $builder->where('LOWER(TRIM(pic_email)) =', strtolower(trim((string) $user->email)));
        }

        $labs = $builder->orderBy('name', 'ASC')->findAll();
        $assetRows = $this->dbAssetSummary();
        $picAccountMap = $this->picAccountMap($labs);

        foreach ($labs as &$lab) {
            $lab = $this->serializeLab($lab, $assetRows[$lab['id']] ?? null, $picAccountMap);
        }
        unset($lab);

        return $this->response->setJSON([
            'status' => 'success',
            'labs' => $labs,
            'filters' => $filters,
            'stats' => [
                'total_labs' => (new LaboratoryModel())->countAllResults(),
                'assigned_pic' => count(array_filter($labs, static fn(array $lab): bool => (string) ($lab['pic_email'] ?? '') !== '')),
                'unassigned_pic' => count(array_filter($labs, static fn(array $lab): bool => (string) ($lab['pic_email'] ?? '') === '')),
            ],
            'permissions' => [
                'can_create' => $this->isAdminUser(),
                'can_delete' => $this->isAdminUser(),
                'can_edit_pic_assignment' => $this->isAdminUser(),
            ],
        ]);
    }

    public function show(int $id)
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $lab = $this->labModel->find($id);
        if (! $lab) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Laboratory not found.',
            ]);
        }
        if (! $this->canManageLab($lab, $user)) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $assetRows = $this->dbAssetSummary();
        $picAccountMap = $this->picAccountMap([$lab]);

        return $this->response->setJSON([
            'status' => 'success',
            'lab' => $this->serializeLab($lab, $assetRows[$lab['id']] ?? null, $picAccountMap),
        ]);
    }

    public function store()
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }
        if (! $this->isAdminUser($user)) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Only administrators can create laboratories.']);
        }

        if (! $this->validate($this->rules())) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Invalid laboratory payload.',
                'errors' => $this->validator->getErrors(),
            ]);
        }

        $payload = $this->collectPayload();
        if ($conflict = $this->picAssignmentConflict($payload['pic_email'])) {
            return $this->response->setStatusCode(422)->setJSON(['status' => 'error', 'message' => $conflict]);
        }
        $picWarning = $this->picValidationWarning($payload['pic_email']);
        $payload['image'] = $this->handleUpload('image', 'images/labs');
        $payload['pic_image'] = $this->handleUpload('pic_image', 'images/pic');

        $labId = (int) $this->labModel->insert($payload, true);
        $lab = $this->labModel->find($labId);
        $this->syncPicAccountRoleAndNotify($labId, $payload['pic_email']);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Laboratory created successfully.',
            'warning' => $picWarning,
            'lab' => $lab ? $this->serializeLab($lab, null, $this->picAccountMap([$lab])) : null,
        ]);
    }

    public function update(int $id)
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $lab = $this->labModel->find($id);
        if (! $lab) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Laboratory not found.',
            ]);
        }
        if (! $this->canManageLab($lab, $user)) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        if (! $this->validate($this->rules())) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Invalid laboratory payload.',
                'errors' => $this->validator->getErrors(),
            ]);
        }

        $payload = $this->collectPayload();
        if (! $this->isAdminUser($user)) {
            $payload['pic_name'] = (string) ($lab['pic_name'] ?? '');
            $payload['pic_email'] = strtolower(trim((string) ($lab['pic_email'] ?? '')));
            $payload['pic_phone'] = (string) ($lab['pic_phone'] ?? '');
        }
        if ($this->isAdminUser($user) && ($conflict = $this->picAssignmentConflict($payload['pic_email'], $id))) {
            return $this->response->setStatusCode(422)->setJSON(['status' => 'error', 'message' => $conflict]);
        }
        $picWarning = $this->picValidationWarning($payload['pic_email']);
        $payload['image'] = $this->handleUpload('image', 'images/labs', $lab['image'] ?? null, (bool) $this->truthy($this->request->getPost('remove_image')));
        $payload['pic_image'] = $this->handleUpload('pic_image', 'images/pic', $lab['pic_image'] ?? null, (bool) $this->truthy($this->request->getPost('remove_pic_image')));

        $this->labModel->update($id, $payload);
        if ($this->isAdminUser($user)) {
            $this->syncPicAccountRoleAndNotify($id, $payload['pic_email']);
        }
        $updatedLab = $this->labModel->find($id);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Laboratory updated successfully.',
            'warning' => $picWarning,
            'lab' => $updatedLab ? $this->serializeLab($updatedLab, null, $this->picAccountMap([$updatedLab])) : null,
        ]);
    }

    public function delete(int $id)
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $lab = $this->labModel->find($id);
        if (! $lab) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Laboratory not found.',
            ]);
        }
        if (! $this->isAdminUser($user)) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Only administrators can delete laboratories.']);
        }

        $assetCount = $this->assetModel->where('lab_id', $id)->countAllResults();
        if ($assetCount > 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Delete or reassign laboratory assets before removing this laboratory.',
            ]);
        }

        foreach (['image', 'pic_image'] as $field) {
            if (! empty($lab[$field]) && is_file(FCPATH . $lab[$field])) {
                unlink(FCPATH . $lab[$field]);
            }
        }

        $this->labModel->delete($id);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Laboratory deleted successfully.',
        ]);
    }

    protected function serializeLab(array $lab, ?array $summary, array $picAccountMap): array
    {
        $summary ??= ['asset_total' => 0, 'assets_in_maintenance' => 0, 'faulty_assets' => 0];
        $picEmail = strtolower(trim((string) ($lab['pic_email'] ?? '')));

        return [
            'id' => (int) $lab['id'],
            'name' => (string) ($lab['name'] ?? ''),
            'room' => (string) ($lab['room'] ?? ''),
            'description' => (string) ($lab['description'] ?? ''),
            'capacity' => isset($lab['capacity']) ? (int) $lab['capacity'] : 0,
            'availability_note' => (string) ($lab['availability_note'] ?? ''),
            'safety_note' => (string) ($lab['safety_note'] ?? ''),
            'pic_name' => (string) ($lab['pic_name'] ?? ''),
            'pic_email' => (string) ($lab['pic_email'] ?? ''),
            'pic_phone' => (string) ($lab['pic_phone'] ?? ''),
            'image' => (string) ($lab['image'] ?? ''),
            'pic_image' => (string) ($lab['pic_image'] ?? ''),
            'image_url' => ! empty($lab['image']) ? base_url('/' . ltrim((string) $lab['image'], '/')) : '',
            'pic_image_url' => ! empty($lab['pic_image']) ? base_url('/' . ltrim((string) $lab['pic_image'], '/')) : '',
            'asset_total' => (int) ($summary['asset_total'] ?? 0),
            'assets_in_maintenance' => (int) ($summary['assets_in_maintenance'] ?? 0),
            'faulty_assets' => (int) ($summary['faulty_assets'] ?? 0),
            'pic_account_linked' => $picEmail !== '' && isset($picAccountMap[$picEmail]),
            'pic_account_has_role' => $picEmail !== '' && isset($picAccountMap[$picEmail]) && in_array('pic', $picAccountMap[$picEmail]['roles'], true),
        ];
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
            'image' => 'permit_empty|max_size[image,2048]|ext_in[image,jpg,jpeg,png,gif,webp]',
            'pic_image' => 'permit_empty|max_size[pic_image,2048]|ext_in[pic_image,jpg,jpeg,png,gif,webp]',
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
            return 'PIC email ' . $email . ' is not linked to a user account. The lab was saved, but create or update the PIC user account before production use.';
        }

        if (! in_array('pic', $account['roles'], true)) {
            return 'PIC email ' . $email . ' belongs to a user account without the PIC role. The lab was saved, but assign the PIC role before approval workflows rely on it.';
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
        $rows = $this->assetModel
            ->select("lab_id, COUNT(*) AS asset_total, SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) AS assets_in_maintenance, SUM(CASE WHEN status = 'faulty' THEN 1 ELSE 0 END) AS faulty_assets")
            ->groupBy('lab_id')
            ->findAll();

        $summary = [];
        foreach ($rows as $row) {
            $summary[(int) $row['lab_id']] = $row;
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

    protected function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    protected function authorizedAdmin()
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ]);
        }

        if (! $user->inGroup('admin') && ! $user->inGroup('pic')) {
            return $this->response->setStatusCode(403)->setJSON([
                'status' => 'error',
                'message' => 'Unauthorized access.',
            ]);
        }

        return $user;
    }

    protected function isAdminUser(?User $user = null): bool
    {
        $user ??= auth()->user();
        return $user instanceof User && $user->inGroup('admin');
    }

    protected function canManageLab(array $lab, ?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user instanceof User) {
            return false;
        }
        if ($this->isAdminUser($user)) {
            return true;
        }

        return strtolower(trim((string) $user->email)) === strtolower(trim((string) ($lab['pic_email'] ?? '')));
    }

    protected function picAssignmentConflict(string $email, int $ignoreLabId = 0): ?string
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $builder = $this->labModel->where('LOWER(TRIM(pic_email)) =', $email);
        if ($ignoreLabId > 0) {
            $builder->where('id !=', $ignoreLabId);
        }

        $labs = $builder->findAll();
        if ($labs === []) {
            return null;
        }

        $names = array_map(static fn(array $lab): string => trim(((string) ($lab['name'] ?? '')) . ' (' . ((string) ($lab['room'] ?? '-')) . ')'), $labs);
        return 'This user is already assigned as PIC for ' . implode(', ', $names) . '. Reassign that laboratory first.';
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
        $promoted = false;
        if (! in_array('pic', $account['roles'], true)) {
            $db->table('auth_groups_users')->insert([
                'user_id' => (int) $account['user_id'],
                'group' => 'pic',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $db->table('auth_groups_users')->where('user_id', (int) $account['user_id'])->where('group', 'staff')->delete();
            $promoted = true;
        }

        $lab = $this->labModel->find($labId);
        if (is_array($lab)) {
            NotificationService::dispatchSafely(
                fn(NotificationService $notifications) => $notifications->notifyPicAssignedToLab($lab, $email, $promoted),
                'native pic assigned to lab'
            );
        }
    }
}
