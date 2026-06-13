<aside class="glass-sidebar d-flex flex-column">
    <div class="text-center mb-4 mt-3">
        <div class="sidebar-logo">
            <i class="bi bi-speedometer2 text-white fs-4"></i>
        </div>
        <div class="fw-bold text-white">Admin Dashboard</div>
        <small class="text-light opacity-75">SLAMS | FKMP</small>
    </div>

    <hr class="sidebar-divider">

    <a href="/dashboard/admin" class="sidebar-link <?= url_is('dashboard/admin') ? 'active' : '' ?>">
        <i class="bi bi-briefcase"></i> Admin Panel
    </a>

    <a href="/admin/labs" class="sidebar-link <?= url_is('admin/labs*') ? 'active' : '' ?>">
        <i class="bi bi-building"></i> Manage Labs
    </a>

    <a href="/admin/assets" class="sidebar-link <?= url_is('admin/assets*') ? 'active' : '' ?>">
        <i class="bi bi-box-seam"></i> Manage Assets
    </a>

    <a href="/admin/services" class="sidebar-link <?= url_is('admin/services*') ? 'active' : '' ?>">
        <i class="bi bi-diagram-3"></i> Manage Services
    </a>

    <a href="/admin/reservations" class="sidebar-link <?= url_is('admin/reservations*') ? 'active' : '' ?>">
        <i class="bi bi-calendar-range"></i> Lab Reservations
    </a>

    <a href="/admin/users" class="sidebar-link <?= url_is('admin/users*') ? 'active' : '' ?>">
        <i class="bi bi-people"></i> User Management
    </a>

    <a href="/dashboard/external-requests" class="sidebar-link <?= url_is('dashboard/external-requests*') ? 'active' : '' ?>">
        <i class="bi bi-clipboard-data"></i> External Requests
    </a>

    <a href="/admin/settings" class="sidebar-link <?= url_is('admin/settings') ? 'active' : '' ?>">
        <i class="bi bi-gear"></i> System Settings
    </a>
</aside>
