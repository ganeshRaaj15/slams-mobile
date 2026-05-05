<?= $this->extend('layouts/main_admin') ?>

<?= $this->section('content') ?>

<?php
$filters = $filters ?? ['q' => '', 'role' => '', 'status' => '', 'per_page' => 10, 'page' => 1];
$pagination = $pagination ?? ['total' => count($users), 'page' => 1, 'per_page' => 10, 'page_count' => 1];
$stats = $stats ?? ['total' => count($users), 'active' => count(array_filter($users, fn($u) => $u['active']))];
$allRoles = $allRoles ?? [];
$baseQuery = [
    'q' => $filters['q'],
    'role' => $filters['role'],
    'status' => $filters['status'],
    'per_page' => $filters['per_page'],
];
$exportQuery = array_filter($baseQuery, static fn($value) => $value !== '' && $value !== null);
?>


<div class="user-management">
    <!-- PAGE HEADER -->
    <div class="dashboard-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
            <div>
                <h1>User Management</h1>
                <p>Manage system users, roles, and permissions</p>
            </div>
            <div class="d-flex gap-3">
                <div class="quick-stat">
                    <i class="bi bi-people-fill"></i>
                    <div>
                        <div class="small text-muted">Total Users</div>
                        <div class="fw-bold"><?= esc($stats['total']) ?></div>
                    </div>
                </div>
                <div class="quick-stat">
                    <i class="bi bi-person-check"></i>
                    <div>
                        <div class="small text-muted">Active</div>
                        <div class="fw-bold"><?= esc($stats['active']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTER AND ACTION BAR -->
    <div class="filter-bar mb-4">
        <form method="get" action="/admin/users" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted">Search</label>
                <input type="text" name="q" class="form-control" value="<?= esc($filters['q']) ?>" placeholder="Name, username, email, or phone">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Role</label>
                <select name="role" class="form-select">
                    <option value="">All roles</option>
                    <?php foreach ($allRoles as $role): ?>
                        <option value="<?= esc($role) ?>" <?= $filters['role'] === $role ? 'selected' : '' ?>><?= esc(ucfirst($role)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Rows</label>
                <select name="per_page" class="form-select">
                    <?php foreach ([10, 25, 50] as $perPage): ?>
                        <option value="<?= esc($perPage) ?>" <?= (int) $filters['per_page'] === $perPage ? 'selected' : '' ?>><?= esc($perPage) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="/admin/users" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            </div>
            <div class="col-12 text-md-end">
                <a href="/admin/users/create" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1"></i> Add New User
                </a>
            </div>
        </form>
    </div>

    <!-- FLASH MESSAGES -->
    <?php if (session()->getFlashdata('message')): ?>
        <div class="alert alert-success glass-card mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill fs-5 me-2"></i>
                <div class="flex-grow-1"><?= session()->getFlashdata('message') ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger glass-card mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                <div class="flex-grow-1"><?= session()->getFlashdata('error') ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- USERS TABLE -->
    <div class="glass-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="glass-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Roles</th>
                            <th>Status</th>
                            <th style="width: 100px;" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="bi bi-people"></i>
                                        <h4 class="text-muted mb-2">No Users Found</h4>
                                        <p class="text-muted">Start by adding your first user</p>
                                        <a href="/admin/users/create" class="btn btn-primary mt-2">
                                            <i class="bi bi-person-plus me-1"></i> Add User
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $i = 1; foreach ($users as $u): ?>
                                <tr class="user-row" data-username="<?= strtolower(esc($u['username'])) ?>" 
                                    data-email="<?= strtolower(esc($u['email'])) ?>" 
                                    data-roles="<?= strtolower(implode(',', $u['roles'])) ?>">
                                    <td class="text-muted fw-semibold"><?= $i++ ?></td>
                                    
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="icon-container bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bi bi-person fs-5 text-primary"></i>
                                            </div>
                                            <div>
                                                <div class="fw-semibold"><?= esc($u['username']) ?></div>
                                                <?php if (!empty($u['full_name'])): ?>
                                                    <small class="text-muted"><?= esc($u['full_name']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-envelope text-muted"></i>
                                            <span><?= esc($u['email']) ?></span>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php if (!empty($u['roles'])): ?>
                                                <?php foreach ($u['roles'] as $role): ?>
                                                    <span class="role-badge <?= $role ?>">
                                                        <i class="bi bi-person-badge me-1"></i>
                                                        <?= esc(ucfirst($role)) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No roles</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <?php if ($u['active']): ?>
                                            <span class="badge badge-active">
                                                <i class="bi bi-check-circle me-1"></i>
                                                Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">
                                                <i class="bi bi-x-circle me-1"></i>
                                                Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="/admin/users/edit/<?= $u['id'] ?>" 
                                               class="btn-action edit" 
                                               title="Edit User">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            
                                            <form action="/admin/users/delete/<?= $u['id'] ?>" 
                                                  method="post" 
                                                  class="d-inline"
                                                  onsubmit="return confirm('Are you sure you want to delete user <?= esc($u['username']) ?>? This action cannot be undone.');">
                                                <?= csrf_field() ?>
                                                <button type="submit" 
                                                        class="btn-action delete" 
                                                        title="Delete User">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
                <!-- TABLE FOOTER -->
        <?php if (!empty($users)): ?>
        <div class="card-footer border-top-0 bg-transparent py-3 px-4">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    <i class="bi bi-people me-1"></i>
                    Showing <span class="fw-semibold text-primary"><?= count($users) ?></span> of <?= esc($pagination['total']) ?> matching user(s)
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <?php if (($pagination['page_count'] ?? 1) > 1): ?>
                        <nav aria-label="User pagination">
                            <ul class="pagination pagination-sm mb-0">
                                <?php for ($pageNum = 1; $pageNum <= (int) $pagination['page_count']; $pageNum++): ?>
                                    <?php $pageQuery = array_filter(array_merge($baseQuery, ['page' => $pageNum]), static fn($value) => $value !== '' && $value !== null); ?>
                                    <li class="page-item <?= (int) $pagination['page'] === $pageNum ? 'active' : '' ?>">
                                        <a class="page-link" href="/admin/users?<?= esc(http_build_query($pageQuery)) ?>"><?= esc($pageNum) ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                    <a class="btn btn-outline-primary btn-sm" href="/admin/users/export?<?= esc(http_build_query($exportQuery)) ?>">
                        <i class="bi bi-download me-1"></i> Export CSV
                    </a>
                    <button class="btn btn-outline-secondary btn-sm" id="refreshUsers">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('userSearch');
    const clearSearchBtn = document.getElementById('clearSearch');
    const userRows = document.querySelectorAll('.user-row');

    if (searchInput && clearSearchBtn) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();

            userRows.forEach(row => {
                const username = row.dataset.username;
                const email = row.dataset.email;
                const roles = row.dataset.roles;

                const matches = username.includes(searchTerm) ||
                               email.includes(searchTerm) ||
                               roles.includes(searchTerm);

                row.style.display = matches ? '' : 'none';
            });
        });

        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
        });
    }
    
    // Confirmation for delete actions
    const deleteForms = document.querySelectorAll('form[action*="delete"]');
    deleteForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
    
    // Add loading effect on actions
    const actionButtons = document.querySelectorAll('.btn-action');
    actionButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            this.classList.add('loading');
            setTimeout(() => {
                this.classList.remove('loading');
            }, 1000);
        });
    });
});

    // Refresh functionality
    document.getElementById('refreshUsers')?.addEventListener('click', function() {
        this.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Refreshing...';
        this.disabled = true;
        
        setTimeout(() => {
            window.location.reload();
        }, 500);
    });
</script>

<?= $this->endSection() ?>
