<aside class="glass-sidebar d-flex flex-column">
    <div class="text-center mb-4 mt-3">
        <div class="sidebar-logo">
            <i class="bi bi-wrench-adjustable-circle text-white fs-4"></i>
        </div>
        <div class="fw-bold text-white">Technician Dashboard</div>
        <small class="text-light opacity-75">Maintenance Operations</small>
    </div>

    <hr class="sidebar-divider">

    <a href="/dashboard/technician" class="sidebar-link <?= url_is('dashboard/technician') ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i> Overview
    </a>

    <a href="/technician/maintenance" class="sidebar-link <?= url_is('technician/maintenance*') && ! url_is('technician/maintenance/create*') ? 'active' : '' ?>">
        <i class="bi bi-tools"></i> Maintenance Records
    </a>

    <a href="/technician/maintenance/create" class="sidebar-link <?= url_is('technician/maintenance/create*') ? 'active' : '' ?>">
        <i class="bi bi-plus-circle"></i> New Maintenance
    </a>

    <a href="/dashboard/profile" class="sidebar-link <?= url_is('dashboard/profile') ? 'active' : '' ?>">
        <i class="bi bi-person-circle"></i> My Profile
    </a>
</aside>
