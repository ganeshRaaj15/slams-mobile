<?php

namespace App\Libraries;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;

class AccountRecoveryService
{
    public function sendLoginLink(User $user): bool
    {
        if (empty($user->id)) {
            return false;
        }

        $email = $this->emailForUser($user);
        if ($email === '') {
            log_message('warning', 'Cannot send account recovery link: user {id} has no email identity.', ['id' => $user->id]);

            return false;
        }

        $token = bin2hex(random_bytes(32));

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identityModel->deleteIdentitiesByType($user, Session::ID_TYPE_MAGIC_LINK);

        $identityId = $identityModel->insert([
            'user_id' => $user->id,
            'type'    => Session::ID_TYPE_MAGIC_LINK,
            'secret'  => hash('sha256', $token),
            'expires' => Time::now()->addSeconds(setting('Auth.magicLinkLifetime')),
        ], true);

        if (! $this->sendEmail($user, $email, $token)) {
            if ($identityId) {
                $identityModel->delete($identityId);
            }

            return false;
        }

        return true;
    }

    private function emailForUser(User $user): string
    {
        try {
            return strtolower(trim((string) ($user->email ?? $user->getEmail() ?? '')));
        } catch (\Throwable $e) {
            log_message('error', 'Unable to read recovery email for user {id}: {message}', [
                'id'      => $user->id,
                'message' => $e->getMessage(),
            ]);

            return '';
        }
    }

    private function sendEmail(User $user, string $emailAddress, string $token): bool
    {
        helper('email');

        $request = service('request');
        $ipAddress = $request instanceof IncomingRequest ? $request->getIPAddress() : 'CLI';
        $userAgent = $request instanceof IncomingRequest ? (string) $request->getUserAgent() : 'CLI';
        $date = Time::now()->toDateTimeString();

        $email = emailer(['mailType' => 'html'])
            ->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '');
        $email->setTo($emailAddress);
        $email->setSubject('Secure sign-in link for FKMP Smart Lab');
        $email->setMessage(view(
            setting('Auth.views')['magic-link-email'],
            [
                'token'      => $token,
                'user'       => $user,
                'ipAddress'  => $ipAddress,
                'userAgent'  => $userAgent,
                'date'       => $date,
                'expiresIn'  => (int) ceil(setting('Auth.magicLinkLifetime') / MINUTE),
            ],
            ['debug' => false],
        ));

        if ($email->send(false) === false) {
            log_message('error', 'Unable to send account recovery email for user {id}: {debug}', [
                'id'    => $user->id,
                'debug' => $email->printDebugger(['headers']),
            ]);

            return false;
        }

        $email->clear();

        return true;
    }
}
