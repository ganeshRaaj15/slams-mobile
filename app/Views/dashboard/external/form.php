<?= $this->extend('layouts/main_user') ?>

<?= $this->section('content') ?>

<?php
$mode = $mode ?? 'create';
$requestRecord = $requestRecord ?? [];
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? '/dashboard/external/request/update/' . (int) ($requestRecord['id'] ?? 0) : '/dashboard/external/request/store';
$requestModel = $requestModel ?? null;
$currentStatus = (string) ($requestRecord['status'] ?? 'pending_pic_approval');
$selectedServiceId = (int) ($requestRecord['service_id'] ?? 0);
$selectedAssets = (string) ($requestRecord['selected_assets'] ?? '');
?>

<div class="dashboard-header">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-0"><?= $isEdit ? 'Update External Request' : 'Request Lab Access' ?></h2>
            <p class="text-muted small mb-0">This form starts a staged approval flow. It does not directly reserve the laboratory.</p>
        </div>
        <a href="/dashboard/external" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Requests
        </a>
    </div>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<?php if ($isEdit && !empty($requestRecord['review_notes'])): ?>
    <div class="alert alert-warning">
        <strong>Latest reviewer note:</strong><br>
        <?= nl2br(esc($requestModel ? $requestModel->latestRequesterNote($requestRecord) : ($requestRecord['review_notes'] ?? ''))) ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form action="<?= esc($actionUrl) ?>" method="post" class="row g-4" id="externalRequestForm">
            <?= csrf_field() ?>
            <input type="hidden" name="selected_assets" id="externalSelectedAssets" value="<?= esc(old('selected_assets', $selectedAssets)) ?>">

            <div class="col-md-6">
                <label class="form-label">Laboratory *</label>
                <select name="lab_id" class="form-select" id="externalLabId" required>
                    <option value="">Select a laboratory</option>
                    <?php foreach (($labs ?? []) as $lab): ?>
                        <option value="<?= esc($lab['id']) ?>" <?= (string) old('lab_id', $requestRecord['lab_id'] ?? '') === (string) $lab['id'] ? 'selected' : '' ?>>
                            <?= esc($lab['name']) ?><?= !empty($lab['room']) ? ' (' . esc($lab['room']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Current Status</label>
                <input type="text" class="form-control" value="<?= esc($requestModel ? $requestModel->statusLabel($currentStatus) : ucfirst($currentStatus)) ?>" readonly>
                <div class="form-text">You will receive a notification and email whenever the PIC or Lab Manager updates this request.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Organization / Institution *</label>
                <input type="text" name="organization_name" class="form-control" maxlength="255" value="<?= esc(old('organization_name', $requestRecord['organization_name'] ?? '')) ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Contact Name *</label>
                <input type="text" name="contact_name" class="form-control" maxlength="255" value="<?= esc(old('contact_name', $requestRecord['contact_name'] ?? '')) ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Contact Email *</label>
                <input type="email" name="contact_email" class="form-control" maxlength="255" value="<?= esc(old('contact_email', $requestRecord['contact_email'] ?? '')) ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Contact Phone *</label>
                <input type="text" name="contact_phone" class="form-control" maxlength="50" value="<?= esc(old('contact_phone', $requestRecord['contact_phone'] ?? '')) ?>" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Participant Count *</label>
                <input type="number" name="participant_count" class="form-control" min="1" value="<?= esc(old('participant_count', $requestRecord['participant_count'] ?? 1)) ?>" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Preferred Date *</label>
                <input type="date" name="preferred_date" id="externalPreferredDate" class="form-control" min="<?= esc(date('Y-m-d')) ?>" value="<?= esc(old('preferred_date', $requestRecord['preferred_date'] ?? '')) ?>" required>
            </div>

            <div class="col-md-8">
                <label class="form-label">Preferred Slot *</label>
                <input type="hidden" name="preferred_start_time" id="externalPreferredStartTime" value="<?= esc(old('preferred_start_time', $requestRecord['preferred_start_time'] ?? '')) ?>">
                <input type="hidden" name="preferred_end_time" id="externalPreferredEndTime" value="<?= esc(old('preferred_end_time', $requestRecord['preferred_end_time'] ?? '')) ?>">
                <div class="form-control bg-light d-flex align-items-center" id="externalSlotSummary" style="min-height: 48px;">
                    Choose a laboratory and date to load the configured booking slots.
                </div>
                <div id="externalSlotFeedback" class="mt-2"></div>
                <div id="externalSlotChoices" class="d-flex flex-wrap gap-2 mt-2"></div>
                <div class="form-text">External requests use the same configured booking sessions as student bookings. The slot is only reserved after final approval.</div>
            </div>

            <div class="col-12">
                <label class="form-label">Service Bundle *</label>
                <input type="hidden" name="service_id" id="externalServiceId" value="<?= esc((string) old('service_id', $selectedServiceId > 0 ? $selectedServiceId : '')) ?>">
                <div id="externalServiceChoices" class="d-flex flex-wrap gap-2"></div>
                <div id="externalServiceSummary" class="form-text mt-2">Choose a laboratory to load its available bundled services.</div>
            </div>

            <div class="col-12">
                <label class="form-label">Purpose of Use *</label>
                <textarea name="purpose" class="form-control" rows="5" required><?= esc(old('purpose', $requestRecord['purpose'] ?? '')) ?></textarea>
                <div class="form-text">Explain what you need to do in the laboratory, why the lab is required, and any timing constraints.</div>
            </div>

            <div class="col-12">
                <label class="form-label">Equipment / Setup Notes</label>
                <textarea name="equipment_notes" class="form-control" rows="4"><?= esc(old('equipment_notes', $requestRecord['equipment_notes'] ?? '')) ?></textarea>
                <div class="form-text">List the equipment, workstation setup, or environmental requirements the PIC should know.</div>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2">
                <a href="/dashboard/external" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update Request' : 'Submit Request' ?></button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const labField = document.getElementById("externalLabId");
    const dateField = document.getElementById("externalPreferredDate");
    const startField = document.getElementById("externalPreferredStartTime");
    const endField = document.getElementById("externalPreferredEndTime");
    const summaryEl = document.getElementById("externalSlotSummary");
    const feedbackEl = document.getElementById("externalSlotFeedback");
    const slotChoicesEl = document.getElementById("externalSlotChoices");
    const serviceIdField = document.getElementById("externalServiceId");
    const selectedAssetsField = document.getElementById("externalSelectedAssets");
    const serviceChoicesEl = document.getElementById("externalServiceChoices");
    const serviceSummaryEl = document.getElementById("externalServiceSummary");

    if (!labField || !dateField || !startField || !endField || !summaryEl || !feedbackEl || !slotChoicesEl || !serviceIdField || !selectedAssetsField || !serviceChoicesEl || !serviceSummaryEl) {
        return;
    }

    let selectedStart = startField.value || "";
    let selectedEnd = endField.value || "";
    let selectedServiceId = serviceIdField.value || "";
    let loadedServices = [];

    function renderFeedback(message, type = "info") {
        if (!message) {
            feedbackEl.innerHTML = "";
            return;
        }

        const className = type === "warning" ? "alert-warning" : type === "success" ? "alert-success" : "alert-info";
        feedbackEl.innerHTML = `<div class="alert ${className} small mb-0">${message}</div>`;
    }

    function renderSummary(label) {
        summaryEl.textContent = label;
    }

    function activeService() {
        return loadedServices.find((service) => String(service.id) === String(selectedServiceId)) || null;
    }

    function updateServiceSummary() {
        const service = activeService();
        if (!service) {
            serviceSummaryEl.textContent = "Choose a laboratory to load its available bundled services.";
            selectedAssetsField.value = "";
            return;
        }

        selectedAssetsField.value = service.bundle_summary || service.equipment_models || "";
        serviceSummaryEl.textContent = service.is_bookable === false
            ? `Selected service bundle is currently unavailable. ${service.bundle_summary || service.equipment_models || ''}`.trim()
            : `Selected bundle: ${service.bundle_summary || service.equipment_models || 'Configured service bundle'}`;
    }

    function renderServiceChoices(services) {
        loadedServices = Array.isArray(services) ? services : [];
        if (!loadedServices.length) {
            serviceChoicesEl.innerHTML = '<div class="alert alert-warning small mb-0">No bundled services are configured for this laboratory.</div>';
            selectedServiceId = "";
            serviceIdField.value = "";
            updateServiceSummary();
            return;
        }

        serviceChoicesEl.innerHTML = loadedServices.map((service) => {
            const selected = String(service.id) === String(selectedServiceId);
            const unavailable = service.is_bookable === false;
            const btnClass = unavailable ? 'btn-outline-secondary' : (selected ? 'btn-primary' : 'btn-outline-primary');
            const disabled = unavailable ? 'disabled' : '';
            const meta = service.bundle_summary || service.equipment_models || 'Configured service bundle';

            return `
                <button
                    type="button"
                    class="btn ${btnClass} text-start external-service-choice"
                    data-service-id="${service.id}"
                    ${disabled}
                >
                    <div class="fw-semibold">${service.service_name || 'Service'}</div>
                    <div class="small">${meta}</div>
                    ${unavailable ? '<div class="small text-warning">Currently unavailable</div>' : ''}
                </button>
            `;
        }).join("");

        const active = activeService();
        if (!active || active.is_bookable === false) {
            selectedServiceId = "";
            serviceIdField.value = "";
        }
        updateServiceSummary();
        if (loadedServices.length && !selectedServiceId) {
            slotChoicesEl.innerHTML = "";
            setSelectedSlot(null);
            renderFeedback("Choose a bundled service before selecting a booking slot.", "info");
            renderSummary("Choose a bundled service to load slot availability.");
        }
    }

    function setSelectedSlot(slot) {
        selectedStart = slot?.start || "";
        selectedEnd = slot?.end || "";
        startField.value = selectedStart;
        endField.value = selectedEnd;

        if (!slot) {
            renderSummary("Choose one of the available booking slots for the selected date.");
            return;
        }

        renderSummary(`${slot.label || `${slot.start}-${slot.end}`} | ${slot.start}-${slot.end}`);
    }

    function renderSlotChoices(slots) {
        if (!slots.length) {
            slotChoicesEl.innerHTML = "";
            renderFeedback("No configured booking slots are available for this date.", "warning");
            return;
        }

        slotChoicesEl.innerHTML = slots.map((slot) => {
            const isSelected = selectedStart === slot.start && selectedEnd === slot.end;
            const buttonClass = slot.can_book
                ? (isSelected ? "btn-primary" : "btn-outline-success")
                : "btn-outline-secondary";
            const disabledAttr = slot.can_book ? "" : "disabled";
            const meta = slot.can_book ? `${slot.start}-${slot.end}` : (slot.reason || "Unavailable");

            return `
                <button
                    type="button"
                    class="btn ${buttonClass} text-start external-slot-choice"
                    data-start="${slot.start}"
                    data-end="${slot.end}"
                    data-label="${slot.label || `${slot.start}-${slot.end}`}"
                    ${disabledAttr}
                >
                    <div class="fw-semibold">${slot.label || `${slot.start}-${slot.end}`}</div>
                    <div class="small">${meta}</div>
                </button>
            `;
        }).join("");
    }

    async function loadSlots(preserveCurrentSelection = false) {
        const labId = labField.value;
        const preferredDate = dateField.value;
        const serviceId = serviceIdField.value;

        if (!labId || !preferredDate) {
            slotChoicesEl.innerHTML = "";
            if (!preserveCurrentSelection) {
                setSelectedSlot(null);
            }
            renderFeedback("");
            renderSummary("Choose a laboratory and date to load the configured booking slots.");
            return;
        }

        if (loadedServices.length && !serviceId) {
            slotChoicesEl.innerHTML = "";
            if (!preserveCurrentSelection) {
                setSelectedSlot(null);
            }
            renderFeedback("Choose a bundled service before selecting a booking slot.", "info");
            renderSummary("Choose a bundled service to load slot availability.");
            return;
        }

        if (!preserveCurrentSelection) {
            setSelectedSlot(null);
        }

        renderFeedback("Loading configured booking slots...");
        slotChoicesEl.innerHTML = "";

        try {
            const url = `/dashboard/external/request/slots/${encodeURIComponent(labId)}/${encodeURIComponent(preferredDate)}?service_id=${encodeURIComponent(serviceId || '')}`;
            const response = await fetch(url);
            const data = await response.json();
            const slots = Array.isArray(data.slots) ? data.slots : [];

            if ((selectedStart || selectedEnd) && !preserveCurrentSelection) {
                selectedStart = "";
                selectedEnd = "";
            }

            const matchingSlot = slots.find((slot) => slot.start === selectedStart && slot.end === selectedEnd);
            if (matchingSlot && matchingSlot.can_book) {
                setSelectedSlot(matchingSlot);
                renderFeedback("Selected slot is available.", "success");
            } else if (matchingSlot && !matchingSlot.can_book) {
                setSelectedSlot(null);
                renderFeedback(matchingSlot.reason || "Selected slot is no longer available. Please choose another slot.", "warning");
            } else if (selectedStart || selectedEnd) {
                setSelectedSlot(null);
                renderFeedback("Please choose one of the configured booking slots for this date.", "warning");
            } else {
                renderFeedback("Choose one of the available booking slots below.");
            }

            renderSlotChoices(slots);
        } catch (_error) {
            slotChoicesEl.innerHTML = "";
            renderFeedback("Could not load booking slots right now. Please try again.", "warning");
            renderSummary("Choose a laboratory and date to load the configured booking slots.");
        }
    }

    async function loadServices() {
        const labId = labField.value;
        if (!labId) {
            loadedServices = [];
            serviceChoicesEl.innerHTML = "";
            selectedServiceId = "";
            serviceIdField.value = "";
            updateServiceSummary();
            return;
        }

        serviceChoicesEl.innerHTML = '<div class="alert alert-info small mb-0">Loading bundled services...</div>';

        try {
            const response = await fetch(`/dashboard/external/request/services/${encodeURIComponent(labId)}`);
            const data = await response.json();
            renderServiceChoices(Array.isArray(data.services) ? data.services : []);
        } catch (_error) {
            loadedServices = [];
            serviceChoicesEl.innerHTML = '<div class="alert alert-warning small mb-0">Could not load services right now.</div>';
            selectedServiceId = "";
            serviceIdField.value = "";
            updateServiceSummary();
        }
    }

    labField.addEventListener("change", () => {
        selectedStart = "";
        selectedEnd = "";
        selectedServiceId = "";
        serviceIdField.value = "";
        selectedAssetsField.value = "";
        loadServices();
        loadSlots();
    });

    dateField.addEventListener("change", () => {
        selectedStart = "";
        selectedEnd = "";
        loadSlots();
    });

    slotChoicesEl.addEventListener("click", (event) => {
        const button = event.target.closest(".external-slot-choice");
        if (!button || button.disabled) {
            return;
        }

        setSelectedSlot({
            label: button.dataset.label || "",
            start: button.dataset.start || "",
            end: button.dataset.end || "",
        });

        renderFeedback("Selected slot is available.", "success");
        loadSlots(true);
    });

    serviceChoicesEl.addEventListener("click", (event) => {
        const button = event.target.closest(".external-service-choice");
        if (!button || button.disabled) {
            return;
        }

        selectedServiceId = button.dataset.serviceId || "";
        serviceIdField.value = selectedServiceId;
        selectedStart = "";
        selectedEnd = "";
        startField.value = "";
        endField.value = "";
        renderServiceChoices(loadedServices);
        loadSlots();
    });

    if (selectedStart && selectedEnd && labField.value && dateField.value) {
        renderSummary("Validating the preselected booking slot...");
        loadServices();
        loadSlots(true);
        return;
    }

    renderSummary("Choose a laboratory and date to load the configured booking slots.");
    if (labField.value && dateField.value) {
        loadServices();
        loadSlots();
    } else if (labField.value) {
        loadServices();
    }
});
</script>

<?= $this->endSection() ?>
