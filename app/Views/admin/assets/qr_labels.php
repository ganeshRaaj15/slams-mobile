<?= $this->extend('layouts/main_admin') ?>
<?= $this->section('content') ?>

<style>
.qr-page { min-height: 100%; }
.qr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 18px; }
.qr-label {
    background: #ffffff;
    border: 1px solid rgba(148, 163, 184, 0.35);
    border-radius: 16px;
    padding: 16px;
    text-align: center;
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}
.qr-code {
    width: 160px;
    height: 160px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    background: #f8fafc;
    border: 1px dashed rgba(59, 130, 246, 0.35);
}
.qr-asset-name { font-weight: 600; font-size: 0.95rem; }
.qr-asset-code { font-size: 0.75rem; letter-spacing: 0.1em; text-transform: uppercase; color: #64748b; }
.qr-asset-lab { font-size: 0.8rem; color: #475569; }
.qr-tag { font-size: 0.7rem; letter-spacing: 0.12em; text-transform: uppercase; color: #1d4ed8; }

@media print {
    body { background: #fff !important; }
    .glass-sidebar, .admin-glass-navbar, .sidebar-overlay, footer, .chatbot-toggle, .chatbot-widget, .chatbot-panel, .no-print { display: none !important; }
    .admin-layout { padding-left: 0 !important; }
    .content-area { padding: 0 !important; }
    .qr-grid { gap: 12px; }
    .qr-label { box-shadow: none; border-color: #cbd5f5; }
}
</style>

<?php $assetCount = is_array($assets ?? null) ? count($assets) : 0; ?>

<div class="container-fluid qr-page">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 no-print">
        <div>
            <h1 class="h3 mb-1">QR Label Sheet</h1>
            <p class="text-muted mb-0">Print QR labels and attach them to equipment for instant booking access.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="/admin/assets" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back to Assets</a>
            <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-2"></i>Print</button>
        </div>
    </div>

    <form method="get" class="d-flex flex-wrap gap-2 mb-4 no-print">
        <input type="text" name="q" class="form-control" placeholder="Filter by asset, code, or lab" value="<?= esc($search ?? '') ?>">
        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
        <?php if (! empty($search ?? '')): ?>
            <a href="/admin/assets/qr-labels" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
        <div class="ms-auto align-self-center text-muted small">Showing <?= esc($assetCount) ?> label(s)</div>
    </form>

    <?php if (empty($assets)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-qr-code fs-1 d-block mb-3"></i>
            No assets matched your filter.
        </div>
    <?php else: ?>
        <div class="qr-grid">
            <?php foreach ($assets as $asset): ?>
                <?php
                $qrCode = $asset['asset_code'] ?: ('AST-' . str_pad((string) $asset['id'], 4, '0', STR_PAD_LEFT));
                $qrUrl = site_url('qr/asset/' . rawurlencode($qrCode)) . '?open=1';
                $labLabel = trim(($asset['lab_name'] ?? 'Unknown Lab') . (!empty($asset['lab_room']) ? ' | Room ' . $asset['lab_room'] : ''));
                ?>
                <div class="qr-label">
                    <div class="qr-code" data-qr-url="<?= esc($qrUrl) ?>"></div>
                    <div class="qr-tag">Scan to Book</div>
                    <div class="qr-asset-name"><?= esc($asset['name']) ?></div>
                    <div class="qr-asset-code"><?= esc($qrCode) ?></div>
                    <div class="qr-asset-lab"><?= esc($labLabel) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.qr-code[data-qr-url]').forEach((el) => {
        const url = el.dataset.qrUrl || '';
        if (!url) return;
        new QRCode(el, {
            text: url,
            width: 150,
            height: 150,
            correctLevel: QRCode.CorrectLevel.M
        });
    });
});
</script>

<?= $this->endSection() ?>

