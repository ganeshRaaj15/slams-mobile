<?= $this->extend('layouts/main_user') ?>
<?= $this->section('content') ?>

<?php
$picName = trim((string)($lab['pic_name'] ?? ''));
$picEmail = trim((string)($lab['pic_email'] ?? ''));
$picPhone = trim((string)($lab['pic_phone'] ?? ''));
$services = is_array($services ?? null) ? $services : [];

if ($picName === '') {
    $picName = 'null';
}
if ($picEmail === '') {
    $picEmail = 'null';
}
if ($picPhone === '') {
    $picPhone = 'null';
}
?>

<!-- ============================================================
     LABORATORY DETAIL PAGE CONTENT
     ============================================================ -->
<div class="lab-detail-page">
    <div class="container">
        
        <!-- Breadcrumb -->
        <div class="lab-breadcrumb">
            <a href="<?= site_url('/laboratories') ?>" class="back-link">
                <i class="bi bi-arrow-left"></i>
                Back to Laboratory Directory
            </a>
        </div>

        <!-- Lab Header -->
        <div class="lab-header-card">
            <div class="lab-header-content">
                <div class="lab-header-info">
                    <h1 class="lab-title"><?= esc($lab['name']) ?></h1>
                    
                    <?php if (!empty($lab['room'])): ?>
                        <div class="lab-room">
                            <i class="bi bi-door-open"></i>
                            Room <?= esc($lab['room']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <p class="lab-description">
                        Choose a laboratory service, review the linked equipment, then check real-time
                        availability before submitting your booking request.
                    </p>
                </div>
                
                <!-- Lab Image -->
                <?php 
                $labImagePath = $lab['image'] ?? '';
                $labImageExists = false;
                if (!empty($labImagePath)) {
                    $fullImagePath = WRITEPATH . str_replace('uploads/', 'uploads/', $labImagePath);
                    $labImageExists = file_exists($fullImagePath);
                }
                ?>
                
                <?php if (!empty($lab['image'])): ?>
                    <div class="lab-header-image">
                        <img src="<?= base_url($lab['image']) ?>" 
                             alt="<?= esc($lab['name']) ?>"
                             onerror="this.onerror=null; this.src='<?= base_url('images/assets/placeholder_asset.png') ?>';">
                    </div>
                <?php else: ?>
                    <!-- Placeholder when no image exists -->
                    <div class="lab-header-image">
                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #e0f2fe, #eff6ff); border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #3b82f6; font-size: 3rem;">
                            <i class="bi bi-building"></i>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-4">
            <!-- Person in Charge Card -->
            <div class="col-lg-4">
                <div class="pic-card">
                    <div class="pic-content">
                        <!-- PIC Image -->
                        <?php 
                        $picImagePath = $lab['pic_image'] ?? '';
                        $picImageExists = false;
                        if (!empty($picImagePath)) {
                            $fullPicPath = WRITEPATH . str_replace('uploads/', 'uploads/', $picImagePath);
                            $picImageExists = file_exists($fullPicPath);
                        }
                        ?>
                        
                        <div class="pic-avatar">
                            <?php if (!empty($lab['pic_image'])): ?>
                                <img src="<?= base_url($lab['pic_image']) ?>" 
                                     alt="<?= esc($picName) ?>"
                                     onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <i class="bi bi-person-gear" style="display: none; font-size: 2.5rem; color: #3b82f6;"></i>
                            <?php else: ?>
                                <i class="bi bi-person-gear"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="pic-info">
                            <div class="pic-label">Person in Charge</div>
                            <div class="pic-name"><?= esc($picName) ?></div>
                            
                            <div class="pic-contact">
                                <div class="contact-item">
                                    <i class="bi bi-envelope"></i>
                                    <?php if ($picEmail !== 'null'): ?>
                                        <a href="mailto:<?= esc($picEmail) ?>"><?= esc($picEmail) ?></a>
                                    <?php else: ?>
                                        <?= esc($picEmail) ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="contact-item">
                                    <i class="bi bi-telephone"></i>
                                    <?= esc($picPhone) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pic-note">
                        <i class="bi bi-info-circle"></i>
                        External users can submit an access request after login. Guests should contact the PIC or register for an external account first.
                    </div>
                </div>
            </div>

            <!-- Equipment Section -->
            <div class="col-lg-8">
                <div class="equipment-card">
                    <div class="equipment-header">
                        <h2 class="equipment-title">
                            <i class="bi bi-tools"></i>
                            Available Equipment
                        </h2>
                        <span class="equipment-badge">
                            <?= count($assets) ?> Equipment Available
                        </span>
                    </div>

                    <div id="selectedServiceSummary" class="alert alert-info small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Choose a service below to activate the correct equipment set for booking.
                    </div>
                    
                    <?php if (empty($assets)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-tools text-primary fs-1 mb-3"></i>
                            <h4 class="fw-semibold text-primary mb-2">No Equipment Configured</h4>
                            <p class="text-muted">This laboratory does not have any equipment listed yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="equipment-table">
                                <thead>
                                    <tr>
                                        <th class="equipment-checkbox"></th>
                                        <th>Equipment Details</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-center">Request Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $unavailableStatuses = ['maintenance', 'faulty'];
                                    $availableCount = 0;
                                    ?>
                                    <?php foreach ($assets as $a): ?>
                                        <?php 
                                        $availableUnits = max((int) ($a['quantity'] ?? 0), 0);
                                        $totalUnits = max((int) ($a['total_quantity'] ?? 0), $availableUnits);
                                        $maintenanceUnits = max($totalUnits - $availableUnits, 0);
                                        $isAvailable = ($availableUnits > 0);
                                        if ($isAvailable) $availableCount++;
                                        
                                        $statusClass = '';
                                        $statusText = $maintenanceUnits > 0 && $availableUnits > 0 ? 'Partially Available' : ucfirst($a['status']);
                                        switch($a['status']) {
                                            case 'available':
                                                $statusClass = 'status-available';
                                                break;
                                            case 'maintenance':
                                                $statusClass = 'status-maintenance';
                                                break;
                                            case 'faulty':
                                                $statusClass = 'status-faulty';
                                                break;
                                            default:
                                                $statusClass = 'status-unavailable';
                                        }
                                        ?>
                                        <tr data-asset-id="<?= esc($a['id']) ?>"
                                            data-service-id="<?= esc((string) ($a['lab_service_id'] ?? '')) ?>"
                                            data-status="<?= esc(strtolower($maintenanceUnits > 0 && $availableUnits > 0 ? 'partially available' : $a['status'])) ?>"
                                            data-quantity="<?= esc($availableUnits) ?>"
                                            class="<?= !$isAvailable ? 'text-muted' : '' ?>">
                                            <td class="equipment-checkbox" data-label="Select">
                                                <input type="checkbox"
                                                       class="form-check-input asset-checkbox"
                                                       data-asset-id="<?= esc($a['id']) ?>"
                                                       <?= !$isAvailable ? 'disabled' : '' ?>
                                                       <?= !$isAvailable ? 'title=\"Equipment is not available for booking\"' : '' ?>>
                                            </td>
                                            <td data-label="Equipment">
                                                <div class="equipment-name"><?= esc($a['name']) ?></div>
                                                <?php if (!empty($a['model'])): ?>
                                                    <div class="equipment-desc">Model: <?= esc($a['model']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($a['specifications'])): ?>
                                                    <div class="equipment-desc"><?= esc($a['specifications']) ?></div>
                                                <?php elseif (!empty($a['description'])): ?>
                                                    <div class="equipment-desc"><?= esc($a['description']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center" data-label="Status">
                                                <span class="equipment-status <?= $statusClass ?>">
                                                    <?= $statusText ?>
                                                </span>
                                            </td>
                                            <td class="text-center" data-label="Quantity">
                                                <span class="quantity-badge <?= !$isAvailable ? 'unavailable' : '' ?>">
                                                    <?= esc($availableUnits) ?> available / <?= esc($totalUnits) ?> total
                                                </span>
                                            </td>
                                            <td class="text-center" data-label="Request Qty">
                                                <input type="number"
                                                       class="form-control quantity-input asset-qty"
                                                       data-asset-id="<?= esc($a['id']) ?>"
                                                       value="<?= $isAvailable ? '1' : '0' ?>"
                                                       min="0"
                                                       max="<?= $isAvailable ? esc($availableUnits) : '0' ?>"
                                                       <?= !$isAvailable ? 'disabled' : '' ?>
                                                       <?= !$isAvailable ? 'title=\"Cannot request unavailable equipment\"' : '' ?>>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <!-- Equipment availability summary -->
                            <div class="equipment-legend" aria-label="Equipment availability legend">
                                <div class="equipment-legend-item">
                                    <span class="equipment-legend-dot status-available" aria-hidden="true"></span>
                                    <span>Available for booking</span>
                                    <span class="badge bg-primary equipment-legend-count"><?= $availableCount ?></span>
                                </div>
                                <div class="equipment-legend-item">
                                    <span class="equipment-legend-dot status-maintenance" aria-hidden="true"></span>
                                    <span>Under maintenance</span>
                                </div>
                                <div class="equipment-legend-item">
                                    <span class="equipment-legend-dot status-faulty" aria-hidden="true"></span>
                                    <span>Faulty/Not working</span>
                                </div>
                                <div class="equipment-legend-item">
                                    <span class="equipment-legend-dot status-unavailable" aria-hidden="true"></span>
                                    <span>Other status</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h2 class="h4 mb-1 text-primary">
                            <i class="bi bi-list-check me-2"></i>
                            Available Services
                        </h2>
                        <p class="text-muted mb-0">Choose the service you need. The system will align the linked equipment automatically.</p>
                    </div>
                    <span class="badge text-bg-primary px-3 py-2">
                        <?= count($services) ?> <?= count($services) === 1 ? 'Service' : 'Services' ?>
                    </span>
                </div>

                <?php if ($services === []): ?>
                    <div class="text-center text-muted py-4">
                        No services have been imported for this laboratory yet.
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($services as $service): ?>
                            <?php
                            $serviceCalibration = strtolower(trim((string) ($service['calibration_status'] ?? 'unknown')));
                            $calibrationClass = $serviceCalibration === 'valid'
                                ? 'text-bg-success'
                                : ($serviceCalibration === 'expired' ? 'text-bg-warning' : 'text-bg-secondary');
                            $equipmentModels = trim((string) ($service['equipment_models'] ?? ''));
                            $criteriaText = trim((string) ($service['acceptance_criteria'] ?? ''));
                            $requiredAssets = array_map(static function (array $asset): array {
                                return [
                                    'assetId' => (int) ($asset['asset_id'] ?? 0),
                                    'name' => (string) ($asset['name'] ?? ''),
                                    'quantityRequired' => (int) ($asset['quantity_required'] ?? 1),
                                    'isAvailable' => (bool) ($asset['is_available'] ?? false),
                                ];
                            }, $service['required_assets'] ?? []);
                            ?>
                            <div class="list-group-item px-0 py-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                    <div>
                                        <div class="fw-semibold"><?= esc($service['service_name'] ?? '') ?></div>
                                        <?php if (!empty($service['field_name'])): ?>
                                            <div class="small text-muted mt-1">
                                                <i class="bi bi-diagram-3 me-1"></i>
                                                <?= esc($service['field_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge <?= esc($calibrationClass) ?>">
                                        Calibration: <?= esc(ucfirst($serviceCalibration)) ?>
                                    </span>
                                </div>

                                <?php if ($equipmentModels !== ''): ?>
                                    <div class="small mt-2">
                                        <span class="fw-semibold">Equipment models:</span>
                                        <?= esc(str_replace(' | ', ', ', $equipmentModels)) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($criteriaText !== ''): ?>
                                    <div class="small text-muted mt-2">
                                        <span class="fw-semibold text-dark">Acceptance criteria:</span>
                                        <?= esc($criteriaText) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-3 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                    <button type="button"
                                            class="btn btn-outline-primary btn-sm select-service-btn"
                                            <?= empty($service['is_bookable']) ? 'disabled' : '' ?>
                                            data-service-id="<?= esc((string) ($service['id'] ?? '')) ?>"
                                            data-service-name="<?= esc($service['service_name'] ?? '') ?>"
                                            data-service-calibration="<?= esc(ucfirst($serviceCalibration)) ?>"
                                            data-service-equipment="<?= esc(str_replace(' | ', ', ', $equipmentModels)) ?>"
                                            data-service-criteria="<?= esc($criteriaText) ?>"
                                            data-service-assets="<?= esc(json_encode($requiredAssets), 'attr') ?>"
                                            data-service-bookable="<?= !empty($service['is_bookable']) ? '1' : '0' ?>">
                                        <i class="bi bi-check2-square me-1"></i>
                                        Choose Service
                                    </button>
                                    <div class="small text-muted service-state-label">
                                        <?= empty($service['is_bookable']) ? 'This service is currently unavailable.' : 'This service will drive equipment and slot selection.' ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Booking CTA Card -->
        <div class="booking-card">
            <h2 class="booking-title">
                <i class="bi bi-calendar-check"></i>
                <?php if ($bookingMode === 'uthm'): ?>
                    Ready to Book?
                <?php else: ?>
                    How to Book This Laboratory
                <?php endif; ?>
            </h2>
            
                <p class="booking-description">
                    <?php if ($bookingMode === 'uthm'): ?>
                        Start by choosing a service. The system will load the linked equipment, check availability,
                        and guide you into the booking wizard with the correct context.
                        <span class="text-danger fw-semibold d-block mt-1">Note: Only equipment marked as "Available" and linked to the chosen service can be booked.</span>
                    <?php elseif ($bookingMode === 'external'): ?>
                        Review the laboratory resources, then submit an external access request. The PIC will review your request and decide whether it can move forward for scheduling.
                    <?php else: ?>
                        You can browse equipment and view availability, but direct booking is reserved for UTHM users. Login with an external account to submit a request, or contact the PIC first.
                    <?php endif; ?>
                </p>
            
            <div class="booking-alert">
                <i class="bi bi-info-circle"></i>
                <p>Availability is calculated from the selected service and its linked equipment, and may vary between timeslots.</p>
            </div>
            
            <div class="booking-actions">
                <?php if ($bookingMode === 'uthm'): ?>
                    <button id="openBookingWizardBtn"
                            class="btn booking-btn"
                            data-lab-id="<?= esc($lab['id']) ?>"
                            disabled>
                        <i class="bi bi-magic me-1"></i>
                        Launch Booking Wizard (Select Service First)
                    </button>
                <?php elseif ($bookingMode === 'external'): ?>
                    <a href="/dashboard/external/request?lab_id=<?= esc($lab['id']) ?>" class="btn booking-btn">
                        <i class="bi bi-clipboard-check me-1"></i>
                        Request Lab Access
                    </a>
                <?php else: ?>
                    <a href="/login" class="btn booking-btn booking-btn-outline">
                        <i class="bi bi-box-arrow-in-right me-1"></i>
                        Login to Submit a Request
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Calendar Section -->
        <div class="calendar-card">
            <div class="calendar-header">
                <h2 class="calendar-title">
                    <i class="bi bi-calendar3"></i>
                    Laboratory Availability
                </h2>
            <div class="calendar-note">
                Choose a service above to load its linked equipment and real-time availability. Click on any date to see available timeslots.
                <span class="d-block text-danger small mt-1">Note: Equipment can still be booked when some units remain available, even if other units are under maintenance.</span>
            </div>
        </div>
            <div id="labCalendar"></div>
        </div>

    </div>
</div>

<!-- Include booking modal (adapts to bookingMode inside) -->
<?= $this->include('public/booking/booking_modal', [
    'lab'          => $lab,
    'faculties'    => $faculties,
    'bookingMode'  => $bookingMode,
    'userProfile'  => $userProfile ?? null
]) ?>

<!-- FULLCALENDAR -->
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const LAB_ID      = <?= (int) $lab['id'] ?>;
    const BOOKING_MODE = "<?= esc($bookingMode) ?>";

    const calendarEl       = document.getElementById("labCalendar");
    const assetCheckboxes  = document.querySelectorAll(".asset-checkbox");
    const openWizardBtn    = document.getElementById("openBookingWizardBtn");
    const hiddenAssetField = document.getElementById("asset_selection_modal");
    const hiddenLabIdInput = document.getElementById("labIdInput");
    const hiddenServiceInput = document.getElementById("service_id_modal");
    const selectedServiceSummary = document.getElementById("selectedServiceSummary");
    const serviceButtons = document.querySelectorAll(".select-service-btn");
    const todayDate = new Date();
    todayDate.setHours(0, 0, 0, 0);
    let selectedService = null;

    // -------------------------------
    // Check if equipment is available based on status
    // -------------------------------
    function isEquipmentAvailable(assetId) {
        const row = document.querySelector(`tr[data-asset-id="${assetId}"]`);
        if (!row) return false;

        const status = row.dataset.status || '';
        const qty = parseInt(row.dataset.quantity || '0', 10);

        return status !== 'maintenance' && status !== 'faulty' && qty > 0;
    }

    function getSelectedServiceId() {
        return selectedService && selectedService.id ? String(selectedService.id) : "";
    }

    function buildServiceInfo(button) {
        if (!button) return null;

        const id = button.dataset.serviceId || "";
        if (!id) return null;

        return {
            id,
            name: button.dataset.serviceName || "Selected Service",
            calibrationStatus: button.dataset.serviceCalibration || "",
            equipmentModels: button.dataset.serviceEquipment || "",
            acceptanceCriteria: button.dataset.serviceCriteria || "",
            requiredAssets: JSON.parse(button.dataset.serviceAssets || "[]"),
            isBookable: button.dataset.serviceBookable === "1",
        };
    }

    function syncServiceContextToModal() {
        const serviceId = getSelectedServiceId();
        if (hiddenServiceInput) {
            hiddenServiceInput.value = serviceId;
        }
        if (typeof window.updateBookingServiceContext === "function") {
            window.updateBookingServiceContext(selectedService);
        }
    }

    function updateServiceButtonStates(serviceId) {
        serviceButtons.forEach(button => {
            const isSelected = serviceId !== "" && button.dataset.serviceId === serviceId;
            button.classList.toggle("btn-primary", isSelected);
            button.classList.toggle("btn-outline-primary", !isSelected);
            button.innerHTML = isSelected
                ? '<i class="bi bi-check2-circle me-1"></i>Selected Service'
                : '<i class="bi bi-check2-square me-1"></i>Choose Service';

            const stateLabel = button.parentElement?.querySelector(".service-state-label");
            if (stateLabel) {
                stateLabel.textContent = isSelected
                    ? "This service is active for availability and booking."
                    : "This service will drive equipment and slot selection.";
            }
        });
    }

    function updateSelectedServiceSummary(linkedCount = 0, availableCount = 0) {
        if (!selectedServiceSummary) return;

        if (!selectedService) {
            selectedServiceSummary.className = "alert alert-info small mb-3";
            selectedServiceSummary.innerHTML = `
                <i class="bi bi-info-circle me-1"></i>
                Choose a service below to activate the correct equipment set for booking.
            `;
            return;
        }

        const meta = [];
        if (selectedService.calibrationStatus) {
            meta.push(`Calibration: ${selectedService.calibrationStatus}`);
        }
        if (selectedService.equipmentModels) {
            meta.push(`Equipment: ${selectedService.equipmentModels}`);
        }
        if (selectedService.acceptanceCriteria) {
            meta.push(`Criteria: ${selectedService.acceptanceCriteria}`);
        }

        const isReady = !!selectedService.isBookable;
        selectedServiceSummary.className = `alert ${isReady ? "alert-success" : "alert-warning"} small mb-3`;
        selectedServiceSummary.innerHTML = `
            <div class="fw-semibold mb-1">${selectedService.name}</div>
            <div>${linkedCount} required equipment item(s), ${availableCount} currently available.</div>
            ${meta.length ? `<div class="mt-1 text-muted">${meta.join(" | ")}</div>` : ""}
            ${!isReady ? `<div class="mt-1">This service cannot be booked right now because one or more required assets are unavailable.</div>` : ""}
        `;
    }

    function applyRowSelectionState(row, enabled, checked) {
        const checkbox = row.querySelector(".asset-checkbox");
        const qtyInput = row.querySelector(".asset-qty");
        const assetId = row.dataset.assetId || "";

        if (!checkbox || !qtyInput) return;

        if (!enabled) {
            checkbox.checked = false;
            checkbox.disabled = true;
            qtyInput.disabled = true;
            qtyInput.style.borderColor = "#e2e8f0";
            qtyInput.value = isEquipmentAvailable(assetId) ? 1 : 0;
            return;
        }

        checkbox.disabled = false;
        checkbox.checked = checked;

        if (checked && isEquipmentAvailable(assetId)) {
            qtyInput.disabled = false;
            qtyInput.style.borderColor = "#3b82f6";
            if (!qtyInput.value || parseInt(qtyInput.value, 10) < 1) {
                qtyInput.value = 1;
            }
        } else {
            qtyInput.disabled = true;
            qtyInput.style.borderColor = "#e2e8f0";
        }
    }

    function filterAssetsForService(serviceId) {
        const requirementMap = new Map((selectedService?.requiredAssets || []).map(asset => [String(asset.assetId), asset]));
        let linkedCount = 0;
        let availableCount = 0;

        document.querySelectorAll("tr[data-asset-id]").forEach(row => {
            const assetId = row.dataset.assetId || "";
            const requirement = requirementMap.get(assetId);
            const matches = serviceId !== "" && !!requirement;
            const available = !!requirement?.isAvailable;
            const qtyInput = row.querySelector(".asset-qty");

            row.classList.toggle("d-none", serviceId !== "" && !matches);

            if (!matches) {
                applyRowSelectionState(row, false, false);
                return;
            }

            linkedCount++;
            if (available) {
                availableCount++;
            }

            applyRowSelectionState(row, false, available);
            if (qtyInput) {
                qtyInput.value = String(requirement?.quantityRequired || 1);
            }
        });

        if (serviceId === "") {
            document.querySelectorAll("tr[data-asset-id]").forEach(row => {
                row.classList.remove("d-none");
            });
        }

        return { linkedCount, availableCount };
    }

    function selectService(service) {
        selectedService = service && service.id ? service : null;
        const serviceId = getSelectedServiceId();

        updateServiceButtonStates(serviceId);

        const { linkedCount, availableCount } = filterAssetsForService(serviceId);
        updateSelectedServiceSummary(linkedCount, availableCount);
        updateBookingButton();
        syncAssetSelectionToModal();
        syncServiceContextToModal();
        refreshCalendar();
    }

    function selectServiceById(serviceId) {
        if (!serviceId) return false;
        const button = document.querySelector(`.select-service-btn[data-service-id="${serviceId}"]`);
        if (!button) return false;

        selectService(buildServiceInfo(button));
        return true;
    }

    // -------------------------------
    // Build asset selection string
    // -------------------------------
    function buildAssetSelectionString() {
        if (!selectedService || !selectedService.isBookable || !Array.isArray(selectedService.requiredAssets)) {
            return "";
        }

        return selectedService.requiredAssets
            .filter(asset => asset && asset.isAvailable && asset.assetId && asset.quantityRequired > 0)
            .map(asset => `${asset.assetId}:${asset.quantityRequired}`)
            .join(",");
    }

    function syncAssetSelectionToModal() {
        if (!hiddenAssetField) return;
        hiddenAssetField.value = buildAssetSelectionString();
        window.dispatchEvent(new Event("assetSelectionUpdated"));
    }

    // -------------------------------
    // Get available assets count
    // -------------------------------
    function getAvailableAssetsCount() {
        if (!selectedService || !Array.isArray(selectedService.requiredAssets)) {
            return 0;
        }

        return selectedService.requiredAssets.filter(asset => asset && asset.isAvailable).length;
    }

    // -------------------------------
    // Update booking button state
    // -------------------------------
    function updateBookingButton() {
        if (!openWizardBtn) return;

        const availableCount = getAvailableAssetsCount();

        if (BOOKING_MODE === 'uthm') {
            if (!getSelectedServiceId()) {
                openWizardBtn.disabled = true;
                openWizardBtn.innerHTML = '<i class="bi bi-magic me-1"></i>Launch Booking Wizard (Select Service First)';
            } else if (selectedService?.isBookable && availableCount === (selectedService.requiredAssets || []).length) {
                openWizardBtn.disabled = false;
                openWizardBtn.innerHTML = '<i class="bi bi-magic me-1"></i>Launch Booking Wizard';
            } else {
                openWizardBtn.disabled = true;
                openWizardBtn.innerHTML = '<i class="bi bi-magic me-1"></i>Selected Service Currently Unavailable';
            }
        }
    }

    // -------------------------------
    // Enable/disable quantity inputs with animations
    // -------------------------------
    assetCheckboxes.forEach(cb => {
        cb.addEventListener("change", () => {
            const id = cb.dataset.assetId;
            const qtyInput = document.querySelector(`.asset-qty[data-asset-id="${id}"]`);
            const row = cb.closest('tr');
            
            if (!qtyInput || !row) return;

            if (cb.checked && isEquipmentAvailable(id)) {
                qtyInput.disabled = false;
                qtyInput.style.borderColor = '#3b82f6';
                if (!qtyInput.value || parseInt(qtyInput.value, 10) < 1) {
                    qtyInput.value = 1;
                }
            } else {
                qtyInput.disabled = true;
                qtyInput.style.borderColor = '#e2e8f0';
                if (!isEquipmentAvailable(id)) {
                    qtyInput.value = 0;
                }
            }

            updateBookingButton();
            refreshCalendar();
            syncAssetSelectionToModal();
        });
    });

    serviceButtons.forEach(button => {
        button.addEventListener("click", () => {
            selectService(buildServiceInfo(button));
        });
    });

    // Quantity input event listeners
    document.querySelectorAll(".asset-qty").forEach(input => {
        input.addEventListener("change", () => {
            const assetId = input.dataset.assetId;
            const row = document.querySelector(`tr[data-asset-id="${assetId}"]`);
            
            if (!isEquipmentAvailable(assetId)) {
                input.value = 0;
                return;
            }
            
            if (parseInt(input.value, 10) < 1) input.value = 1;
            const max = parseInt(input.max, 10);
            if (parseInt(input.value, 10) > max) input.value = max;
            
            refreshCalendar();
            syncAssetSelectionToModal();
        });
        
        input.addEventListener("input", () => {
            const assetId = input.dataset.assetId;
            if (!isEquipmentAvailable(assetId)) {
                input.value = 0;
            }
        });
    });

    // -------------------------------
    // FullCalendar setup with custom styling
    // -------------------------------
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: "dayGridMonth",
        height: "auto",
        eventDisplay: "block",
        headerToolbar: {
            left: "prev,next today",
            center: "title",
            right: "dayGridMonth,timeGridWeek,timeGridDay"
        },
        buttonText: {
            today: "Today",
            month: "Month",
            week: "Week",
            day: "Day"
        },
        dateClick: (info) => {
            const clickedDate = new Date(info.dateStr + "T00:00:00");
            if (clickedDate < todayDate) {
                return;
            }
            loadDaySlots(info.dateStr);
        },
        dayCellClassNames: (info) => {
            const cellDate = new Date(info.date.getFullYear(), info.date.getMonth(), info.date.getDate());
            return cellDate < todayDate ? ["fc-day-past-disabled"] : [];
        },
        eventDidMount: (info) => {
            // Add tooltip for events
            info.el.title = info.event.title;
        },
        datesSet: () => {
            // Refresh calendar when view changes
            refreshCalendar();
        }
    });

    calendar.render();

    // -------------------------------
    // Refresh availability on calendar
    // -------------------------------
    function refreshCalendar() {
        const serviceId = getSelectedServiceId();
        const assets = buildAssetSelectionString();
        if (!serviceId || !assets) {
            calendar.removeAllEvents();
            return;
        }

        // Show loading state
        calendar.updateSize();
        
        fetch(`/api/calendar-with-assets/${LAB_ID}?service_id=${encodeURIComponent(serviceId)}&assets=${encodeURIComponent(assets)}`)
            .then(r => r.json())
            .then(data => {
                calendar.removeAllEvents();

                (data.unavailableDates || []).forEach(date => {
                    calendar.addEvent({
                        start: date,
                        allDay: true,
                        title: "Fully Unavailable",
                        color: "#ef4444",
                        classNames: ["fc-event-unavailable"]
                    });
                });

                // Add available dates if needed
                if (data.availableDates && data.availableDates.length > 0) {
                    data.availableDates.forEach(date => {
                        calendar.addEvent({
                            start: date,
                            allDay: true,
                            title: "Available",
                            color: "#10b981",
                            classNames: ["fc-event-available"]
                        });
                    });
                }
            })
            .catch(() => {
                // Show error state
                calendar.addEvent({
                    title: "Error loading availability",
                    start: new Date(),
                    allDay: true,
                    color: "#f97316"
                });
            });
    }

    // -------------------------------
    // Load day slots for clicked date
    // -------------------------------
    function loadDaySlots(dateStr) {
        const serviceId = getSelectedServiceId();
        if (!serviceId) {
            showAlert("Please choose a service first.", "warning");
            return;
        }

        const assets = buildAssetSelectionString();
        if (!assets) {
            showAlert("No bookable equipment is linked to the selected service right now.", "warning");
            return;
        }

        // Show loading
        showLoadingPopup();

        fetch(`/api/bookings/day-with-assets/${LAB_ID}/${dateStr}?service_id=${encodeURIComponent(serviceId)}&assets=${encodeURIComponent(assets)}`)
            .then(r => r.json())
            .then(data => {
                showSlotPopup(dateStr, data.slots || []);
            })
            .catch(() => {
                showAlert("Unable to load timeslots for this date.", "error");
            });
    }

    // -------------------------------
    // Timeslot popup - Enhanced Design
    // -------------------------------
    function showSlotPopup(dateStr, slots) {
        const formattedDate = new Date(dateStr).toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        let html = `
            <div class="slot-popup">
                <div class="slot-header mb-4">
                    <h4 class="fw-bold text-primary mb-1">${formattedDate}</h4>
                    <p class="text-muted mb-0">Available timeslots for the selected service and linked equipment</p>
                </div>
        `;

        if (!slots.length) {
            html += `
                <div class="no-slots text-center py-4">
                    <i class="bi bi-calendar-x text-primary fs-1 mb-3"></i>
                    <p class="text-muted">No timeslots available for this date.</p>
                </div>
            `;
        }

        slots.forEach((slot, index) => {
            const colorClass = slot.can_book ? "slot-available" : "slot-unavailable";
            const statusText = slot.can_book ? "Available" : "Unavailable";
            const statusIcon = slot.can_book ? "bi-check-circle" : "bi-x-circle";

            html += `
                <div class="slot-item ${colorClass} mb-3">
                    <div class="slot-info">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <div>
                                <h6 class="fw-semibold mb-0">${slot.label}</h6>
                                <small class="text-muted">${slot.start} - ${slot.end}</small>
                            </div>
                            <span class="slot-status badge ${slot.can_book ? 'bg-success' : 'bg-secondary'}">
                                <i class="bi ${statusIcon} me-1"></i>${statusText}
                            </span>
                        </div>

                        <div class="slot-assets">
                            <small class="d-block mb-1"><strong>Equipment Status:</strong></small>
                            <ul class="mb-0 ps-3">
            `;

            (slot.assets || []).forEach(a => {
                const icon = a.remaining >= a.requested ? "bi-check-circle text-success" : "bi-x-circle text-danger";
                html += `
                    <li class="small">
                        <i class="bi ${icon} me-1"></i>
                        ${a.name}: Requested ${a.requested}, Available ${a.remaining}
                    </li>
                `;
            });

            html += `</ul>`;

            if (slot.can_book && BOOKING_MODE === "uthm") {
                html += `
                    <button class="btn btn-primary btn-sm w-100 mt-3"
                            type="button"
                            onclick="selectSlot('${dateStr}', '${slot.start}', '${slot.end}')">
                        <i class="bi bi-calendar-plus me-1"></i>Book This Slot
                    </button>
                `;
            } else if (slot.can_book && BOOKING_MODE === "external") {
                html += `
                    <a class="btn btn-outline-primary btn-sm w-100 mt-3"
                       href="/dashboard/external/request?lab_id=${LAB_ID}&preferred_date=${dateStr}&preferred_start_time=${slot.start}&preferred_end_time=${slot.end}">
                        <i class="bi bi-clipboard-plus me-1"></i>Request This Slot
                    </a>
                `;
            } else if (slot.can_book && BOOKING_MODE === "guest") {
                html += `
                    <div class="alert alert-warning small mt-3 mb-0">
                        <i class="bi bi-box-arrow-in-right me-1"></i>
                        Login with an external account to request this slot, or contact the PIC.
                    </div>
                `;
            }

            html += `</div></div></div>`;
        });

        html += `</div>`;

        const modal = document.createElement("div");
        modal.className = "modal fade";
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content shadow-lg" style="border-radius: 20px; border: none;">
                    <div class="modal-header border-0 pb-0">
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-0">${html}</div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        window.currentSlotModal = { instance: bsModal, element: modal };
        bsModal.show();

        modal.addEventListener("hidden.bs.modal", () => {
            if (window.currentSlotModal?.element === modal) {
                window.currentSlotModal = null;
            }
            modal.remove();
        });
    }

    function showLoadingPopup() {
        const modal = document.createElement("div");
        modal.className = "modal fade";
        modal.innerHTML = `
            <div class="modal-dialog modal-sm">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-body text-center py-4">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mb-0">Loading timeslots...</p>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        // Auto remove after 3 seconds if still showing
        setTimeout(() => {
            if (document.body.contains(modal)) {
                bsModal.hide();
                modal.remove();
            }
        }, 3000);
    }

    function showAlert(message, type = "info") {
        const alertClass = {
            info: "alert-info",
            warning: "alert-warning",
            error: "alert-danger",
            success: "alert-success"
        }[type];

        const alert = document.createElement("div");
        alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
        alert.style.cssText = `
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-radius: 12px;
            border: none;
        `;
        alert.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="bi ${type === 'warning' ? 'bi-exclamation-triangle' : type === 'error' ? 'bi-x-circle' : 'bi-info-circle'} me-2"></i>
                <div class="flex-grow-1">${message}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        document.body.appendChild(alert);
        
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }

    function showBookingModal() {
        const modalEl = document.getElementById("bookingModal");
        if (!modalEl || typeof bootstrap === "undefined") {
            return false;
        }

        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }

        const modal = new bootstrap.Modal(modalEl);
        modal.show();
        return true;
    }

    // -------------------------------------------------
    // Global function used by popup "Book This Slot"
    // -------------------------------------------------
    window.selectSlot = function(dateStr, start, end) {
        if (BOOKING_MODE !== "uthm") {
            showAlert("Online booking is only available for UTHM users.", "warning");
            return;
        }

        if (window.currentSlotModal?.instance) {
            window.currentSlotModal.instance.hide();
        }

        const bookingModalEl = document.getElementById("bookingModal");
        const dateField      = document.getElementById("selectedDate");
        const startField     = document.getElementById("startTime");
        const endField       = document.getElementById("endTime");
        const serviceId      = getSelectedServiceId();

        if (!serviceId) {
            showAlert("Please choose a service before booking a slot.", "warning");
            return;
        }

        const assetsString = buildAssetSelectionString();
        if (!assetsString) {
            showAlert("No bookable equipment is linked to the selected service right now.", "warning");
            return;
        }

        if (hiddenAssetField) hiddenAssetField.value = assetsString;
        if (hiddenLabIdInput) hiddenLabIdInput.value = LAB_ID;
        if (hiddenServiceInput) hiddenServiceInput.value = serviceId;
        syncServiceContextToModal();
        syncAssetSelectionToModal();

        if (dateField)  dateField.value  = dateStr;
        if (startField) startField.value = start;
        if (endField)   endField.value   = end;

        if (window.resetBookingWizard) {
            window.resetBookingWizard();
        }

        showBookingModal();
    };

    // -------------------------------------------------
    // "Launch Booking Wizard" button behaviour
    // -------------------------------------------------
    if (openWizardBtn) {
        openWizardBtn.addEventListener("click", () => {
            // Non-UTHM: show simple PIC info modal
            if (BOOKING_MODE !== "uthm") {
                const modalEl = document.getElementById("bookingModal");
                if (modalEl) {
                    showBookingModal();
                } else {
                    showAlert("For external bookings, please contact the Person in Charge shown above.", "info");
                }
                return;
            }

            // UTHM: normal booking wizard flow
            const serviceId = getSelectedServiceId();
            if (!serviceId) {
                showAlert("Please choose a service before proceeding.", "warning");
                return;
            }

            const assetsString = buildAssetSelectionString();
            if (!assetsString) {
                showAlert("No bookable equipment is linked to the selected service right now.", "warning");
                return;
            }

            if (hiddenAssetField) hiddenAssetField.value = assetsString;
            if (hiddenLabIdInput) hiddenLabIdInput.value = LAB_ID;
            if (hiddenServiceInput) hiddenServiceInput.value = serviceId;
            syncServiceContextToModal();
            syncAssetSelectionToModal();

            if (window.resetBookingWizard) {
                window.resetBookingWizard();
            }

            showBookingModal();
        });
    }

    function applyQrSelectionFromQuery() {
        const params = new URLSearchParams(window.location.search);
        const assetParam = params.get("asset");
        if (!assetParam) return false;

        const assetId = assetParam.replace(/[^0-9]/g, "");
        if (!assetId) return false;

        const row = document.querySelector(`tr[data-asset-id="${assetId}"]`);
        const checkbox = document.querySelector(`.asset-checkbox[data-asset-id="${assetId}"]`);
        const qtyInput = document.querySelector(`.asset-qty[data-asset-id="${assetId}"]`);

        if (!checkbox) {
            showAlert("Selected equipment is not listed in this laboratory.", "warning");
            return false;
        }

        const rowServiceId = row?.dataset.serviceId || "";
        if (!rowServiceId || !selectServiceById(rowServiceId)) {
            showAlert("Selected equipment is not linked to a bookable service.", "warning");
            return false;
        }

        if (!isEquipmentAvailable(assetId)) {
            showAlert("Selected equipment is not available for booking right now.", "warning");
            return false;
        }

        checkbox.checked = true;

        if (qtyInput) {
            const maxRaw = parseInt(qtyInput.max || "1", 10);
            const requestedRaw = parseInt(params.get("qty") || "1", 10);
            const max = Number.isNaN(maxRaw) ? 1 : maxRaw;
            const requested = Number.isNaN(requestedRaw) ? 1 : requestedRaw;
            const safeQty = Math.min(Math.max(requested, 1), max);

            qtyInput.disabled = false;
            qtyInput.value = safeQty;
        }

        updateBookingButton();
        refreshCalendar();
        syncAssetSelectionToModal();

        const openWizard = params.get("open") === "1";
        if (!openWizard) return true;

        if (BOOKING_MODE === "external") {
            window.location.href = `/dashboard/external/request?lab_id=${LAB_ID}`;
            return true;
        }

        if (BOOKING_MODE === "guest") {
            showAlert("Login with an external account to submit a lab access request.", "warning");
            return true;
        }

        const assetsString = buildAssetSelectionString();
        if (hiddenAssetField) hiddenAssetField.value = assetsString;
        if (hiddenLabIdInput) hiddenLabIdInput.value = LAB_ID;
        if (hiddenServiceInput) hiddenServiceInput.value = rowServiceId;
        syncServiceContextToModal();

        if (window.resetBookingWizard) {
            window.resetBookingWizard();
        }

        showBookingModal();

        return true;
    }

    const qrApplied = applyQrSelectionFromQuery();
    // Initial calendar load
    if (!qrApplied) {
        if (serviceButtons.length === 1) {
            selectService(buildServiceInfo(serviceButtons[0]));
        } else {
            filterAssetsForService("");
            updateSelectedServiceSummary(0, 0);
            syncServiceContextToModal();
        }
        refreshCalendar();
        updateBookingButton();
    }
});
</script>

<?= $this->endSection() ?>
