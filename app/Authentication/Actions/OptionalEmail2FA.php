<?php

namespace App\Authentication\Actions;

use App\Authentication\OtpPolicy;
use CodeIgniter\Shield\Authentication\Actions\Email2FA;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;

class OptionalEmail2FA extends Email2FA
{
    public function createIdentity(User $user): string
    {
        if (! (new OtpPolicy())->requiresOtp($user)) {
            model(UserIdentityModel::class)->deleteIdentitiesByType($user, $this->getType());

            return '';
        }

        return parent::createIdentity($user);
    }
}
