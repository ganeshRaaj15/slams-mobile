<?php

namespace App\Libraries;

use App\Models\PushSubscriptionModel;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    private WebPushConfiguration $configuration;
    private PushSubscriptionModel $subscriptionModel;

    public function __construct(?WebPushConfiguration $configuration = null, ?PushSubscriptionModel $subscriptionModel = null)
    {
        helper('url');
        OpenSslBootstrap::ensureConfig();

        $this->configuration = $configuration ?? new WebPushConfiguration();
        $this->subscriptionModel = $subscriptionModel ?? new PushSubscriptionModel();
    }

    public function isConfigured(): bool
    {
        return $this->configuration->isConfigured();
    }

    public function publicConfig(): array
    {
        return $this->configuration->clientConfig();
    }

    public function syncSubscription(int $userId, array $payload, ?string $userAgent = null): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Web push is not configured for this environment.');
        }

        $subscription = $this->validatedSubscription($payload);
        $this->subscriptionModel->upsertForUser($userId, $subscription, $userAgent);
    }

    public function unsubscribe(int $userId, string $endpoint): void
    {
        $this->subscriptionModel->removeForUser($userId, $endpoint);
    }

    public function sendTestNotification(int $userId): void
    {
        $this->notifyUsers([$userId], [
            'title' => 'SLAMS Push Enabled',
            'body' => 'This device can now receive booking, request, and maintenance alerts.',
            'url' => site_url('dashboard/notifications'),
            'tag' => 'slams-push-test',
            'type' => 'push_test',
        ]);
    }

    public function notifyUsers(array $userIds, array $payload): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $subscriptions = $this->subscriptionModel->activeForUsers($userIds);
        if ($subscriptions === []) {
            return;
        }

        $notificationPayload = json_encode($this->normalizePayload($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($notificationPayload === false) {
            log_message('error', 'Web push payload encoding failed.');
            return;
        }

        try {
            $webPush = new WebPush(
                $this->configuration->authOptions(),
                ['TTL' => $this->configuration->defaultTtl()],
                20
            );
            $webPush->setReuseVAPIDHeaders(true);
        } catch (\Throwable $e) {
            log_message('error', 'Web push service could not be initialized: ' . $e->getMessage());
            return;
        }

        foreach ($subscriptions as $subscriptionRow) {
            try {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscriptionRow['endpoint'],
                        'publicKey' => $subscriptionRow['public_key'],
                        'authToken' => $subscriptionRow['auth_token'],
                        'contentEncoding' => $subscriptionRow['content_encoding'] ?: 'aes128gcm',
                    ]),
                    $notificationPayload
                );
            } catch (\Throwable $e) {
                $this->subscriptionModel->markDeliveryFailure((string) $subscriptionRow['endpoint'], $e->getMessage(), false);
            }
        }

        foreach ($webPush->flush() as $report) {
            try {
                if ($report->isSuccess()) {
                    $this->subscriptionModel->markDeliverySuccess($report->getEndpoint());
                    continue;
                }

                $this->subscriptionModel->markDeliveryFailure(
                    $report->getEndpoint(),
                    $report->getReason(),
                    $report->isSubscriptionExpired()
                );
            } catch (\Throwable $e) {
                log_message('error', 'Web push delivery bookkeeping failed: ' . $e->getMessage());
            }
        }
    }

    private function validatedSubscription(array $payload): array
    {
        $endpoint = trim((string) ($payload['endpoint'] ?? ''));
        $publicKey = trim((string) ($payload['keys']['p256dh'] ?? $payload['publicKey'] ?? ''));
        $authToken = trim((string) ($payload['keys']['auth'] ?? $payload['authToken'] ?? ''));
        $contentEncoding = trim((string) ($payload['contentEncoding'] ?? 'aes128gcm'));

        if ($endpoint === '' || ! filter_var($endpoint, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('A valid push endpoint is required.');
        }

        if ($publicKey === '' || $authToken === '') {
            throw new \InvalidArgumentException('Push subscription keys are incomplete.');
        }

        if (! in_array($contentEncoding, ['aesgcm', 'aes128gcm'], true)) {
            $contentEncoding = 'aes128gcm';
        }

        return [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => $publicKey,
                'auth' => $authToken,
            ],
            'contentEncoding' => $contentEncoding,
        ];
    }

    private function normalizePayload(array $payload): array
    {
        $defaultUrl = site_url('dashboard/notifications');
        $iconUrl = base_url('icons/slams-mobile.svg');

        $body = trim((string) ($payload['body'] ?? ''));
        if (mb_strlen($body) > 220) {
            $body = mb_substr($body, 0, 217) . '...';
        }

        $url = trim((string) ($payload['url'] ?? ''));
        if ($url === '') {
            $url = $defaultUrl;
        } elseif (! preg_match('#^https?://#i', $url)) {
            $url = site_url(ltrim($url, '/'));
        }

        return [
            'title' => trim((string) ($payload['title'] ?? 'SLAMS Notification')) ?: 'SLAMS Notification',
            'body' => $body,
            'url' => $url,
            'tag' => trim((string) ($payload['tag'] ?? 'slams-notification')) ?: 'slams-notification',
            'type' => trim((string) ($payload['type'] ?? 'notice')) ?: 'notice',
            'icon' => $iconUrl,
            'badge' => $iconUrl,
            'requireInteraction' => (bool) ($payload['requireInteraction'] ?? false),
        ];
    }
}
