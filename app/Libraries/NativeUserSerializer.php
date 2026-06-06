<?php

namespace App\Libraries;

use CodeIgniter\Shield\Entities\User;

class NativeUserSerializer
{
    protected UserRoleResolver $roleResolver;

    public function __construct()
    {
        $this->roleResolver = new UserRoleResolver();
    }

    public function serialize(User $user): array
    {
        $profile = db_connect()->table('users')
            ->select('id, username, full_name, phone, faculty_id, profile_photo, active, twofa_enabled')
            ->where('id', $user->id)
            ->get()
            ->getRowArray() ?? [];

        $roles = $this->roles($user);

        return [
            'id' => (int) $user->id,
            'email' => (string) ($user->email ?? ''),
            'username' => (string) ($profile['username'] ?? $user->username ?? ''),
            'full_name' => (string) ($profile['full_name'] ?? ''),
            'phone' => (string) ($profile['phone'] ?? ''),
            'faculty_id' => isset($profile['faculty_id']) ? (int) $profile['faculty_id'] : null,
            'profile_photo' => (string) ($profile['profile_photo'] ?? ''),
            'profile_photo_url' => $this->profilePhotoUrl((string) ($profile['profile_photo'] ?? '')),
            'active' => isset($profile['active']) ? (bool) $profile['active'] : true,
            'twofa_enabled' => isset($profile['twofa_enabled']) ? (bool) $profile['twofa_enabled'] : false,
            'roles' => $roles,
            'primary_role' => $this->primaryRole($roles),
            'dashboard_path' => $this->dashboardPath($roles),
        ];
    }

    /**
     * @return list<string>
     */
    public function roles(User $user): array
    {
        return $this->roleResolver->rolesForUser($user);
    }

    /**
     * @param list<string> $roles
     */
    public function primaryRole(array $roles): string
    {
        return $this->roleResolver->primaryRole($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function dashboardPath(array $roles): string
    {
        if (in_array('admin', $roles, true)) {
            return '/dashboard/admin';
        }
        if (in_array('manager', $roles, true)) {
            return '/dashboard/manager';
        }
        if (in_array('pic', $roles, true)) {
            return '/dashboard/pic';
        }
        if (in_array('technician', $roles, true)) {
            return '/dashboard/technician';
        }
        if (in_array('external', $roles, true)) {
            return '/dashboard/external';
        }

        return '/dashboard/student';
    }

    protected function profilePhotoUrl(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '';
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        return base_url('/' . ltrim($trimmed, '/'));
    }
}
