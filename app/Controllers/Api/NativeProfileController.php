<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\NativeUserSerializer;
use App\Models\FacultyModel;
use CodeIgniter\Shield\Entities\User;

class NativeProfileController extends BaseController
{
    protected NativeUserSerializer $serializer;
    protected FacultyModel $facultyModel;

    public function __construct()
    {
        helper('auth');
        $this->serializer = new NativeUserSerializer();
        $this->facultyModel = new FacultyModel();
    }

    public function show()
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

        return $this->response->setJSON([
            'status' => 'success',
            'user' => $this->serializer->serialize($user),
            'editable' => $this->canEditProfile($user),
            'editable_reason' => $this->canEditProfile($user)
                ? null
                : 'Profile updates for PIC and Manager roles are managed by Admin.',
            'faculties' => array_map(static function (array $faculty): array {
                return [
                    'id' => (int) $faculty['id'],
                    'code' => (string) ($faculty['code'] ?? ''),
                    'name_bm' => (string) ($faculty['name_bm'] ?? ''),
                    'name_en' => (string) ($faculty['name_en'] ?? ''),
                    'is_fkmp' => (bool) ($faculty['is_fkmp'] ?? false),
                    'label' => trim(((string) ($faculty['code'] ?? '')) . ' - ' . ((string) ($faculty['name_en'] ?? ''))),
                ];
            }, $this->facultyModel->orderBy('name_bm', 'ASC')->findAll()),
        ]);
    }

    public function update()
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

        if (! $this->canEditProfile($user)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Profile updates for PIC and Manager roles are managed by Admin.',
                ]);
        }

        $payload = $this->requestPayload();
        $rules = [
            'username' => 'required|min_length[3]|max_length[30]',
            'full_name' => 'permit_empty|max_length[120]',
            'phone' => 'permit_empty|max_length[40]',
            'faculty_id' => 'permit_empty|integer',
            'email' => 'required|valid_email|max_length[255]',
            'password' => 'permit_empty|min_length[8]',
            'password_confirm' => 'matches[password]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid profile update payload.',
                    'errors' => $this->validator->getErrors(),
                ]);
        }

        $db = db_connect();
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $username = trim((string) ($payload['username'] ?? ''));

        $existingEmail = $db->table('auth_identities')
            ->where('type', 'email_password')
            ->where('LOWER(secret) =', $email)
            ->where('user_id !=', $user->id)
            ->countAllResults();
        if ($existingEmail > 0) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Email already exists.',
                ]);
        }

        $existingUsername = $db->table('users')
            ->where('username', $username)
            ->where('id !=', $user->id)
            ->countAllResults();
        if ($existingUsername > 0) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Username already exists.',
                ]);
        }

        $photoPath = null;
        $photoFile = $this->request->getFile('profile_photo');
        if ($photoFile && $photoFile->isValid() && ! $photoFile->hasMoved()) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (! in_array($photoFile->getMimeType(), $allowedTypes, true)) {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'Profile photo must be a JPG, PNG, or WEBP image.',
                    ]);
            }

            $destination = FCPATH . 'images/users';
            if (! is_dir($destination)) {
                mkdir($destination, 0755, true);
            }

            $newName = $photoFile->getRandomName();
            $photoFile->move($destination, $newName);
            $photoPath = 'images/users/' . $newName;
        }

        $updateData = [
            'username' => $username,
            'full_name' => trim((string) ($payload['full_name'] ?? '')) ?: null,
            'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
            'faculty_id' => ($payload['faculty_id'] ?? '') !== '' ? (int) $payload['faculty_id'] : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($photoPath !== null) {
            $updateData['profile_photo'] = $photoPath;
        }

        $db->table('users')
            ->where('id', $user->id)
            ->update($updateData);

        $db->table('auth_identities')
            ->where('user_id', $user->id)
            ->where('type', 'email_password')
            ->set('secret', $email)
            ->set('updated_at', date('Y-m-d H:i:s'))
            ->update();

        $password = (string) ($payload['password'] ?? '');
        if ($password !== '') {
            $db->table('auth_identities')
                ->where('user_id', $user->id)
                ->where('type', 'email_password')
                ->set('secret2', password_hash($password, PASSWORD_DEFAULT))
                ->set('updated_at', date('Y-m-d H:i:s'))
                ->update();
        }

        $fresh = auth()->user();
        if (! $fresh instanceof User) {
            $fresh = $user;
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Profile updated successfully.',
            'user' => $this->serializer->serialize($fresh),
        ]);
    }

    protected function canEditProfile(User $user): bool
    {
        return ! $user->inGroup('pic') && ! $user->inGroup('manager');
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
}
