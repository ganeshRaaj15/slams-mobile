<?php

namespace App\Libraries;

use App\Models\NativePushTokenModel;
use CodeIgniter\HTTP\CURLRequest;
use Config\Services;

class ExpoPushService
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    private NativePushTokenModel $tokenModel;
    private CURLRequest $http;

    public function __construct(?NativePushTokenModel $tokenModel = null, ?CURLRequest $http = null)
    {
        $this->tokenModel = $tokenModel ?? new NativePushTokenModel();
        $this->http = $http ?? Services::curlrequest([
            'timeout' => 10,
            'http_errors' => false,
        ]);
    }

    public function registerToken(int $userId, string $expoPushToken, string $platform = 'unknown', ?string $deviceName = null): void
    {
        $expoPushToken = trim($expoPushToken);
        if (! $this->isValidExpoPushToken($expoPushToken)) {
            throw new \InvalidArgumentException('A valid Expo push token is required.');
        }

        $this->tokenModel->upsertForUser($userId, $expoPushToken, $platform, $deviceName);
    }

    public function unregisterToken(int $userId, ?string $expoPushToken = null): void
    {
        $this->tokenModel->deactivateForUser($userId, $expoPushToken);
    }

    public function activeTokenCount(int $userId): int
    {
        return $this->tokenModel->activeCountForUser($userId);
    }

    public function notifyUsers(array $userIds, array $payload): void
    {
        $tokens = $this->tokenModel->activeForUsers($userIds);
        if ($tokens === []) {
            return;
        }

        foreach (array_chunk($tokens, 100) as $chunk) {
            $messages = array_map(fn(array $row): array => $this->messageForToken($row, $payload), $chunk);

            try {
                $response = $this->http->post(self::EXPO_PUSH_URL, [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Accept-Encoding' => 'gzip, deflate',
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode($messages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]);
            } catch (\Throwable $e) {
                foreach ($chunk as $row) {
                    $this->tokenModel->markDeliveryFailure((string) $row['expo_push_token'], $e->getMessage(), false);
                }
                log_message('error', 'Expo push transport failed: ' . $e->getMessage());
                continue;
            }

            $statusCode = $response->getStatusCode();
            $decoded = json_decode((string) $response->getBody(), true);
            $results = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];

            if ($statusCode >= 400 || $results === []) {
                $reason = is_array($decoded['errors'] ?? null)
                    ? json_encode($decoded['errors'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : 'Expo push service returned HTTP ' . $statusCode . '.';

                foreach ($chunk as $row) {
                    $this->tokenModel->markDeliveryFailure((string) $row['expo_push_token'], (string) $reason, false);
                }

                log_message('error', 'Expo push request failed: ' . (string) $reason);
                continue;
            }

            foreach ($chunk as $index => $row) {
                $result = $results[$index] ?? null;
                if (! is_array($result)) {
                    $this->tokenModel->markDeliveryFailure((string) $row['expo_push_token'], 'Expo push receipt was missing for this token.', false);
                    continue;
                }

                if (($result['status'] ?? '') === 'ok') {
                    $this->tokenModel->markDeliverySuccess((string) $row['expo_push_token']);
                    continue;
                }

                $errorCode = trim((string) ($result['details']['error'] ?? $result['message'] ?? 'Expo push delivery failed.'));
                $deactivate = in_array($errorCode, ['DeviceNotRegistered'], true);
                $this->tokenModel->markDeliveryFailure((string) $row['expo_push_token'], $errorCode, $deactivate);
            }
        }
    }

    private function messageForToken(array $row, array $payload): array
    {
        $body = trim((string) ($payload['body'] ?? ''));
        if (mb_strlen($body) > 220) {
            $body = mb_substr($body, 0, 217) . '...';
        }

        return [
            'to' => (string) $row['expo_push_token'],
            'title' => trim((string) ($payload['title'] ?? 'SLAMS Notification')) ?: 'SLAMS Notification',
            'body' => $body,
            'sound' => 'default',
            'priority' => 'high',
            'channelId' => 'default',
            'data' => [
                'url' => trim((string) ($payload['url'] ?? '')),
                'type' => trim((string) ($payload['type'] ?? 'notice')),
                'entityType' => trim((string) ($payload['entityType'] ?? '')),
                'entityId' => (int) ($payload['entityId'] ?? 0),
            ],
        ];
    }

    private function isValidExpoPushToken(string $expoPushToken): bool
    {
        if ($expoPushToken === '') {
            return false;
        }

        return preg_match('/^Expo(?:nent)?PushToken\\[[A-Za-z0-9_-]+\\]$/', $expoPushToken) === 1;
    }
}
