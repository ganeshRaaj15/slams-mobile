<?php

namespace App\Libraries;

use App\Models\NotificationModel;

class UserNotificationDispatcher
{
    private NotificationModel $notificationModel;
    private WebPushService $webPushService;
    private ExpoPushService $expoPushService;

    public function __construct(?NotificationModel $notificationModel = null, ?WebPushService $webPushService = null, ?ExpoPushService $expoPushService = null)
    {
        $this->notificationModel = $notificationModel ?? new NotificationModel();
        $this->webPushService = $webPushService ?? new WebPushService();
        $this->expoPushService = $expoPushService ?? new ExpoPushService();
    }

    public function dispatch(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        try {
            $this->notificationModel->insertBatch($rows);
        } catch (\Throwable $e) {
            log_message('error', 'Notification insert error: ' . $e->getMessage());
        }

        $grouped = [];
        foreach ($rows as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $key = md5(json_encode([
                $row['title'] ?? '',
                $row['message'] ?? '',
                $row['link'] ?? '',
                $row['type'] ?? '',
                $row['entity_type'] ?? '',
                $row['entity_id'] ?? 0,
            ]));

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'row' => $row,
                    'userIds' => [],
                ];
            }

            $grouped[$key]['userIds'][] = $userId;
        }

        foreach ($grouped as $group) {
            $row = $group['row'];
            $userIds = array_values(array_unique(array_map('intval', $group['userIds'])));

            try {
                $this->webPushService->notifyUsers($userIds, [
                    'title' => $row['title'] ?? 'SLAMS Notification',
                    'body' => $row['message'] ?? '',
                    'url' => $row['link'] ?? '/dashboard/notifications',
                    'tag' => $this->notificationTag($row),
                    'type' => $row['type'] ?? 'notice',
                    'entityType' => $row['entity_type'] ?? '',
                    'entityId' => $row['entity_id'] ?? 0,
                    'requireInteraction' => in_array((string) ($row['type'] ?? ''), ['booking', 'external_request'], true),
                ]);
            } catch (\Throwable $e) {
                log_message('error', 'Web push dispatch failed: ' . $e->getMessage());
            }

            try {
                $this->expoPushService->notifyUsers($userIds, [
                    'title' => $row['title'] ?? 'SLAMS Notification',
                    'body' => $row['message'] ?? '',
                    'url' => $row['link'] ?? '/dashboard/notifications',
                    'type' => $row['type'] ?? 'notice',
                    'entityType' => $row['entity_type'] ?? '',
                    'entityId' => $row['entity_id'] ?? 0,
                ]);
            } catch (\Throwable $e) {
                log_message('error', 'Native Expo push dispatch failed: ' . $e->getMessage());
            }
        }
    }

    private function notificationTag(array $row): string
    {
        $entityType = trim((string) ($row['entity_type'] ?? ''));
        $entityId = (int) ($row['entity_id'] ?? 0);
        if ($entityType !== '' && $entityId > 0) {
            return $entityType . '-' . $entityId;
        }

        return trim((string) ($row['type'] ?? 'slams-notification')) ?: 'slams-notification';
    }
}
