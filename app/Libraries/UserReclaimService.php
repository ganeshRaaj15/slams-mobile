<?php

namespace App\Libraries;

use CodeIgniter\Shield\Models\UserModel;

class UserReclaimService
{
    protected UserModel $users;

    public function __construct()
    {
        $this->users = model(UserModel::class);
    }

    public function reclaimSoftDeletedUsername(string $username, int $ignoreId = 0): void
    {
        $username = strtolower(trim($username));

        if ($username === '') {
            return;
        }

        $builder = db_connect()->table('users')
            ->select('id')
            ->where('LOWER(username) =', $username)
            ->where('deleted_at IS NOT NULL', null, false);

        if ($ignoreId > 0) {
            $builder->where('id !=', $ignoreId);
        }

        $rows = $builder->get()->getResultArray();

        foreach ($rows as $row) {
            $this->users->delete((int) $row['id'], true);
        }
    }

    public function usernameExists(string $username, int $ignoreId = 0): bool
    {
        $username = strtolower(trim($username));

        if ($username === '') {
            return false;
        }

        $builder = db_connect()->table('users')
            ->where('LOWER(username) =', $username);

        if ($ignoreId > 0) {
            $builder->where('id !=', $ignoreId);
        }

        return $builder->countAllResults() > 0;
    }

    public function activeUsernameExists(string $username, int $ignoreId = 0): bool
    {
        $username = strtolower(trim($username));

        if ($username === '') {
            return false;
        }

        $builder = db_connect()->table('users')
            ->where('LOWER(username) =', $username)
            ->where('deleted_at', null);

        if ($ignoreId > 0) {
            $builder->where('id !=', $ignoreId);
        }

        return $builder->countAllResults() > 0;
    }

    public function usernameAvailable(string $username, int $ignoreId = 0): bool
    {
        $this->reclaimSoftDeletedUsername($username, $ignoreId);

        return ! $this->usernameExists($username, $ignoreId);
    }
}
