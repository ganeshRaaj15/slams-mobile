<?php

namespace App\Libraries;

use CodeIgniter\Shield\Entities\User;

class UserRoleResolver
{
    /**
     * @var list<string>
     */
    public const ROLE_PRIORITY = [
        'admin',
        'manager',
        'pic',
        'technician',
        'student',
        'staff',
        'external',
    ];

    /**
     * @var list<string>
     */
    public const APPROVAL_PRIORITY = [
        'admin',
        'manager',
        'pic',
    ];

    /**
     * @param list<string> $roles
     * @return list<string>
     */
    public function normalize(array $roles): array
    {
        return array_values(array_unique(array_map(
            static fn(string $group): string => strtolower(trim($group)),
            $roles
        )));
    }

    /**
     * @param list<string> $roles
     */
    public function primaryRole(array $roles): string
    {
        $normalized = $this->normalize($roles);

        foreach (self::ROLE_PRIORITY as $role) {
            if (in_array($role, $normalized, true)) {
                return $role;
            }
        }

        return $normalized[0] ?? 'user';
    }

    /**
     * @return list<string>
     */
    public function rolesForUser(User $user): array
    {
        return $this->normalize($user->getGroups() ?? []);
    }

    public function primaryRoleForUser(User $user): string
    {
        return $this->primaryRole($this->rolesForUser($user));
    }

    public function approvalRole(User $user): ?string
    {
        foreach (self::APPROVAL_PRIORITY as $role) {
            if ($user->inGroup($role)) {
                return $role;
            }
        }

        return null;
    }
}
