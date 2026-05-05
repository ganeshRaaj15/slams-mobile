<?php

namespace App\Validation;

use App\Libraries\UserReclaimService;

class UserRules
{
    public function reusable_username($value, ?string $params = null, array $data = []): bool
    {
        $username = trim((string) $value);

        if ($username === '') {
            return true;
        }

        return (new UserReclaimService())->usernameAvailable($username);
    }
}
