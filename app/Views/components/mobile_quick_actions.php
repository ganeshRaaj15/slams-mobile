<?php
use App\Libraries\MobileExperienceBuilder;

$mobileExperience = (new MobileExperienceBuilder())->current();
$mobileQuickActions = $mobileExperience['quickActions'] ?? [];

if ($mobileQuickActions === []) {
    return;
}
?>

<div class="slams-mobile-status-banner" data-mobile-status-banner hidden></div>
<div class="slams-mobile-update-banner" data-mobile-update-banner hidden>
    <div class="slams-mobile-update-copy">
        <strong>Update available</strong>
        <span>A fresher mobile shell is ready.</span>
    </div>
    <button type="button" class="btn btn-light btn-sm" data-mobile-app-update>Reload</button>
</div>

<button
    type="button"
    class="slams-mobile-fab"
    data-mobile-sheet-toggle
    aria-controls="slamsMobileActionSheet"
    aria-expanded="false"
>
    <i class="bi bi-lightning-charge-fill" aria-hidden="true"></i>
    <span>Actions</span>
    <?php if (! empty($mobileExperience['attentionCount'])): ?>
        <span class="slams-mobile-fab-badge"><?= esc($mobileExperience['attentionCount'] > 99 ? '99+' : (string) $mobileExperience['attentionCount']) ?></span>
    <?php endif; ?>
</button>

<div class="slams-mobile-sheet-backdrop" data-mobile-sheet-backdrop hidden></div>

<section class="slams-mobile-sheet" id="slamsMobileActionSheet" aria-hidden="true" tabindex="-1">
    <div class="slams-mobile-sheet-handle" aria-hidden="true"></div>

    <div class="slams-mobile-sheet-header">
        <div>
            <div class="slams-mobile-sheet-eyebrow">Mobile Workspace</div>
            <h2 class="slams-mobile-sheet-title"><?= esc($mobileExperience['sheetTitle'] ?? 'Mobile Actions') ?></h2>
            <p class="slams-mobile-sheet-description"><?= esc($mobileExperience['sheetDescription'] ?? '') ?></p>
        </div>
        <button type="button" class="slams-mobile-sheet-close" data-mobile-sheet-close aria-label="Close quick actions">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>

    <div class="slams-mobile-sheet-status">
        <span class="slams-mobile-status-pill is-online" data-mobile-network-status>Online</span>
        <span class="slams-mobile-status-pill" data-mobile-last-sync>Checking sync</span>
        <span class="slams-mobile-status-pill" data-mobile-push-status>Push off</span>
        <span class="slams-mobile-status-pill is-emphasis"><?= esc($mobileExperience['attentionLabel'] ?? 'Ready') ?></span>
    </div>

    <div class="slams-mobile-action-grid">
        <?php foreach ($mobileQuickActions as $action): ?>
            <a href="<?= esc($action['href']) ?>" class="slams-mobile-action-card" data-mobile-action-link>
                <div class="slams-mobile-action-icon">
                    <i class="bi <?= esc($action['icon']) ?>" aria-hidden="true"></i>
                </div>
                <div class="slams-mobile-action-copy">
                    <div class="slams-mobile-action-title"><?= esc($action['label']) ?></div>
                    <div class="slams-mobile-action-meta"><?= esc($action['meta']) ?></div>
                </div>
                <?php if (! empty($action['badge'])): ?>
                    <span class="slams-mobile-action-badge"><?= esc($action['badge'] > 99 ? '99+' : (string) $action['badge']) ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="slams-mobile-sheet-footer">
        <div class="slams-mobile-sheet-note"><?= esc($mobileExperience['attentionMeta'] ?? '') ?></div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-mobile-push-toggle hidden>
                <i class="bi bi-bell me-1" aria-hidden="true"></i>Push
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" data-mobile-refresh>
                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Refresh
            </button>
        </div>
    </div>
</section>
