<?php
use App\Models\NotificationModel;

$navNotificationItems = [];
$navUnreadCount = 0;
if (function_exists('auth') && auth()->loggedIn()) {
    $navUser = auth()->user();
    $notificationModel = new NotificationModel();
    $navUnreadCount = $notificationModel->where('user_id', $navUser->id)->where('is_read', 0)->countAllResults();
    $navNotificationItems = $notificationModel->where('user_id', $navUser->id)->orderBy('created_at', 'DESC')->findAll(5);
}
?>

<nav class="admin-glass-navbar">
    <div class="navbar-content px-3">
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
            <div class="navbar-title">
                <h4><?= esc($page ?? 'Dashboard') ?></h4>
                <div class="breadcrumb-nav">
                    <span class="breadcrumb-item"><i class="bi bi-house-door"></i> Dashboard</span>
                    <?php if (isset($page) && $page !== 'Dashboard'): ?>
                        <span class="breadcrumb-divider"><i class="bi bi-chevron-right"></i></span>
                        <span class="breadcrumb-item active"><?= esc($page) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="navbar-actions">
            <?= $this->include('components/navbar_app_controls') ?>
            <?php if (isset($user) && $user): ?>
                <div class="dropdown">
                    <a href="#" class="notification-trigger" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                        <i class="bi bi-bell"></i>
                        <?php if ($navUnreadCount > 0): ?><span class="notification-badge"><?= esc($navUnreadCount > 99 ? '99+' : (string) $navUnreadCount) ?></span><?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end notification-menu p-0">
                        <div class="dropdown-header d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold text-dark">Notifications</div>
                                <div class="small text-muted"><?= esc((int) $navUnreadCount) ?> unread</div>
                            </div>
                            <a href="/dashboard/notifications" class="small text-decoration-none">View all</a>
                        </div>
                        <?php if (empty($navNotificationItems)): ?>
                            <div class="px-3 py-4 text-center text-muted small">No notifications yet.</div>
                        <?php else: ?>
                            <?php foreach ($navNotificationItems as $item): ?>
                                <a href="/dashboard/notifications" class="notification-item">
                                    <div class="d-flex align-items-start gap-2">
                                        <span class="badge <?= (int) ($item['is_read'] ?? 0) === 0 ? 'bg-primary' : 'bg-secondary' ?> mt-1"><?= (int) ($item['is_read'] ?? 0) === 0 ? 'New' : 'Read' ?></span>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold small text-dark"><?= esc($item['title'] ?? 'Notification') ?></div>
                                            <div class="small text-muted"><?= esc($item['message'] ?? '') ?></div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <a href="/dashboard/profile" class="user-profile-glass">
                    <div class="user-avatar"><i class="bi bi-person-circle"></i></div>
                    <div class="user-info">
                        <div class="user-name"><?= esc($user->full_name ?? $user->username ?? 'User') ?></div>
                        <div class="user-role"><?= esc($user->role ?? 'User') ?></div>
                    </div>
                </a>
            <?php endif; ?>

            <form action="/logout" method="post">
                <?= csrf_field() ?>
                <button class="btn-logout-glass" type="submit"><i class="bi bi-box-arrow-right"></i> Logout</button>
            </form>
        </div>
    </div>
</nav>
