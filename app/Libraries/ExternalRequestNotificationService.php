<?php

namespace App\Libraries;

use App\Models\ExternalRequestModel;
use App\Models\NotificationModel;
use Config\Database;

class ExternalRequestNotificationService
{
    protected \CodeIgniter\Database\BaseConnection $db;
    protected ExternalRequestModel $requestModel;
    protected NotificationModel $notificationModel;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->requestModel = new ExternalRequestModel();
        $this->notificationModel = new NotificationModel();
    }

    public function notifySubmitted(int $requestId, bool $resubmitted = false): void
    {
        $context = $this->requestContext($requestId);
        if (! $context) {
            return;
        }

        $userIds = $this->reviewerUserIdsForLab((int) ($context['lab_id'] ?? 0));
        if ($userIds === []) {
            return;
        }

        $title = $resubmitted ? 'External Request Resubmitted' : 'New External Request';
        $message = ($context['contact_name'] ?? 'An external contact') . ' submitted a request';
        if (! empty($context['lab_name'])) {
            $message .= ' for ' . $context['lab_name'];
        }
        $message .= '.';

        $this->createNotifications($userIds, $title, $message, '/dashboard/external-requests/' . $requestId, $requestId);
    }

    public function notifyStatusUpdated(int $requestId): void
    {
        $context = $this->requestContext($requestId);
        if (! $context) {
            return;
        }

        $userId = (int) ($context['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $title = 'External Request Updated';
        $message = 'Your external request';
        if (! empty($context['lab_name'])) {
            $message .= ' for ' . $context['lab_name'];
        }
        $message .= ' is now ' . $this->requestModel->statusLabel((string) ($context['status'] ?? 'submitted')) . '.';

        $this->createNotifications([$userId], $title, $message, '/dashboard/external', $requestId);
    }

    protected function requestContext(int $requestId): ?array
    {
        if ($requestId <= 0) {
            return null;
        }

        return $this->db->table('external_requests er')
            ->select('er.*, l.name AS lab_name, l.pic_email')
            ->join('laboratories l', 'l.id = er.lab_id', 'left')
            ->where('er.id', $requestId)
            ->get()
            ->getRowArray();
    }

    protected function reviewerUserIdsForLab(int $labId): array
    {
        $userIds = [];

        if ($labId > 0) {
            $row = $this->db->table('laboratories')
            ->select('pic_email')
            ->where('id', $labId)
            ->get()
            ->getRowArray();

            $email = strtolower(trim((string) ($row['pic_email'] ?? '')));
            if ($email !== '') {
                $identity = $this->db->table('auth_identities')
                    ->select('user_id')
                    ->where('type', 'email_password')
                    ->where('LOWER(secret) =', $email)
                    ->get()
                    ->getRowArray();

                $userId = (int) ($identity['user_id'] ?? 0);
                if ($userId > 0) {
                    $userIds[] = $userId;
                }
            }
        }

        $reviewers = $this->db->table('auth_groups_users')
            ->select('user_id')
            ->whereIn('group', ['manager', 'admin'])
            ->get()
            ->getResultArray();

        foreach ($reviewers as $reviewer) {
            $reviewerId = (int) ($reviewer['user_id'] ?? 0);
            if ($reviewerId > 0) {
                $userIds[] = $reviewerId;
            }
        }

        return array_values(array_unique($userIds));
    }

    protected function createNotifications(array $userIds, string $title, string $message, string $link, int $requestId): void
    {
        $rows = [];
        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            if ($userId <= 0) {
                continue;
            }

            $rows[] = [
                'user_id' => $userId,
                'type' => 'external_request',
                'title' => $title,
                'message' => $message,
                'link' => $link,
                'entity_type' => 'external_request',
                'entity_id' => $requestId,
                'is_read' => 0,
            ];
        }

        if ($rows !== []) {
            (new UserNotificationDispatcher($this->notificationModel))->dispatch($rows);
        }
    }
}
