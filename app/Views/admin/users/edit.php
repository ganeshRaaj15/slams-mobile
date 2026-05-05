<?= $this->extend('layouts/main_admin') ?>

<?= $this->section('content') ?>


<div class="user-form-page">
    <!-- PAGE HEADER -->
    <div class="dashboard-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
            <div>
                <h1>Edit User</h1>
                <p>Update user details, roles, and permissions</p>
            </div>
            <a href="/admin/users" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Users
            </a>
        </div>
    </div>

    <!-- FLASH MESSAGES -->
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger glass-card mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                <div class="flex-grow-1"><?= session()->getFlashdata('error') ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- USER SUMMARY -->
    <div class="user-summary glass-card">
        <div class="user-summary-header">
            <div class="user-avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div class="user-info flex-grow-1">
                <h4><?= esc($user->username) ?></h4>
                <div class="text-muted">User ID: <?= $user->id ?> • Email: <?= esc($email) ?></div>
            </div>
            <div class="d-flex gap-2">
                <span class="badge <?= $user->active ? 'badge-active' : 'badge-inactive' ?>">
                    <i class="bi bi-<?= $user->active ? 'check-circle' : 'x-circle' ?> me-1"></i>
                    <?= $user->active ? 'Active' : 'Inactive' ?>
                </span>
            </div>
        </div>
        
        <div class="user-stats">
            <div class="stat-item">
                <div class="stat-value"><?= count($roles) ?></div>
                <div class="stat-label">Roles Assigned</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= date('M d, Y', strtotime($user->created_at ?? 'now')) ?></div>
                <div class="stat-label">Member Since</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">
                    <?php 
                    $lastLogin = $user->last_active ? date('M d', strtotime($user->last_active)) : 'Never';
                    echo $lastLogin;
                    ?>
                </div>
                <div class="stat-label">Last Login</div>
            </div>
        </div>
    </div>

    <!-- USER FORM -->
    <div class="glass-card">
        <div class="card-body p-4">
            <form method="post" action="/admin/users/update/<?= $user->id ?>" id="userForm">
                <?= csrf_field() ?>
                <input type="hidden" name="active" id="activeInput" value="<?= $user->active ? '1' : '0' ?>">
                
                <div class="mb-5">
                    <h5 class="section-title">
                        <i class="bi bi-person-badge"></i>
                        Basic Information
                    </h5>
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="form-group-glass">
                                <label for="username">
                                    <i class="bi bi-person"></i>
                                    Username
                                </label>
                                <input type="text" 
                                       class="form-control form-control-glass" 
                                       id="username" 
                                       name="username" 
                                       value="<?= old('username', $user->username) ?>" 
                                       required
                                       placeholder="Enter username">
                                <div class="form-hint">Unique identifier for the user</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group-glass">
                                <label for="email">
                                    <i class="bi bi-envelope"></i>
                                    Email Address
                                </label>
                                <input type="email" 
                                       class="form-control form-control-glass" 
                                       id="email" 
                                       name="email" 
                                       value="<?= old('email', $email) ?>" 
                                       required
                                       placeholder="user@example.com">
                                <div class="form-hint">Primary email for communication</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group-glass">
                                <label for="full_name">
                                    <i class="bi bi-person-vcard"></i>
                                    Full Name
                                </label>
                                <input type="text"
                                       class="form-control form-control-glass"
                                       id="full_name"
                                       name="full_name"
                                       value="<?= old('full_name', $user->full_name ?? '') ?>"
                                       placeholder="Enter full name">
                                <div class="form-hint">Display name used in reports and user-facing pages</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group-glass">
                                <label for="phone">
                                    <i class="bi bi-telephone"></i>
                                    Phone Number
                                </label>
                                <input type="text"
                                       class="form-control form-control-glass"
                                       id="phone"
                                       name="phone"
                                       value="<?= old('phone', $user->phone ?? '') ?>"
                                       placeholder="Enter phone number">
                                <div class="form-hint">Operational contact number for approvals and notifications</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group-glass">
                                <label for="faculty_id">
                                    <i class="bi bi-building"></i>
                                    Faculty
                                </label>
                                <select class="form-control form-control-glass" id="faculty_id" name="faculty_id">
                                    <option value="">No faculty assigned</option>
                                    <?php foreach ($faculties as $faculty): ?>
                                        <option value="<?= esc($faculty['id']) ?>" <?= (string) old('faculty_id', $user->faculty_id ?? '') === (string) $faculty['id'] ? 'selected' : '' ?>>
                                            <?= esc($faculty['code']) ?> - <?= esc($faculty['name_en']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-hint">Primary faculty used in profile and booking workflows</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group-glass">
                                <label for="activeDisplay">
                                    <i class="bi bi-toggle-on"></i>
                                    Account Status
                                </label>
                                <input type="text"
                                       class="form-control form-control-glass"
                                       id="activeDisplay"
                                       value="<?= $user->active ? 'Active' : 'Inactive' ?>"
                                       readonly>
                                <div class="form-hint">Use the activate/deactivate action below to change status</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <h5 class="section-title">
                        <i class="bi bi-shield-lock"></i>
                        Security
                    </h5>
                    
                    <div class="alert alert-info glass-card mb-4">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <div class="small">
                                Leave password fields blank if you don't want to change the password. For forgotten passwords, send a secure recovery link instead of sharing a temporary password.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="form-group-glass password-toggle">
                                <label for="password">
                                    <i class="bi bi-key"></i>
                                    New Password
                                </label>
                                <input type="password" 
                                       class="form-control form-control-glass" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Leave blank to keep current">
                                <button type="button" class="toggle-password" data-target="password">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <div class="form-hint">Minimum 8 characters with letters and numbers</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group-glass password-toggle">
                                <label for="password_confirm">
                                    <i class="bi bi-key-fill"></i>
                                    Confirm Password
                                </label>
                                <input type="password" 
                                       class="form-control form-control-glass" 
                                       id="password_confirm" 
                                       name="password_confirm" 
                                       placeholder="Confirm new password">
                                <button type="button" class="toggle-password" data-target="password_confirm">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <div class="form-hint">Re-enter the password for verification</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <h5 class="section-title">
                        <i class="bi bi-person-rolodex"></i>
                        Roles & Permissions
                    </h5>
                    
                    <div class="role-checkbox-group">
                        <div class="row">
                            <?php foreach ($allRoles as $role): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-check-glass">
                                        <input class="form-check-input-glass" 
                                               type="checkbox" 
                                               name="roles[]" 
                                               value="<?= esc($role) ?>" 
                                               id="role_<?= esc($role) ?>"
                                               data-role="<?= esc($role) ?>"
                                               <?= in_array($role, $roles) ? 'checked' : '' ?>>
                                        <label class="form-check-label-glass" for="role_<?= esc($role) ?>">
                                            <i class="bi bi-person-badge me-2"></i>
                                            <?= ucfirst($role) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-4">
                            <div class="alert alert-warning glass-card">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <div class="small">
                                        <strong>Warning:</strong> Changing roles will affect user permissions immediately.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FORM ACTIONS -->
                <div class="border-top pt-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <button type="submit" class="btn btn-primary-glass px-4">
                                <i class="bi bi-save me-2"></i> Save Changes
                            </button>
                            <button type="submit"
                                    class="btn btn-warning-glass ms-2"
                                    formaction="/admin/users/send-recovery/<?= $user->id ?>"
                                    formmethod="post"
                                    formnovalidate
                                    onclick="return confirm('Send a secure sign-in link to this user\\'s registered email?');">
                                <i class="bi bi-envelope-paper me-2"></i> Send Recovery Link
                            </button>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <a href="/admin/users" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> Cancel
                            </a>
                            <button type="button" class="btn btn-outline-danger" id="deactivateUser">
                                <i class="bi bi-person-x me-1"></i> 
                                <?= $user->active ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });
    });
    
    // Deactivate/Activate user
    document.getElementById('deactivateUser')?.addEventListener('click', function() {
        const action = this.innerHTML.includes('Deactivate') ? 'deactivate' : 'activate';
        const username = '<?= esc($user->username) ?>';
        
        if (confirm(`Are you sure you want to ${action} ${username}?`)) {            const activeInput = document.getElementById('activeInput');
            if (activeInput) {
                activeInput.value = action === 'deactivate' ? '0' : '1';
            }
            document.getElementById('userForm')?.submit();
        }
    });
    
    // Password validation
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirm');
    
    passwordInput.addEventListener('input', function() {
        if (this.value.length === 0) return;
        
        const password = this.value;
        const strength = checkPasswordStrength(password);
        
        if (password.length > 0 && password.length < 8) {
            this.style.borderColor = '#ef4444';
        } else if (strength === 'weak') {
            this.style.borderColor = '#f59e0b';
        } else if (strength === 'strong') {
            this.style.borderColor = '#10b981';
        }
    });
    
    // Password confirmation check
    passwordConfirmInput.addEventListener('input', function() {
        if (passwordInput.value.length === 0 && this.value.length === 0) return;
        
        if (passwordInput.value !== this.value) {
            this.style.borderColor = '#ef4444';
        } else {
            this.style.borderColor = '#10b981';
        }
    });
    
    // Form validation
    document.getElementById('userForm').addEventListener('submit', function(e) {
        const password = passwordInput.value;
        const confirm = passwordConfirmInput.value;
        
        if (password.length > 0) {
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                passwordInput.focus();
                return;
            }
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match.');
                passwordConfirmInput.focus();
                return;
            }
        }
        
        const selectedRoles = Array.from(document.querySelectorAll('input[name="roles[]"]:checked'));
        if (selectedRoles.length === 0) {
            if (!confirm('No roles selected. Are you sure you want to remove all roles from this user?')) {
                e.preventDefault();
            }
        }
    });
    
    function checkPasswordStrength(password) {
        if (password.length < 8) return 'too-short';
        
        const hasLetters = /[a-zA-Z]/.test(password);
        const hasNumbers = /[0-9]/.test(password);
        const hasSpecial = /[^a-zA-Z0-9]/.test(password);
        
        if (hasLetters && hasNumbers && hasSpecial) return 'strong';
        if (hasLetters && hasNumbers) return 'medium';
        return 'weak';
    }
});
</script>

<?= $this->endSection() ?>


