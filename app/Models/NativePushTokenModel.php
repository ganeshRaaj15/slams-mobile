<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\I18n\Time;

class NativePushTokenModel extends Model
{
    private ?bool $supportsAccessTokenBinding = null;

    protected $table = 'native_push_tokens';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'user_id',
        'access_token_id',
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

    public function upsertForUser(int $userId, string $expoPushToken, string $platform = 'unknown', ?string $deviceName = null, ?int $accessTokenId = null): void
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

        if ($this->supportsAccessTokenBinding()) {
            $data['access_token_id'] = $accessTokenId > 0 ? $accessTokenId : null;
        }

        $this->deactivateSupersededDeviceRegistrations(
            $userId,
            $expoPushToken,
            (string) $data['platform'],
            $data['device_name']
        );

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

        $rows = $this->activeBuilder()
            ->whereIn($this->table . '.user_id', $userIds)
            ->get()
            ->getResultArray();

        return $this->collapseRowsByDevice($rows);
    }

    public function activeCountForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $rows = $this->activeBuilder()
            ->where($this->table . '.user_id', $userId)
            ->get()
            ->getResultArray();

        return count($this->collapseRowsByDevice($rows));
    }

    public function listForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = $this->where('user_id', $userId)->findAll();

        return $this->collapseRowsByDevice($rows);
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
            'last_error_message' => 'This registration was signed out or push was disabled in the app.',
        ]);

        if ($this->supportsAccessTokenBinding()) {
            $builder->set([
                'access_token_id' => null,
            ]);
        }

        $builder->update();
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

        $builder = $this->where('expo_push_token', $expoPushToken)
            ->set([
                'is_active' => $deactivate ? 0 : 1,
                'last_error_at' => date('Y-m-d H:i:s'),
                'last_error_message' => trim($reason) !== '' ? trim($reason) : 'Unknown Expo push delivery failure.',
            ]);

        if ($deactivate && $this->supportsAccessTokenBinding()) {
            $builder->set([
                'access_token_id' => null,
            ]);
        }

        $builder->update();
    }

    protected function activeBuilder()
    {
        $builder = $this->builder();
        $builder->select($this->table . '.*');
        $builder->where($this->table . '.is_active', 1);

        if ($this->supportsAccessTokenBinding()) {
            $identitiesTable = config('Auth')->tables['identities'] ?? 'auth_identities';
            $builder->join(
                $identitiesTable . ' access_tokens',
                'access_tokens.id = ' . $this->table . '.access_token_id AND access_tokens.type = \'access_token\'',
                'inner'
            );
            $builder->where($this->table . '.access_token_id IS NOT NULL', null, false);
            $builder->groupStart()
                ->where('access_tokens.expires', null)
                ->orWhere('access_tokens.expires >=', Time::now()->toDateTimeString())
                ->groupEnd();

            $unusedLifetime = (int) (config('AuthToken')->unusedTokenLifetime ?? 0);
            if ($unusedLifetime > 0) {
                $unusedCutoff = Time::now()->subSeconds($unusedLifetime)->toDateTimeString();
                $builder->groupStart()
                    ->where('access_tokens.last_used_at', null)
                    ->orWhere('access_tokens.last_used_at >=', $unusedCutoff)
                    ->groupEnd();
            }
        }

        return $builder;
    }

    private function supportsAccessTokenBinding(): bool
    {
        if ($this->supportsAccessTokenBinding !== null) {
            return $this->supportsAccessTokenBinding;
        }

        $this->supportsAccessTokenBinding = $this->db->fieldExists('access_token_id', $this->table);

        return $this->supportsAccessTokenBinding;
    }

    private function deactivateSupersededDeviceRegistrations(
        int $userId,
        string $currentExpoPushToken,
        string $platform,
        ?string $deviceName
    ): void {
        $normalizedDeviceName = $this->normalizeDeviceName((string) $deviceName);
        if ($userId <= 0 || $normalizedDeviceName === '') {
            return;
        }

        $rows = $this->where('user_id', $userId)
            ->where('platform', $platform !== '' ? $platform : 'unknown')
            ->findAll();

        $idsToDeactivate = [];
        foreach ($rows as $row) {
            if (trim((string) ($row['expo_push_token'] ?? '')) === $currentExpoPushToken) {
                continue;
            }

            if ($this->normalizeDeviceName((string) ($row['device_name'] ?? '')) !== $normalizedDeviceName) {
                continue;
            }

            $idsToDeactivate[] = (int) ($row['id'] ?? 0);
        }

        $idsToDeactivate = array_values(array_filter($idsToDeactivate));
        if ($idsToDeactivate === []) {
            return;
        }

        $builder = $this->builder()
            ->whereIn('id', $idsToDeactivate)
            ->set([
                'is_active' => 0,
                'last_error_at' => date('Y-m-d H:i:s'),
                'last_error_message' => 'This older registration was replaced by the latest one for this device.',
            ]);

        if ($this->supportsAccessTokenBinding()) {
            $builder->set([
                'access_token_id' => null,
            ]);
        }

        $builder->update();
    }

    private function collapseRowsByDevice(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $key = $this->deviceGroupKey($row);
            $current = $grouped[$key] ?? null;

            if ($current === null || $this->shouldPreferRow($row, $current)) {
                $grouped[$key] = $row;
            }
        }

        $devices = array_values($grouped);
        usort($devices, fn(array $left, array $right): int => $this->compareRows($left, $right));

        return $devices;
    }

    private function deviceGroupKey(array $row): string
    {
        $userId = (int) ($row['user_id'] ?? 0);
        $platform = strtolower(trim((string) ($row['platform'] ?? 'unknown')));
        $deviceName = $this->normalizeDeviceName((string) ($row['device_name'] ?? ''));

        if ($deviceName !== '') {
            return $userId . '|' . $platform . '|' . $deviceName;
        }

        $expoPushToken = trim((string) ($row['expo_push_token'] ?? ''));
        if ($expoPushToken !== '') {
            return $userId . '|token|' . $expoPushToken;
        }

        return $userId . '|id|' . (string) ($row['id'] ?? '0');
    }

    private function normalizeDeviceName(string $deviceName): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $deviceName) ?? ''));
    }

    private function shouldPreferRow(array $candidate, array $current): bool
    {
        return $this->compareRows($candidate, $current) < 0;
    }

    private function compareRows(array $left, array $right): int
    {
        $leftActive = (bool) ($left['is_active'] ?? false);
        $rightActive = (bool) ($right['is_active'] ?? false);

        if ($leftActive !== $rightActive) {
            return $leftActive ? -1 : 1;
        }

        $leftTimestamp = $this->rowTimestamp($left);
        $rightTimestamp = $this->rowTimestamp($right);
        if ($leftTimestamp !== $rightTimestamp) {
            return $leftTimestamp > $rightTimestamp ? -1 : 1;
        }

        $leftId = (int) ($left['id'] ?? 0);
        $rightId = (int) ($right['id'] ?? 0);

        if ($leftId === $rightId) {
            return 0;
        }

        return $leftId > $rightId ? -1 : 1;
    }

    private function rowTimestamp(array $row): int
    {
        foreach (['updated_at', 'last_used_at', 'created_at', 'last_error_at'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value === '') {
                continue;
            }

            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        return 0;
    }
}
