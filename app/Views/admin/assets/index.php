<?= $this->extend('layouts/main_admin') ?>
<?= $this->section('content') ?>

<?php
$filters = $filters ?? ['q' => '', 'lab_id' => 0, 'status' => ''];
$labs = $labs ?? [];
$statusOptions = $statusOptions ?? ['available', 'maintenance', 'faulty'];
?>


<?php
$totalAssets = count($assets);
$openMaintenance = array_sum(array_map(static fn($asset) => (int) $asset['maintenance_open'], $assets));
$unitsInMaintenance = array_sum(array_map(static fn($asset) => (int) ($asset['maintenance_quantity'] ?? 0), $assets));
?>

<div class="container-fluid">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Asset Management</h1>
            <p class="text-muted mb-0">Track equipment specifications, live availability, and maintenance history in one place.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="/admin/assets/qr-labels" class="btn btn-outline-primary"><i class="bi bi-qr-code me-2"></i>Print QR Labels</a>
            <a href="/admin/assets/create" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Add Asset</a>
        </div>
    </div>

    <?php if (session()->getFlashdata('message')): ?>
        <div class="alert alert-success border-0 shadow-sm"><?= esc(session()->getFlashdata('message')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger border-0 shadow-sm"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <div class="asset-table-card p-3 mb-4">
        <form method="get" action="/admin/assets" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted">Search</label>
                <input type="text" name="q" class="form-control" value="<?= esc($filters['q']) ?>" placeholder="Asset name, code, serial, category, or lab">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Lab</label>
                <select name="lab_id" class="form-select">
                    <option value="0">All laboratories</option>
                    <?php foreach ($labs as $lab): ?>
                        <option value="<?= esc($lab['id']) ?>" <?= (int) $filters['lab_id'] === (int) $lab['id'] ? 'selected' : '' ?>>
                            <?= esc($lab['name']) ?><?= !empty($lab['room']) ? ' - Room ' . esc($lab['room']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    <?php foreach ($statusOptions as $status): ?>
                        <option value="<?= esc($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= esc(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="/admin/assets" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
        <div class="small text-muted mt-3">Showing <?= esc(count($assets)) ?> asset record(s). Availability is system-managed from open maintenance records.</div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="asset-metric p-3"><div class="text-muted small text-uppercase">Registered Assets</div><div class="display-6 fw-bold"><?= esc($totalAssets) ?></div></div></div>
        <div class="col-md-4"><div class="asset-metric p-3"><div class="text-muted small text-uppercase">Open Maintenance Records</div><div class="display-6 fw-bold"><?= esc($openMaintenance) ?></div></div></div>
        <div class="col-md-4"><div class="asset-metric p-3"><div class="text-muted small text-uppercase">Units Under Maintenance</div><div class="display-6 fw-bold"><?= esc($unitsInMaintenance) ?></div></div></div>
    </div>

    <div class="asset-table-card p-3 p-lg-4">
        <?php if (empty($assets)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-box-seam fs-1 d-block mb-3"></i>
                No assets have been added yet.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Asset</th>
                            <th>Lab</th>
                            <th>Specification</th>
                            <th>Availability</th>
                            <th>Maintenance</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assets as $asset): ?>
                            <?php $imagePath = !empty($asset['image']) ? base_url($asset['image']) : base_url('images/assets/placeholder_asset.png'); ?>
                            <?php $badge = ($asset['quantity'] ?? 0) > 0 ? (($asset['maintenance_quantity'] ?? 0) > 0 ? 'warning text-dark' : 'success') : 'secondary'; ?>
                            <tr>
                                <td>
                                    <div class="d-flex gap-3 align-items-center">
                                        <img src="<?= esc($imagePath) ?>" alt="Asset image" class="asset-thumb shadow-sm">
                                        <div>
                                            <div class="asset-code"><?= esc($asset['asset_code']) ?></div>
                                            <div class="fw-semibold"><?= esc($asset['name']) ?></div>
                                            <div class="small text-muted"><?= esc($asset['category'] ?: 'Uncategorized') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= esc($asset['lab_name'] ?? '-') ?></div>
                                    <div class="small text-muted"><?= esc($asset['lab_room'] ?? '-') ?></div>
                                </td>
                                <td>
                                    <div><?= esc(trim(($asset['brand'] ?: '-') . ' ' . ($asset['model'] ?: ''))) ?></div>
                                    <div class="small text-muted">SN: <?= esc($asset['serial_number'] ?: '-') ?></div>
                                    <div class="small text-muted">Total stock: <?= esc($asset['total_quantity']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $badge ?> text-uppercase"><?= esc($asset['status']) ?></span>
                                    <div class="small text-muted mt-1">Available now: <?= esc($asset['quantity']) ?> unit(s)</div>
                                    <div class="small text-muted">Under maintenance: <?= esc($asset['maintenance_quantity']) ?> unit(s)</div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= esc($asset['maintenance_total']) ?> total record(s)</div>
                                    <div class="small text-muted"><?= esc($asset['maintenance_open']) ?> open</div>
                                    <div class="small text-muted">Last completed: <?= esc($asset['last_completed_at'] ? date('d M Y', strtotime($asset['last_completed_at'])) : '-') ?></div>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $qrCode = $asset['asset_code'] ?: ('AST-' . str_pad((string) $asset['id'], 4, '0', STR_PAD_LEFT));
                                    $qrUrl = qr_public_url('qr/asset/' . rawurlencode($qrCode), ['open' => 1]);
                                    $labLabel = trim(($asset['lab_name'] ?? 'Unknown Lab') . (!empty($asset['lab_room']) ? ' | Room ' . $asset['lab_room'] : ''));
                                    ?>
                                    <div class="d-inline-flex gap-1">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-dark"
                                                data-qr-url="<?= esc($qrUrl) ?>"
                                                data-asset-name="<?= esc($asset['name']) ?>"
                                                data-asset-code="<?= esc($qrCode) ?>"
                                                data-asset-lab="<?= esc($labLabel) ?>"
                                                onclick="openQrModal(this)">
                                            <i class="bi bi-qr-code"></i>
                                        </button>
                                        <a href="/admin/assets/edit/<?= esc($asset['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <button type="button" onclick="deleteAsset(<?= esc($asset['id']) ?>)" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Asset QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-4 align-items-start">
                    <div id="qrPreview" class="qr-preview"></div>
                    <div class="flex-grow-1">
                        <div class="qr-meta-label mb-1">Equipment</div>
                        <div class="fw-semibold" id="qrAssetName">-</div>
                        <div class="text-muted small" id="qrAssetCode">-</div>
                        <div class="text-muted small" id="qrAssetLab">-</div>
                        <div class="small text-muted mt-3">Scan the QR code to open the booking wizard for this equipment.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="/admin/assets/qr-labels" class="btn btn-outline-primary btn-sm" id="printQrSheet">Print Label Sheet</a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
function deleteAsset(id) {
    if (!confirm('Delete this asset? This will be blocked if maintenance history exists.')) {
        return;
    }
    fetch(`/admin/assets/delete/${id}`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '<?= csrf_hash() ?>'
        }
    }).then(async (response) => {
        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            alert(data.message || 'Unable to delete this asset.');
            return;
        }
        location.reload();
    });
}

