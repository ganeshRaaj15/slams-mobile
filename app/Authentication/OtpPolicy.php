<?php

namespace App\Authentication;

use CodeIgniter\Shield\Entities\User;

class OtpPolicy
{
    public function requiresOtp(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return (bool) ($user->twofa_enabled ?? false);
        }

        return true;
    }

    protected function isAdmin(User $user): bool
    {
        if ($user->inGroup('admin')) {
            return true;
        }

        return db_connect()
            ->table('auth_groups_users')
            ->where('user_id', $user->id)
            ->where('group', 'admin')
            ->countAllResults() > 0;
    }
}
