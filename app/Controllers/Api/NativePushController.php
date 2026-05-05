<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\ExpoPushService;
use CodeIgniter\Shield\Entities\User;

class NativePushController extends BaseController
{
    private ExpoPushService $expoPushService;

    public function __construct()
    {
        helper('auth');
        $this->expoPushService = new ExpoPushService();
    }

    public function show()
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->unauthenticated();
        }

        return $this->response->setJSON([
            'status' => 'success',
            'active_tokens' => $this->expoPushService->activeTokenCount($user->id),
        ]);
    }

    public function register()
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->unauthenticated();
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $expoPushToken = trim((string) ($payload['expo_push_token'] ?? ''));
        $deviceName = trim((string) ($payload['device_name'] ?? ''));
        $platform = trim((string) ($payload['platform'] ?? 'unknown'));

        try {
            $this->expoPushService->registerToken($user->id, $expoPushToken, $platform, $deviceName);
        } catch (\InvalidArgumentException $e) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Native push token registered successfully.',
            'active_tokens' => $this->expoPushService->activeTokenCount($user->id),
        ]);
    }

    public function unregister()
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->unauthenticated();
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $expoPushToken = trim((string) ($payload['expo_push_token'] ?? ''));
        $this->expoPushService->unregisterToken($user->id, $expoPushToken !== '' ? $expoPushToken : null);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Native push token deactivated.',
            'active_tokens' => $this->expoPushService->activeTokenCount($user->id),
        ]);
    }

    private function unauthenticated()
    {
        return $this->response
            ->setStatusCode(401)
            ->setJSON([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ]);
    }
}
