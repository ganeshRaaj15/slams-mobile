<?php

namespace App\Models;

use CodeIgniter\Model;

class NativePushTokenModel extends Model
{
    protected $table = 'native_push_tokens';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'user_id',
        'expo_push_token',
        'platform',
        'device_name',
        'is_active',
        'last_used_at',
        'last_error_at',
        'last_error_message',
        'created_at',
        'updated_at',
    ];

    public function upsertForUser(int $userId, string $expoPushToken, string $platform = 'unknown', ?string $deviceName = null): void
    {
        $expoPushToken = trim($expoPushToken);
        if ($userId <= 0 || $expoPushToken === '') {
            return;
        }

        $data = [
            'user_id' => $userId,
            'expo_push_token' => $expoPushToken,
            'platform' => $platform !== '' ? $platform : 'unknown',
            'device_name' => $deviceName !== null && trim($deviceName) !== '' ? trim($deviceName) : null,
            'is_active' => 1,
            'last_error_at' => null,
            'last_error_message' => null,
        ];

        $existing = $this->where('expo_push_token', $expoPushToken)->first();
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

    public function activeCountForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return (int) $this->where('user_id', $userId)
            ->where('is_active', 1)
            ->countAllResults();
    }

    public function deactivateForUser(int $userId, ?string $expoPushToken = null): void
    {
        if ($userId <= 0) {
            return;
        }

        $builder = $this->where('user_id', $userId);
        if ($expoPushToken !== null && trim($expoPushToken) !== '') {
            $builder->where('expo_push_token', trim($expoPushToken));
        }

        $builder->set([
            'is_active' => 0,
            'last_error_at' => date('Y-m-d H:i:s'),
            'last_error_message' => 'Signed out or notifications disabled from the native app.',
        ])->update();
    }

    public function markDeliverySuccess(string $expoPushToken): void
    {
        $expoPushToken = trim($expoPushToken);
        if ($expoPushToken === '') {
            return;
        }

        $this->where('expo_push_token', $expoPushToken)
            ->set([
                'is_active' => 1,
                'last_used_at' => date('Y-m-d H:i:s'),
                'last_error_at' => null,
                'last_error_message' => null,
            ])
            ->update();
    }

    public function markDeliveryFailure(string $expoPushToken, string $reason, bool $deactivate = false): void
    {
        $expoPushToken = trim($expoPushToken);
        if ($expoPushToken === '') {
            return;
        }

        $this->where('expo_push_token', $expoPushToken)
            ->set([
                'is_active' => $deactivate ? 0 : 1,
                'last_error_at' => date('Y-m-d H:i:s'),
                'last_error_message' => trim($reason) !== '' ? trim($reason) : 'Unknown Expo push delivery failure.',
            ])
            ->update();
    }
}
