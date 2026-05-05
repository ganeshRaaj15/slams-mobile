<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Models\FacultyModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Models\UserModel;

class ProfileController extends BaseController
{
    public function index()
    {
        helper('auth');

        if (!auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $user = auth()->user();
        if ($user->inGroup('pic') || $user->inGroup('manager')) {
            return redirect()->to('/dashboard')->with('error', 'Profile updates for PIC and Manager roles are managed by Admin.');
        }
        $userModel = model(UserModel::class);
        $facultyModel = new FacultyModel();

        $db = \Config\Database::connect();
        $identity = $db->table('auth_identities')
            ->where('user_id', $user->id)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();

        $email = $identity['secret'] ?? '';
        $faculties = $facultyModel->getAllForDropdown();

        $layout = 'layouts/main_user';
        $backUrl = '/dashboard';
        $title = 'My Profile | FKMP Smart Lab';

        if ($user->inGroup('admin')) {
            $layout = 'layouts/main_admin';
            $backUrl = '/dashboard/admin';
        } elseif ($user->inGroup('technician')) {
            $layout = 'layouts/main_technician';
            $backUrl = '/dashboard/technician';
            $title = 'Technician Profile | FKMP Smart Lab';
        }

        return view('dashboard/profile/index', [
            'layout' => $layout,
            'title' => $title,
            'roleLabel' => $user->inGroup('technician') ? 'Technician' : null,
            'backUrl' => $backUrl,
            'user' => $userModel->findById($user->id),
            'email' => $email,
            'faculties' => $faculties,
            'page' => 'Profile',
        ]);
    }

    public function update(): RedirectResponse
    {
        helper('auth');

        if (!auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $user = auth()->user();
        if ($user->inGroup('pic') || $user->inGroup('manager')) {
            return redirect()->to('/dashboard')->with('error', 'Profile updates for PIC and Manager roles are managed by Admin.');
        }
        $post = $this->request->getPost();

        $rules = [
            'username' => 'required|min_length[3]|max_length[30]',
            'full_name' => 'permit_empty|max_length[120]',
            'phone' => 'permit_empty|max_length[40]',
            'faculty_id' => 'permit_empty|integer',
            'email' => 'required|valid_email|max_length[255]',
            'password' => 'permit_empty|min_length[8]',
            'password_confirm' => 'matches[password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $db = \Config\Database::connect();
        $email = strtolower(trim((string) $post['email']));

        // Email uniqueness check
        $existingEmail = $db->table('auth_identities')
            ->where('type', 'email_password')
            ->where('LOWER(secret) =', $email)
            ->where('user_id !=', $user->id)
            ->countAllResults();

        if ($existingEmail > 0) {
            return redirect()->back()->withInput()->with('errors', ['Email already exists.']);
        }

        // Username uniqueness check
        $existingUsername = $db->table('users')
            ->where('username', trim($post['username']))
            ->where('id !=', $user->id)
            ->countAllResults();

        if ($existingUsername > 0) {
            return redirect()->back()->withInput()->with('errors', ['Username already exists.']);
        }

        $photoPath = null;
        $photoFile = $this->request->getFile('profile_photo');

        if ($photoFile && $photoFile->isValid() && ! $photoFile->hasMoved()) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (! in_array($photoFile->getMimeType(), $allowedTypes, true)) {
                return redirect()->back()->withInput()->with('errors', ['Profile photo must be a JPG, PNG, or WEBP image.']);
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
            'username' => trim($post['username']),
            'full_name' => $post['full_name'] !== '' ? trim($post['full_name']) : null,
            'phone' => $post['phone'] !== '' ? trim($post['phone']) : null,
            'faculty_id' => $post['faculty_id'] !== '' ? (int)$post['faculty_id'] : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($photoPath) {
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

        if (! empty($post['password'])) {
            $db->table('auth_identities')
                ->where('user_id', $user->id)
                ->where('type', 'email_password')
                ->set('secret2', password_hash($post['password'], PASSWORD_DEFAULT))
                ->set('updated_at', date('Y-m-d H:i:s'))
                ->update();
        }

        return redirect()->to('/dashboard/profile')->with('message', 'Profile updated successfully.');
    }
}