let qrModalInstance = null;

function openQrModal(button) {
    const url = button.dataset.qrUrl || '';
    const name = button.dataset.assetName || '-';
    const code = button.dataset.assetCode || '-';
    const lab = button.dataset.assetLab || '-';

    const preview = document.getElementById('qrPreview');
    const printSheet = document.getElementById('printQrSheet');

    if (preview) {
        preview.innerHTML = '';
        new QRCode(preview, {
            text: url,
            width: 180,
            height: 180,
            correctLevel: QRCode.CorrectLevel.M
        });
    }

    if (printSheet) {
        printSheet.href = `/admin/assets/qr-labels?q=${encodeURIComponent(code)}`;
    }

    const nameEl = document.getElementById('qrAssetName');
    const codeEl = document.getElementById('qrAssetCode');
    const labEl = document.getElementById('qrAssetLab');

    if (nameEl) nameEl.textContent = name;
    if (codeEl) codeEl.textContent = code;
    if (labEl) labEl.textContent = lab;

    if (!qrModalInstance) {
        const modalEl = document.getElementById('qrModal');
        if (modalEl) {
            if (modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }
            qrModalInstance = new bootstrap.Modal(modalEl);
        }
    }

    if (qrModalInstance) {
        qrModalInstance.show();
    }
}
</script>

<?= $this->endSection() ?>
