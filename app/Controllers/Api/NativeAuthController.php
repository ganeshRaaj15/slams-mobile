<?php

namespace App\Controllers\Api;

use App\Authentication\OtpPolicy;
use App\Controllers\BaseController;
use App\Libraries\NativeUserSerializer;
use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Exceptions\ValidationException;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Validation\ValidationRules;
use Random\RandomException;

class NativeAuthController extends BaseController
{
    protected NativeUserSerializer $serializer;

    public function __construct()
    {
        $this->serializer = new NativeUserSerializer();
    }

    public function token()
    {
        $payload = $this->requestPayload();

        $rules = [
            'email' => config('Auth')->emailValidationRules,
            'password' => [
                'label' => 'Auth.password',
                'rules' => 'required',
            ],
            'device_name' => [
                'label' => 'Device Name',
                'rules' => 'required|string|max_length[255]',
            ],
        ];

        if (! $this->validateData($payload, $rules, [], config('Auth')->DBGroup)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid login payload.',
                    'errors' => $this->validator->getErrors(),
                ]);
        }

        $credentials = [
            'email' => trim((string) ($payload['email'] ?? '')),
            'password' => (string) ($payload['password'] ?? ''),
        ];

        $result = auth('session')->check($credentials);
        if (! $result->isOK()) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => $result->reason(),
                ]);
        }

        $user = $result->extraInfo();
        if (! $user instanceof User) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Authenticated user could not be resolved.',
                ]);
        }

        if ((new OtpPolicy())->requiresOtp($user)) {
            return $this->issueOtpChallenge($user, trim((string) $payload['device_name']));
        }

        $token = $user->generateAccessToken(trim((string) $payload['device_name']));

        return $this->response->setJSON([
            'status' => 'success',
            'token' => $token->raw_token,
            'user' => $this->serializer->serialize($user),
        ]);
    }

    public function verifyOtp()
    {
        $payload = $this->requestPayload();

        $rules = [
            'otp_token' => 'required|string|max_length[64]',
            'otp_code'  => 'required|string|min_length[6]|max_length[6]',
            'device_name' => [
                'label' => 'Device Name',
                'rules' => 'required|string|max_length[255]',
            ],
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid OTP payload.',
                    'errors' => $this->validator->getErrors(),
                ]);
        }

        $db = db_connect();
        $otpToken = trim((string) ($payload['otp_token'] ?? ''));
        $otpCode  = trim((string) ($payload['otp_code'] ?? ''));

        $record = $db->table('native_otp_tokens')
            ->where('otp_token', $otpToken)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->get()
            ->getRowArray();

        if (! $record) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid or expired OTP session.',
                ]);
        }

        if (! password_verify($otpCode, (string) $record['otp_hash'])) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Incorrect OTP code.',
                ]);
        }

        $db->table('native_otp_tokens')->where('id', $record['id'])->delete();

        $users = $this->userProvider();
        $user  = $users->findById((int) $record['user_id']);

        if (! $user instanceof User) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'User could not be resolved.',
                ]);
        }

        $deviceName = trim((string) ($payload['device_name'] ?? $record['device_name'] ?? 'mobile'));
        $token = $user->generateAccessToken($deviceName);

        return $this->response->setJSON([
            'status' => 'success',
            'token'  => $token->raw_token,
            'user'   => $this->serializer->serialize($user),
        ]);
    }

    public function register()
    {
        if (! setting('Auth.allowRegistration')) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'status' => 'error',
                    'message' => lang('Auth.registerDisabled'),
                ]);
        }

        $payload = $this->requestPayload();
        $registrationRules = $this->registrationRules();
        $rules = array_merge($registrationRules, [
            'device_name' => [
                'label' => 'Device Name',
                'rules' => 'required|string|max_length[255]',
            ],
        ]);

        if (! $this->validateData($payload, $rules, [], config('Auth')->DBGroup)) {
            $errors = $this->validator->getErrors();

            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => $this->firstErrorMessage($errors, 'Invalid registration payload.'),
                    'errors' => $errors,
                ]);
        }

        $users = $this->userProvider();
        $allowedFields = array_keys($registrationRules);
        $userData = array_intersect_key($payload, array_flip($allowedFields));
        $user = $users->createNewUser($userData);

        if ($user->username === null) {
            $user->username = null;
        }

        try {
            $users->save($user);
        } catch (ValidationException) {
            $errors = $users->errors();

            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => $this->firstErrorMessage($errors, 'Registration failed.'),
                    'errors' => $errors,
                ]);
        }

        $user = $users->findById($users->getInsertID());
        if (! $user instanceof User) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Registered user could not be resolved.',
                ]);
        }

        $users->addToDefaultGroup($user);
        Events::trigger('register', $user);
        $user->activate();

        $user = $users->withGroups()->findById($user->id) ?? $user;
        $token = $user->generateAccessToken(trim((string) $payload['device_name']));

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'status' => 'success',
                'message' => 'Account created successfully.',
                'token' => $token->raw_token,
                'user' => $this->serializer->serialize($user),
            ]);
    }

    public function me()
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
        ]);
    }

    public function logout()
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

        $rawToken = $this->bearerToken();
        if ($rawToken === null || $rawToken === '') {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Missing bearer token.',
                ]);
        }

        $user->revokeAccessToken($rawToken);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Device token revoked.',
        ]);
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

    protected function bearerToken(): ?string
    {
        $header = $this->request->getHeaderLine('Authorization');
        if ($header === '') {
            return null;
        }

        if (stripos($header, 'Bearer ') !== 0) {
            return null;
        }

        return trim(substr($header, 7));
    }

    protected function userProvider(): UserModel
    {
        $provider = model(setting('Auth.userProvider'));

        assert($provider instanceof UserModel, 'Config Auth.userProvider is not a valid UserProvider.');

        return $provider;
    }

    /**
     * @return array<string, array<string, list<string>|string>>
     */
    protected function registrationRules(): array
    {
        return (new ValidationRules())->getRegistrationRules();
    }

    /**
     * @param array<string, string> $errors
     */
    protected function firstErrorMessage(array $errors, string $fallback): string
    {
        foreach ($errors as $message) {
            if (is_string($message) && trim($message) !== '') {
                return trim($message);
            }
        }

        return $fallback;
    }

    /**
     * @throws RandomException
     */
    protected function issueOtpChallenge(User $user, string $deviceName): \CodeIgniter\HTTP\ResponseInterface
    {
        $otp     = (string) random_int(100000, 999999);
        $token   = bin2hex(random_bytes(24));
        $expires = date('Y-m-d H:i:s', time() + 600);

        $db = db_connect();

        $db->table('native_otp_tokens')->where('user_id', $user->id)->delete();

        $db->table('native_otp_tokens')->insert([
            'user_id'     => $user->id,
            'otp_token'   => $token,
            'otp_hash'    => password_hash($otp, PASSWORD_DEFAULT),
            'device_name' => $deviceName,
            'expires_at'  => $expires,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $identity = $db->table('auth_identities')
            ->where('user_id', $user->id)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();

        $email = $identity['secret'] ?? '';

        if ($email !== '') {
            try {
                $mailer = service('email');
                $mailer->clear(true);
                $mailer->setTo($email);
                $mailer->setSubject('Your SLAMS login verification code');
                $mailer->setMessage(
                    '<p>Hi ' . esc($user->username ?? 'there') . ',</p>'
                    . '<p>Your one-time login code is:</p>'
                    . '<h2 style="letter-spacing:0.3em">' . $otp . '</h2>'
                    . '<p>This code expires in 10 minutes. Do not share it with anyone.</p>'
                );
                $mailer->send();
            } catch (\Throwable $e) {
                log_message('error', '[NativeAuthController] OTP email failed: ' . $e->getMessage());
            }
        }

        return $this->response->setJSON([
            'status'    => 'otp_required',
            'otp_token' => $token,
            'message'   => 'A verification code has been sent to your email address.',
        ]);
    }
}
