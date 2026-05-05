<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\NotificationModel;
use CodeIgniter\Shield\Entities\User;

class NativeNotificationController extends BaseController
{
    protected NotificationModel $notificationModel;

    public function __construct()
    {
        helper('auth');
        $this->notificationModel = new NotificationModel();
    }

    public function index()
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

        $limit = min(max((int) $this->request->getGet('limit'), 1), 50);
        $notifications = $this->notificationModel
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'DESC')
            ->findAll($limit);

        $unreadCount = (int) $this->notificationModel
            ->where('user_id', $user->id)
            ->where('is_read', 0)
            ->countAllResults();

        return $this->response->setJSON([
            'status' => 'success',
            'unread_count' => $unreadCount,
            'notifications' => array_map(static function (array $notification): array {
                return [
                    'id' => (int) $notification['id'],
                    'type' => (string) ($notification['type'] ?? ''),
                    'title' => (string) ($notification['title'] ?? ''),
                    'message' => (string) ($notification['message'] ?? ''),
                    'link' => (string) ($notification['link'] ?? ''),
                    'entity_type' => (string) ($notification['entity_type'] ?? ''),
                    'entity_id' => isset($notification['entity_id']) ? (int) $notification['entity_id'] : null,
                    'is_read' => (bool) ($notification['is_read'] ?? false),
                    'created_at' => (string) ($notification['created_at'] ?? ''),
                    'updated_at' => (string) ($notification['updated_at'] ?? ''),
                ];
            }, $notifications),
        ]);
    }

    public function markRead(int $id)
    {
        return $this->markSingle($id, true);
    }

    public function markUnread(int $id)
    {
        return $this->markSingle($id, false);
    }

    public function markAllRead()
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

        $this->notificationModel
            ->where('user_id', $user->id)
            ->where('is_read', 0)
            ->set([
                'is_read' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ])
            ->update();

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'All notifications marked as read.',
        ]);
    }

    protected function markSingle(int $id, bool $read)
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

        $notification = $this->notificationModel
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $notification) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Notification not found.',
                ]);
        }

        $this->notificationModel->update($id, [
            'is_read' => $read ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => $read ? 'Notification marked as read.' : 'Notification marked as unread.',
        ]);
    }
}
