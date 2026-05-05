<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Models\NotificationModel;

class NotificationController extends BaseController
{
    protected NotificationModel $notificationModel;

    public function __construct()
    {
        helper('auth');
        $this->notificationModel = new NotificationModel();
    }

    public function index()
    {
        if (! auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $user = auth()->user();
        $notifications = $this->notificationModel
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'DESC')
            ->paginate(12);

        $unreadCount = $this->notificationModel
            ->where('user_id', $user->id)
            ->where('is_read', 0)
            ->countAllResults();

        return view('dashboard/notifications/index', [
            'title' => 'Notifications | FKMP Smart Lab',
            'page' => 'Notifications',
            'layout' => $this->resolveLayout($user),
            'user' => $user,
            'notifications' => $notifications,
            'pager' => $this->notificationModel->pager,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markRead(int $id)
    {
        if (! auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $user = auth()->user();
        $notification = $this->notificationModel->where('id', $id)->where('user_id', $user->id)->first();
        if ($notification) {
            $this->notificationModel->update($id, ['is_read' => 1]);
            $target = ! empty($notification['link']) ? $notification['link'] : '/dashboard/notifications';
            return redirect()->to($target);
        }

        return redirect()->to('/dashboard/notifications')->with('error', 'Notification not found.');
    }

    public function markAllRead()
    {
        if (! auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $user = auth()->user();
        $this->notificationModel
            ->where('user_id', $user->id)
            ->where('is_read', 0)
            ->set(['is_read' => 1, 'updated_at' => date('Y-m-d H:i:s')])
            ->update();

        return redirect()->to('/dashboard/notifications')->with('success', 'All notifications marked as read.');
    }

    protected function resolveLayout($user): string
    {
        if ($user->inGroup('admin')) {
            return 'layouts/main_admin';
        }

        if ($user->inGroup('technician')) {
            return 'layouts/main_technician';
        }

        return 'layouts/main_user';
    }
}
