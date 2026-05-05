<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Libraries\WebPushService;

class PushSubscriptionController extends BaseController
{
    private WebPushService $webPushService;

    public function __construct()
    {
        helper('auth');
        $this->webPushService = new WebPushService();
    }

    public function subscribe()
    {
        if (! auth()->loggedIn()) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'You must be signed in to enable push notifications.',
            ]);
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        try {
            $this->webPushService->syncSubscription(
                (int) auth()->id(),
                $payload,
                $this->request->getUserAgent()?->getAgentString()
            );
        } catch (\InvalidArgumentException $e) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        } catch (\RuntimeException $e) {
            return $this->response->setStatusCode(503)->setJSON([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Push subscription sync failed: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Push notifications could not be enabled right now.',
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Push notifications are enabled for this device.',
        ]);
    }

    public function unsubscribe()
    {
        if (! auth()->loggedIn()) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'You must be signed in to disable push notifications.',
            ]);
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $endpoint = trim((string) ($payload['endpoint'] ?? ''));
        if ($endpoint === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Push endpoint is required to remove this device.',
            ]);
        }

        $this->webPushService->unsubscribe((int) auth()->id(), $endpoint);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Push notifications have been disabled for this device.',
        ]);
    }

    public function test()
    {
        if (! auth()->loggedIn()) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'You must be signed in to send a test push notification.',
            ]);
        }

        if (! $this->webPushService->isConfigured()) {
            return $this->response->setStatusCode(503)->setJSON([
                'status' => 'error',
                'message' => 'Web push is not configured for this environment.',
            ]);
        }

        try {
            $this->webPushService->sendTestNotification((int) auth()->id());
        } catch (\Throwable $e) {
            log_message('error', 'Push test notification failed: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'The test push notification could not be sent.',
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'A test push notification was queued for this device.',
        ]);
    }
}
