<?php
helper(['url']);
use App\Libraries\MobileExperienceBuilder;

$mobileExperience = (new MobileExperienceBuilder())->current();
$mobileNavLoggedIn = (bool) ($mobileExperience['loggedIn'] ?? false);
$dashboardHref = $mobileExperience['dashboardHref'] ?? '/dashboard';
$dashboardLabel = $mobileExperience['dashboardLabel'] ?? 'Dashboard';
$alertsBadge = (int) ($mobileExperience['alertsBadge'] ?? 0);

$mobileNavItems = [
    [
        'href' => '/',
        'icon' => 'bi-house-door',
        'label' => 'Home',
        'active' => url_is('/'),
    ],
    [
        'href' => '/laboratories',
        'icon' => 'bi-building',
        'label' => 'Labs',
        'active' => url_is('laboratories*'),
    ],
    [
        'href' => '/assets',
        'icon' => 'bi-box-seam',
        'label' => 'Assets',
        'active' => url_is('assets*'),
    ],
];

if ($mobileNavLoggedIn) {
    $mobileNavItems[] = [
        'href' => $dashboardHref,
        'icon' => 'bi-speedometer2',
        'label' => $dashboardLabel,
        'active' => url_is('dashboard*') || url_is('admin*') || url_is('technician*'),
    ];
    $mobileNavItems[] = [
        'href' => '/dashboard/notifications',
        'icon' => 'bi-bell',
        'label' => 'Alerts',
        'active' => url_is('dashboard/notifications*'),
        'badge' => $alertsBadge,
    ];
} else {
    $mobileNavItems[] = [
        'href' => '/contact',
        'icon' => 'bi-envelope',
        'label' => 'Contact',
        'active' => url_is('contact'),
    ];
    $mobileNavItems[] = [
        'href' => '/login',
        'icon' => 'bi-box-arrow-in-right',
        'label' => 'Login',
        'active' => url_is('login*'),
    ];
}
?>

<nav class="slams-mobile-bottom-nav" aria-label="Mobile app navigation">
    <?php foreach ($mobileNavItems as $item): ?>
        <a href="<?= esc($item['href']) ?>" class="slams-mobile-nav-item <?= $item['active'] ? 'active' : '' ?>">
            <i class="bi <?= esc($item['icon']) ?>" aria-hidden="true"></i>
            <span><?= esc($item['label']) ?></span>
            <?php if (! empty($item['badge'])): ?>
                <span class="slams-mobile-nav-badge"><?= esc($item['badge'] > 99 ? '99+' : (string) $item['badge']) ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
