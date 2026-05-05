<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Libraries\AccountRecoveryService;
use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\LoginModel;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;

class PasswordRecoveryController extends BaseController
{
    private UserModel $users;

    public function __construct()
    {
        /** @var class-string<UserModel> $providerClass */
        $providerClass = setting('Auth.userProvider');
        $this->users = new $providerClass();
    }

    public function loginView(): RedirectResponse|string
    {
        if (! setting('Auth.allowMagicLinkLogins')) {
            return redirect()->route('login')->with('error', lang('Auth.magicLinkDisabled'));
        }

        if (auth()->loggedIn()) {
            return redirect()->to(config('Auth')->loginRedirect());
        }

        return view(setting('Auth.views')['magic-link-login']);
    }

    public function loginAction(): RedirectResponse|string
    {
        if (! setting('Auth.allowMagicLinkLogins')) {
            return redirect()->route('login')->with('error', lang('Auth.magicLinkDisabled'));
        }

        $account = trim((string) $this->request->getPost('account'));
        if ($account === '' || strlen($account) > 254) {
            return redirect()->route('magic-link')
                ->withInput()
                ->with('errors', ['Enter the email address or username on your account.']);
        }

        $user = $this->findUserByAccount($account);
        if ($user instanceof User && $this->canReceiveRecoveryLink($user)) {
            (new AccountRecoveryService())->sendLoginLink($user);
        }

        return $this->displayMessage();
    }

    public function verify(): RedirectResponse
    {
        if (! setting('Auth.allowMagicLinkLogins')) {
            return redirect()->route('login')->with('error', lang('Auth.magicLinkDisabled'));
        }

        $token = trim((string) $this->request->getGet('token'));
        $identifier = $this->identifierForToken($token);

        /** @var UserIdentityModel $identityModel */
        $identityModel = model(UserIdentityModel::class);
        $identity = $token !== ''
            ? $identityModel->getIdentityBySecret(Session::ID_TYPE_MAGIC_LINK, hash('sha256', $token))
            : null;

        if ($identity === null) {
            $this->recordLoginAttempt($identifier, false);
            Events::trigger('failedLogin', ['magicLinkToken' => $identifier]);

            return redirect()->route('magic-link')->with('error', 'That sign-in link is invalid or has already been used.');
        }

        $identityModel->delete($identity->id);

        if ($identity->expires === null || Time::now()->isAfter($identity->expires)) {
            $this->recordLoginAttempt($identifier, false);
            Events::trigger('failedLogin', ['magicLinkToken' => $identifier]);

            return redirect()->route('magic-link')->with('error', 'That sign-in link has expired. Request a new one.');
        }

        $user = $this->users->findById($identity->user_id);
        if (! $user instanceof User || ! $this->canReceiveRecoveryLink($user)) {
            $this->recordLoginAttempt($identifier, false, $identity->user_id);
            Events::trigger('failedLogin', ['magicLinkToken' => $identifier]);

            return redirect()->route('magic-link')->with('error', 'That sign-in link cannot be used. Request a new one or contact an administrator.');
        }

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();
        if ($authenticator->hasAction($identity->user_id)) {
            return redirect()->route('auth-action-show')->with('error', lang('Auth.needActivate'));
        }

        $authenticator->loginById($identity->user_id);
        $loggedInUser = $authenticator->getUser();

        $this->recordLoginAttempt($identifier, true, $identity->user_id);

        session()->setTempdata('magicLogin', true, 300);
        Events::trigger('magicLogin');

        if ($loggedInUser instanceof User && ! $loggedInUser->inGroup('pic', 'manager')) {
            return redirect()->to(site_url('dashboard/profile'))
                ->with('message', 'You are signed in. You can set a new password from your profile.');
        }

        return redirect()->to(config('Auth')->loginRedirect())->with('message', 'You are signed in.');
    }

    private function displayMessage(): string
    {
        return view(setting('Auth.views')['magic-link-message']);
    }

    private function findUserByAccount(string $account): ?User
    {
        $account = trim($account);
        $accountLower = strtolower($account);

        if (filter_var($account, FILTER_VALIDATE_EMAIL)) {
            return $this->users->findByCredentials(['email' => $accountLower]);
        }

        if (! preg_match('/\A[a-zA-Z0-9\.]{3,30}\z/', $account)) {
            return null;
        }

        $db = db_connect();
        $row = $db->table('users')
            ->select('id')
            ->where('LOWER(username) =', $accountLower)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return $row ? $this->users->findById((int) $row['id']) : null;
    }

    private function canReceiveRecoveryLink(User $user): bool
    {
        if ($user->isBanned()) {
            return false;
        }

        return (int) ($user->active ?? 0) === 1;
    }

    private function identifierForToken(string $token): string
    {
        return $token === '' ? 'missing-token' : 'sha256:' . hash('sha256', $token);
    }

    /**
     * @param int|string|null $userId
     */
    private function recordLoginAttempt(string $identifier, bool $success, $userId = null): void
    {
        /** @var LoginModel $loginModel */
        $loginModel = model(LoginModel::class);
        $loginModel->recordLoginAttempt(
            Session::ID_TYPE_MAGIC_LINK,
            $identifier,
            $success,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent(),
            $userId,
        );
    }
}
