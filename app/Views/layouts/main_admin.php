<?php
helper(['url', 'asset', 'auth']);
use App\Libraries\WebPushConfiguration;

$webPushClient = (new WebPushConfiguration())->clientConfig();
$pushLoggedIn = function_exists('auth') && auth()->loggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f766e">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="SLAMS">
    <title><?= esc($title ?? 'Admin Dashboard | SLAMS') ?></title>

    <script src="<?= slams_asset('js/theme.js') ?>"></script>
    <link rel="manifest" href="<?= slams_asset('manifest.webmanifest') ?>">
    <link rel="icon" href="<?= slams_asset('icons/slams-mobile.svg') ?>" type="image/svg+xml">
    <?= csrf_meta('slams-csrf-meta') ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= slams_asset('css/theme.css') ?>" rel="stylesheet">
    <link href="<?= slams_asset('css/mobile-app.css') ?>" rel="stylesheet">
    <?= $this->renderSection('styles') ?>
</head>

<body
    class="slams-app slams-mobile-app slams-layout-admin"
    data-user-logged-in="<?= $pushLoggedIn ? '1' : '0' ?>"
    data-push-configured="<?= !empty($webPushClient['configured']) ? '1' : '0' ?>"
    data-push-public-key="<?= esc($webPushClient['publicKey'] ?? '') ?>"
    data-push-subscribe-url="/dashboard/push/subscribe"
    data-push-unsubscribe-url="/dashboard/push/unsubscribe"
    data-push-test-url="/dashboard/push/test"
>
    <?= $this->include('components/sidebar_admin') ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="admin-layout">
        <?= $this->include('components/navbar_admin') ?>
        <main class="content-area slams-content">
            <?= $this->renderSection('content') ?>
        </main>
        <?= $this->include('components/footer') ?>
    </div>

    <?= $this->include('components/chatbot') ?>
    <?= $this->include('components/mobile_quick_actions') ?>
    <?= $this->include('components/mobile_bottom_nav') ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= slams_asset('js/mobile-app.js') ?>"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
