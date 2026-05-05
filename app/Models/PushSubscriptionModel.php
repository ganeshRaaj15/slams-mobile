<?php

namespace App\Models;

use CodeIgniter\Model;

class PushSubscriptionModel extends Model
{
    protected $table = 'push_subscriptions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'user_id',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_agent',
        'is_active',
        'last_used_at',
        'last_error_at',
        'last_error_message',
        'created_at',
        'updated_at',
    ];

    public function upsertForUser(int $userId, array $subscription, ?string $userAgent = null): void
    {
        $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
        if ($userId <= 0 || $endpoint === '') {
            return;
        }

        $data = [
            'user_id' => $userId,
            'endpoint' => $endpoint,
            'public_key' => $subscription['keys']['p256dh'] ?? $subscription['publicKey'] ?? null,
            'auth_token' => $subscription['keys']['auth'] ?? $subscription['authToken'] ?? null,
            'content_encoding' => $subscription['contentEncoding'] ?? 'aes128gcm',
            'user_agent' => $userAgent !== null && trim($userAgent) !== '' ? trim($userAgent) : null,
            'is_active' => 1,
            'last_error_at' => null,
            'last_error_message' => null,
        ];

        $existing = $this->where('endpoint', $endpoint)->first();
        if ($existing) {
            $this->update((int) $existing['id'], $data);
            return;
        }

        $this->insert($data);
    }

    public function activeForUsers(array $userIds): array
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0));
        if ($userIds === []) {
            return [];
        }

        return $this->whereIn('user_id', $userIds)
            ->where('is_active', 1)
            ->findAll();
    }

    public function deactivateEndpoint(string $endpoint, ?string $reason = null): void
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return;
        }

        $this->where('endpoint', $endpoint)
            ->set([
                'is_active' => 0,
                'last_error_at' => date('Y-m-d H:i:s'),
                'last_error_message' => $reason !== null && trim($reason) !== '' ? trim($reason) : 'Subscription expired or was removed by the push provider.',
            ])
            ->update();
    }

    public function removeForUser(int $userId, string $endpoint): void
    {
        $endpoint = trim($endpoint);
        if ($userId <= 0 || $endpoint === '') {
            return;
        }

        $this->where('user_id', $userId)
            ->where('endpoint', $endpoint)
            ->delete();
    }

    public function markDeliverySuccess(string $endpoint): void
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return;
        }

        $this->where('endpoint', $endpoint)
            ->set([
                'is_active' => 1,
                'last_used_at' => date('Y-m-d H:i:s'),
                'last_error_at' => null,
                'last_error_message' => null,
            ])
            ->update();
    }

    public function markDeliveryFailure(string $endpoint, string $reason, bool $expired = false): void
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return;
        }

        $this->where('endpoint', $endpoint)
            ->set([
                'is_active' => $expired ? 0 : 1,
                'last_error_at' => date('Y-m-d H:i:s'),
                'last_error_message' => trim($reason) !== '' ? trim($reason) : 'Unknown push delivery failure.',
            ])
            ->update();
    }
}
