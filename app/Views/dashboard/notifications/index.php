<?= $this->extend($layout ?? 'layouts/main_user') ?>
<?= $this->section('content') ?>

<div class="container-fluid">
    <?php if (session()->getFlashdata('error')): ?><div class="alert alert-danger border-0 shadow-sm"><?= esc(session()->getFlashdata('error')) ?></div><?php endif; ?>
    <?php if (session()->getFlashdata('success')): ?><div class="alert alert-success border-0 shadow-sm"><?= esc(session()->getFlashdata('success')) ?></div><?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h3 class="mb-1">Notifications</h3>
            <p class="text-muted mb-0">Track booking approvals, maintenance updates, and system alerts in one place.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge rounded-pill bg-primary-subtle text-primary border px-3 py-2"><?= esc((int) $unreadCount) ?> unread</span>
            <form action="/dashboard/notifications/mark-all-read" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-primary">Mark All As Read</button>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($notifications)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-bell fs-1 d-block mb-3"></i>
                    <div class="fw-semibold mb-1">No notifications yet</div>
                    <div class="small">Notifications will appear here when bookings or maintenance records are updated.</div>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notification): ?>
                        <?php $isUnread = (int) ($notification['is_read'] ?? 0) === 0; ?>
                        <div class="list-group-item px-4 py-4 <?= $isUnread ? 'bg-light' : '' ?>">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="badge rounded-pill <?= $isUnread ? 'bg-primary' : 'bg-secondary' ?>"><?= $isUnread ? 'Unread' : 'Read' ?></span>
                                        <span class="badge rounded-pill bg-light text-dark border text-capitalize"><?= esc($notification['type'] ?? 'notice') ?></span>
                                    </div>
                                    <div class="fw-semibold text-dark mb-1"><?= esc($notification['title'] ?? 'Notification') ?></div>
                                    <div class="text-muted small mb-2"><?= esc($notification['message'] ?? '') ?></div>
                                    <div class="small text-muted">
                                        <i class="bi bi-clock me-1"></i>
                                        <?= esc(! empty($notification['created_at']) ? date('d M Y H:i', strtotime($notification['created_at'])) : '-') ?>
                                    </div>
                                </div>
                                <div class="d-flex align-items-start gap-2">
                                    <?php if ($isUnread): ?>
                                        <form action="/dashboard/notifications/read/<?= esc($notification['id']) ?>" method="post">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-outline-primary btn-sm">Open</button>
                                        </form>
                                    <?php elseif (! empty($notification['link'])): ?>
                                        <a href="<?= esc($notification['link']) ?>" class="btn btn-outline-secondary btn-sm">Open</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="p-3 border-top">
                    <?= $pager->links() ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
