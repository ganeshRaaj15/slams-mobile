<?= $this->extend('layouts/main_user') ?>
<?= $this->section('content') ?>
<?php
/** @var array<int, array<string, mixed>> $assets */
/** @var string $search */

$assets = is_array($assets ?? null) ? $assets : [];
$search = isset($search) ? (string) $search : '';
$assetTotal = count($assets);
?>
<div class="assets-page">
    <section class="asset-hero">
        <div class="container hero-content text-center">
            <h1 class="asset-title">Laboratory Assets</h1>
            <p class="asset-subtitle">
                Browse equipment available across FKMP laboratories.
            </p>

            <div class="asset-search-box">
                <form method="get" class="d-flex flex-column flex-md-row gap-2" id="searchForm">
                    <input type="text"
                           name="q"
                           id="searchInput"
                           value="<?= esc($search) ?>"
                           class="form-control search-input"
                           placeholder="Search equipment or lab...">
                    <button class="btn search-btn" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </form>

                <div class="search-indicator" id="searchIndicator">
                    <?php if ($search !== ''): ?>
                        <span>Showing results for: <strong><?= esc($search) ?></strong></span>
                        <button class="clear-search-btn ms-2" id="clearFilterBtn" type="button">Clear</button>
                    <?php else: ?>
                        <span>Search by equipment name or laboratory</span>
                        <button class="clear-search-btn ms-2" id="clearFilterBtn" type="button" style="display: none;">Clear</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <div class="container pb-5">
        <div class="text-center mb-3">
            <div class="assets-count d-inline-block" id="assetCount">
                <?= $assetTotal ?> <?= $assetTotal === 1 ? 'Asset' : 'Assets' ?> Available
            </div>
        </div>

        <div id="noResults" class="text-center text-muted py-5" style="display: <?= $assetTotal === 0 ? 'block' : 'none' ?>;">
            <i class="bi bi-hdd fs-1 mb-2"></i>
            <p class="mb-0" id="noResultsText">No equipment available.</p>
        </div>

        <div class="row g-4" id="assetsGrid">
            <?php foreach ($assets as $asset): ?>
                <?php
                $imagePath = !empty($asset['image'])
                    ? base_url($asset['image'])
                    : base_url('images/assets/placeholder_asset.png');
                $status = $asset['status'] ?? 'available';
                $availableUnits = max((int) ($asset['quantity'] ?? 0), 0);
                $totalUnits = max((int) ($asset['total_quantity'] ?? 0), $availableUnits);
                $displayStatus = ($status === 'maintenance' && $availableUnits > 0) ? 'partially available' : $status;
                $statusClass = $displayStatus === 'available'
                    ? 'status-available'
                    : (($displayStatus === 'partially available' || $status === 'maintenance') ? 'status-maintenance' : 'status-faulty');
                $labName = $asset['lab_name'] ?? '';
                $labRoom = $asset['lab_room'] ?? '';
                $assetModel = trim((string) ($asset['model'] ?? ''));
                $assetCategory = trim((string) ($asset['category'] ?? ''));
                ?>
                <div class="col-md-6 col-lg-4 asset-item"
                     data-name="<?= esc(strtolower($asset['name'] ?? '')) ?>"
                     data-model="<?= esc(strtolower($assetModel)) ?>"
                     data-category="<?= esc(strtolower($assetCategory)) ?>"
                     data-lab="<?= esc(strtolower($labName)) ?>"
                     data-room="<?= esc(strtolower($labRoom)) ?>"
                     data-status="<?= esc(strtolower($displayStatus)) ?>"
                     data-qty="<?= esc($availableUnits) ?>">
                    <a class="asset-link" href="<?= site_url('/laboratories/' . ($asset['lab_id'] ?? '')) ?>">
                        <div class="asset-card">
                            <div class="position-relative">
                                <img src="<?= esc($imagePath) ?>" class="asset-image" alt="Asset image">
                                <span class="asset-status <?= esc($statusClass) ?>">
                                    <?= esc(ucwords($displayStatus)) ?>
                                </span>
                            </div>
                            <div class="asset-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="asset-name"><?= esc($asset['name']) ?></div>
                                    <span class="asset-quantity">
                                        <?= esc($availableUnits) ?> available / <?= esc($totalUnits) ?> total
                                    </span>
                                </div>

                                <div class="asset-meta">
                                    <?php if ($assetModel !== ''): ?>
                                        <div>
                                            <i class="bi bi-cpu"></i>
                                            <?= esc($assetModel) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($assetCategory !== ''): ?>
                                        <div>
                                            <i class="bi bi-tags"></i>
                                            <?= esc($assetCategory) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <i class="bi bi-building"></i>
                                        <?= esc($labName ?: 'Unknown Lab') ?>
                                        <?php if (! empty($labRoom)): ?>
                                            , Room <?= esc($labRoom) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const assetItems = document.querySelectorAll('.asset-item');
    const assetCount = document.getElementById('assetCount');
    const noResults = document.getElementById('noResults');
    const noResultsText = document.getElementById('noResultsText');
    const clearFilterBtn = document.getElementById('clearFilterBtn');
    const searchIndicator = document.getElementById('searchIndicator');
    const searchForm = document.getElementById('searchForm');

    if (!searchInput || assetItems.length === 0) {
        return;
    }

    let filterTimeout;

    function updateResults(count, searchTerm) {
        assetCount.textContent = `${count} ${count === 1 ? 'Asset' : 'Assets'} Available`;

        if (searchTerm.trim()) {
            searchIndicator.innerHTML = `<span>Showing results for: <strong>${searchTerm}</strong></span>`;
            clearFilterBtn.style.display = 'inline-flex';
        } else {
            searchIndicator.innerHTML = '<span>Search by equipment name or laboratory</span>';
            clearFilterBtn.style.display = 'none';
        }

        if (count === 0 && searchTerm.trim()) {
            noResultsText.textContent = `No assets found for "${searchTerm}". Try different keywords.`;
            noResults.style.display = 'block';
        } else if (count === 0) {
            noResultsText.textContent = 'No equipment available.';
            noResults.style.display = 'block';
        } else {
            noResults.style.display = 'none';
        }
    }

    function filterAssets(searchTerm) {
        const searchLower = searchTerm.toLowerCase();
        let visibleCount = 0;

        assetItems.forEach(item => {
            const name = item.dataset.name || '';
            const model = item.dataset.model || '';
            const category = item.dataset.category || '';
            const lab = item.dataset.lab || '';
            const room = item.dataset.room || '';
            const status = item.dataset.status || '';
            const qty = item.dataset.qty || '';

            const matches = name.includes(searchLower) ||
                model.includes(searchLower) ||
                category.includes(searchLower) ||
                lab.includes(searchLower) ||
                room.includes(searchLower) ||
                status.includes(searchLower) ||
                qty.includes(searchTerm);

            if (matches || searchTerm === '') {
                item.classList.remove('asset-hidden');
                visibleCount++;
            } else {
                item.classList.add('asset-hidden');
            }
        });

        updateResults(visibleCount, searchTerm);
    }

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.trim();

        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            filterAssets(searchTerm);
        }, 250);
    });

    if (clearFilterBtn) {
        clearFilterBtn.addEventListener('click', function() {
            searchInput.value = '';
            filterAssets('');
            searchInput.focus();
        });
    }

    if (searchForm) {
        searchForm.addEventListener('submit', function(event) {
            event.preventDefault();
            filterAssets(searchInput.value.trim());
        });
    }

    searchInput.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            this.value = '';
            filterAssets('');
        }
    });

    const initialSearch = searchInput.value.trim();
    if (initialSearch) {
        filterAssets(initialSearch);
    } else {
        updateResults(assetItems.length, '');
    }
});
</script>

<?= $this->endSection() ?>
