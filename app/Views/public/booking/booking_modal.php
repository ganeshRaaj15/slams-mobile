<?php
/**
 * @var array $lab
 * @var array $faculties
 * @var array|null $userProfile
 * @var string|null $bookingMode
 */
$mode = $bookingMode ?? 'guest';
$userProfile = $userProfile ?? null;
$defaultFacultyId = $userProfile['faculty_id'] ?? null;

$picName = trim((string)($lab['pic_name'] ?? ''));
$picEmail = trim((string)($lab['pic_email'] ?? ''));
$picPhone = trim((string)($lab['pic_phone'] ?? ''));

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

<?php if ($mode !== 'uthm'): ?>

<!-- SIMPLE MODAL FOR EXTERNAL / GUEST USERS -->
<div class="modal fade" id="bookingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content shadow-lg border-0">

            <div class="modal-header" style="background: var(--fkmp-maroon); color:white;">
                <div>
                    <h5 class="modal-title fw-bold">Booking Information</h5>
                    <small class="opacity-75">
                        Online booking is only available for UTHM users.
                    </small>
                </div>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <p class="small text-muted mb-3">
                    You can browse laboratories and equipment here. External users should submit a lab access request after login, while guests should contact the Person-in-Charge (PIC) or register first.
                </p>

                <div class="card border-0 shadow-sm">
                    <div class="card-body d-flex gap-3 align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle overflow-hidden"
                                 style="width:72px;height:72px;background:#e5f0ff;display:flex;align-items:center;justify-content:center;">
                                <?php if (! empty($lab['pic_image'])): ?>
                                    <img src="<?= esc($lab['pic_image']) ?>"
                                         alt="<?= esc($picName) ?>"
                                         class="w-100 h-100"
                                         style="object-fit: cover;">
                                <?php else: ?>
                                    <i class="bi bi-person-gear fs-2 text-primary"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <div class="small text-muted mb-1">Person in Charge (PIC)</div>
                            <h6 class="fw-semibold mb-1"><?= esc($picName) ?></h6>

                            <div class="small mb-1">
                                <i class="bi bi-envelope me-1"></i>
                                <?php if ($picEmail !== 'null'): ?>
                                    <a href="mailto:<?= esc($picEmail) ?>"><?= esc($picEmail) ?></a>
                                <?php else: ?>
                                    <?= esc($picEmail) ?>
                                <?php endif; ?>
                            </div>

                            <div class="small">
                                <i class="bi bi-telephone me-1"></i><?= esc($picPhone) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="small text-muted mt-3 mb-0">
                    External access still follows FKMP safety and procedural requirements as communicated by the PIC.
                </p>
            </div>

            <div class="modal-footer border-0">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>

<?php else: ?>

<!-- ==========================================================
     FULL BOOKING WIZARD (UTHM USERS ONLY)
=========================================================== -->
<div class="modal fade" id="bookingModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">

            <!-- HEADER -->
            <div class="modal-header" style="background: var(--fkmp-maroon); color:white;">
                <div>
                    <h5 class="modal-title fw-bold">Laboratory Booking Wizard</h5>
                    <small class="opacity-75">Complete all steps to submit your booking.</small>
                </div>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body">

                <!-- Progress -->
                <div class="mb-4">
                    <div class="progress" style="height: 8px;">
                        <div id="wizardProgress" class="progress-bar bg-primary" style="width: 33%;"></div>
                    </div>
                    <div id="wizardStepLabel" class="text-center mt-2 small fw-semibold text-primary">
                        Step 1 of 3 - Applicant Details
                    </div>
                </div>

                <!-- ERROR AREA -->
                <div id="wizardErrorArea"></div>

                <!-- FORM -->
                <form id="bookingForm">
                    <?= csrf_field() ?>

                    <!-- Hidden Inputs -->
                    <input type="hidden" name="asset_selection" id="asset_selection_modal">
                    <input type="hidden" name="lab_id" id="labIdInput">
                    <input type="hidden" name="service_id" id="service_id_modal">
                    <!-- user_type is implied as UTHM on server-side; no need for hidden field -->

                    <div id="selectedServicePanel" class="alert alert-info small mb-4 d-none">
                        <div class="fw-semibold mb-1">Selected Service</div>
                        <div id="selectedServiceName" class="mb-1">No service selected.</div>
                        <div id="selectedServiceMeta" class="text-muted"></div>
                    </div>

                    <!-- ====================== STEP 1 ====================== -->
                    <div id="step1" class="wizard-step">
                        <h5 class="fw-semibold mb-3">Applicant Information</h5>

                        <div id="applicantContainer">
                            <div class="card p-3 mb-3 position-relative applicant-block">

                                <button class="btn btn-sm btn-danger remove-applicant-btn"
                                        type="button"
                                        style="position:absolute; top:10px; right:10px; display:none;">
                                    <i class="bi bi-x"></i>
                                </button>

                                <h6 class="fw-semibold small mb-3 applicant-title">Applicant 1</h6>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="small mb-1">Name *</label>
                                        <input type="text" name="applicant_name[]"
                                               class="form-control required-field uthm-only">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="small mb-1">Matric / Staff ID *</label>
                                        <input type="text" name="applicant_id[]"
                                               class="form-control required-field uthm-only">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="small mb-1">Email *</label>
                                        <input type="email" name="applicant_email[]"
                                               class="form-control required-field uthm-only">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="small mb-1">Phone *</label>
                                        <input type="text" name="applicant_phone[]"
                                               class="form-control required-field uthm-only">
                                    </div>

                                    <div class="col-12">
                                        <label class="small mb-1">Faculty / Organisation *</label>
                                        <select name="applicant_faculty[]"
                                                class="form-control required-field uthm-only">
                                            <option value="">Select Faculty</option>
                                            <?php foreach ($faculties as $f): ?>
                                                <option value="<?= esc($f['id']) ?>" <?= ($defaultFacultyId && (int)$defaultFacultyId === (int)$f['id']) ? 'selected' : '' ?>>
                                                    <?= esc($f['code']) ?> - <?= esc($f['name_en']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <button type="button" id="addApplicant" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-person-plus me-1"></i> Add Applicant
                        </button>
                    </div>


                    <!-- ====================== STEP 2 ====================== -->
                    <div id="step2" class="wizard-step d-none">
                        <h5 class="fw-semibold mb-3">Date &amp; Time</h5>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="small mb-1">Date *</label>
                                <input type="text" id="selectedDate" name="date"
                                       class="form-control required-field" readonly>
                            </div>

                            <div class="col-md-4">
                                <label class="small mb-1">Start Time *</label>
                                <input type="time" id="startTime" name="start_time"
                                       class="form-control required-field">
                            </div>

                            <div class="col-md-4">
                                <label class="small mb-1">End Time *</label>
                                <input type="time" id="endTime" name="end_time"
                                       class="form-control required-field">
                            </div>
                        </div>

                        <div id="recommendedSlots" class="mb-2"></div>
                        <div id="slotConflictWarning"></div>
                    </div>


                    <!-- ====================== STEP 3 ====================== -->
                    <div id="step3" class="wizard-step d-none">
                        <h5 class="fw-semibold mb-3">Activity &amp; Supervisor</h5>

                        <div class="card p-3 mb-3">
                            <h6 class="fw-semibold small mb-2">Supervisor (Students Only)</h6>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="small mb-1">Supervisor Name</label>
                                    <input type="text" name="supervisor_name" class="form-control">
                                </div>

                                <div class="col-md-6">
                                    <label class="small mb-1">Supervisor Email</label>
                                    <input type="email" name="supervisor_email" class="form-control">
                                </div>

                                <div class="col-md-6">
                                    <label class="small mb-1">Supervisor Phone</label>
                                    <input type="text" name="supervisor_phone" class="form-control">
                                </div>
                            </div>
                        </div>

                        <label class="small mb-1 fw-semibold">Activity Description *</label>
                        <textarea name="activity" rows="4"
                                  class="form-control required-field mb-3"></textarea>

                        <label class="small mb-1 fw-semibold">Upload PDF (SOP/SWP/SDS) *</label>
                        <input type="file" name="pdf" class="form-control required-field">
                    </div>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer border-0">
                <button id="prevBtn" class="btn btn-outline-secondary d-none" type="button">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </button>

                <button id="nextBtn" class="btn btn-primary" type="button">
                    Next <i class="bi bi-arrow-right ms-1"></i>
                </button>

                <button id="submitBtn" class="btn btn-success d-none" form="bookingForm">
                    <i class="bi bi-check-circle me-1"></i> Submit Booking
                </button>
            </div>

        </div>
    </div>
</div>

<?php endif; ?>  <!-- end mode switch -->


<?php if ($mode === 'uthm'): ?>
<!-- ============================================================
     BOOKING WIZARD JAVASCRIPT (UTHM ONLY)
=============================================================== -->
<script>
document.addEventListener("DOMContentLoaded", () => {

    let currentStep = 1;

    const form        = document.getElementById("bookingForm");
    const prevBtn     = document.getElementById("prevBtn");
    const nextBtn     = document.getElementById("nextBtn");
    const submitBtn   = document.getElementById("submitBtn");
    const wizardLabel = document.getElementById("wizardStepLabel");
    const wizardProg  = document.getElementById("wizardProgress");
    const errorArea   = document.getElementById("wizardErrorArea");

    const dateField   = document.getElementById("selectedDate");
    const startField  = document.getElementById("startTime");
    const endField    = document.getElementById("endTime");
    const conflictEl  = document.getElementById("slotConflictWarning");
    const recommendEl = document.getElementById("recommendedSlots");
    const serviceField = document.getElementById("service_id_modal");
    const servicePanel = document.getElementById("selectedServicePanel");
    const serviceNameEl = document.getElementById("selectedServiceName");
    const serviceMetaEl = document.getElementById("selectedServiceMeta");

    const defaultFacultyId = "<?= esc($defaultFacultyId ?? '') ?>";
    const csrfTokenName = "<?= csrf_token() ?>";
    const csrfTokenValue = "<?= csrf_hash() ?>";

    const labels = {
        1: "Step 1 of 3 - Applicant Details",
        2: "Step 2 of 3 - Date & Time",
        3: "Step 3 of 3 - Activity & Supervisor"
    };

    const widths = {1:33, 2:66, 3:100};
    let slotConflict = null;
    let currentServiceContext = null;

    function shouldShowRecommendations() {
        return (!dateField.value && !startField.value && !endField.value);
    }

    function renderSelectedServiceSummary(service) {
        if (!servicePanel || !serviceNameEl || !serviceMetaEl) return;

        if (!service || !service.id) {
            servicePanel.classList.add("d-none");
            serviceNameEl.textContent = "No service selected.";
            serviceMetaEl.textContent = "";
            return;
        }

        const metaParts = [];
        if (service.calibrationStatus) {
            metaParts.push(`Calibration: ${service.calibrationStatus}`);
        }
        if (service.equipmentModels) {
            metaParts.push(`Equipment: ${service.equipmentModels}`);
        }
        if (service.acceptanceCriteria) {
            metaParts.push(`Criteria: ${service.acceptanceCriteria}`);
        }

        serviceNameEl.textContent = service.name || "Selected service";
        serviceMetaEl.textContent = metaParts.join(" | ");
        servicePanel.classList.remove("d-none");
    }


    // ----------------------------
    // Error helpers
    // ----------------------------
    function showError(msg) {
        errorArea.innerHTML = `
            <div class="alert alert-danger small mb-2">
                <i class="bi bi-exclamation-triangle me-1"></i>${msg}
            </div>
        `;
    }

    function clearError() {
        errorArea.innerHTML = "";
    }

    function renderConflictWarning(message, type = "warning") {
        if (!conflictEl) return;
        if (!message) {
            conflictEl.innerHTML = "";
            return;
        }

        const cls = type === "success" ? "alert-success" : "alert-warning";
        const icon = type === "success" ? "bi-check-circle" : "bi-exclamation-triangle";
        conflictEl.innerHTML = `
            <div class="alert ${cls} small mb-2">
                <i class="bi ${icon} me-1"></i>${message}
            </div>
        `;
    }

    function formatDateLabel(dateStr) {
        const d = new Date(dateStr + "T00:00:00");
        return d.toLocaleDateString("en-US", {
            weekday: "short",
            month: "short",
            day: "numeric"
        });
    }

    async function checkSlotConflict() {
        const assetString = document.getElementById("asset_selection_modal")?.value || "";
        const labId = document.getElementById("labIdInput")?.value || "";
        const serviceId = serviceField?.value || "";

        if (!serviceId) {
            slotConflict = null;
            renderConflictWarning("Choose a service before checking slot availability.");
            return false;
        }

        if (!assetString) {
            slotConflict = null;
            renderConflictWarning("No linked equipment is available for the selected service.");
            return false;
        }

        if (!labId || !dateField.value || !startField.value || !endField.value) {
            slotConflict = null;
            renderConflictWarning("");
            return true;
        }

        const fd = new FormData();
        fd.append("lab_id", labId);
        fd.append("service_id", serviceId);
        fd.append("date", dateField.value);
        fd.append("start_time", startField.value);
        fd.append("end_time", endField.value);
        fd.append("asset_selection", assetString);
        fd.append(csrfTokenName, csrfTokenValue);

        try {
            const res = await fetch("/api/bookings/check-slot", {
                method: "POST",
                headers: {"X-Requested-With": "XMLHttpRequest"},
                body: fd
            });
            const data = await res.json();
            slotConflict = !!data.conflict;

            if (slotConflict) {
                const reason = data.reason ? ` ${data.reason}` : "";
                renderConflictWarning(`Selected slot is not available.${reason}`);
                return false;
            }

            renderConflictWarning("Selected slot is available.", "success");
            return true;
        } catch {
            slotConflict = null;
            renderConflictWarning("Unable to verify slot availability right now.");
            return true;
        }
    }

    async function refreshRecommendedSlots() {
        if (!recommendEl) return;

        const assetString = document.getElementById("asset_selection_modal")?.value || "";
        const labId = document.getElementById("labIdInput")?.value || "";
        const serviceId = serviceField?.value || "";

        if (!shouldShowRecommendations()) {
            recommendEl.innerHTML = "";
            return;
        }

        if (!serviceId || !assetString || !labId) {
            recommendEl.innerHTML = "";
            return;
        }

        recommendEl.innerHTML = `
            <div class="alert alert-info small mb-2">
                <i class="bi bi-hourglass-split me-1"></i>
                Finding recommended slots...
            </div>
        `;

        const results = [];
        const maxDays = 14;
        const limit = 3;
        const today = new Date();

        for (let i = 0; i < maxDays && results.length < limit; i++) {
            const d = new Date(today);
            d.setDate(today.getDate() + i);
            const dateStr = d.toISOString().slice(0, 10);

            try {
                const res = await fetch(`/api/bookings/day-with-assets/${labId}/${dateStr}?service_id=${encodeURIComponent(serviceId)}&assets=${encodeURIComponent(assetString)}`);
                const data = await res.json();
                const slots = data.slots || [];
                slots.forEach(slot => {
                    if (slot.can_book && results.length < limit) {
                        results.push({
                            date: dateStr,
                            start: slot.start,
                            end: slot.end,
                            label: slot.label
                        });
                    }
                });
            } catch {
                // Ignore day errors and continue
            }
        }

        if (!results.length) {
            recommendEl.innerHTML = `
                <div class="alert alert-warning small mb-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    No recommended slots found in the next two weeks.
                </div>
            `;
            return;
        }

        const buttons = results.map(r => {
            return `
                <button type="button"
                        class="btn btn-outline-primary btn-sm me-2 mb-2 recommend-slot-btn"
                        data-date="${r.date}"
                        data-start="${r.start}"
                        data-end="${r.end}">
                    ${formatDateLabel(r.date)} ${r.start}-${r.end}
                </button>
            `;
        }).join("");

        recommendEl.innerHTML = `
            <div class="small fw-semibold text-muted mb-2">Recommended next slots:</div>
            ${buttons}
        `;
    }


    // ----------------------------
    // Step switching
    // ----------------------------
    function showStep(step) {
        document.querySelectorAll(".wizard-step").forEach(s => s.classList.add("d-none"));
        const stepEl = document.getElementById("step" + step);
        if (stepEl) stepEl.classList.remove("d-none");

        wizardLabel.textContent = labels[step];
        wizardProg.style.width  = widths[step] + "%";

        prevBtn.classList.toggle("d-none", step === 1);
        nextBtn.classList.toggle("d-none", step === 3);
        submitBtn.classList.toggle("d-none", step !== 3);

        clearError();

        if (step === 2) {
            refreshRecommendedSlots();
            checkSlotConflict();
        }
    }

    // Expose reset function for external scripts (e.g., show.php)
    window.resetBookingWizard = function() {
        currentStep = 1;
        slotConflict = null;
        renderSelectedServiceSummary(currentServiceContext);
        renderConflictWarning("");
        showStep(1);
    };

    window.updateBookingServiceContext = function(service) {
        currentServiceContext = service && service.id ? service : null;
        if (serviceField) {
            serviceField.value = currentServiceContext?.id ? String(currentServiceContext.id) : "";
        }
        renderSelectedServiceSummary(currentServiceContext);
        refreshRecommendedSlots();
        checkSlotConflict();
    };


    // ----------------------------
    // Applicant add/remove
    // ----------------------------
    const applicantContainer = document.getElementById("applicantContainer");
    const addApplicantBtn    = document.getElementById("addApplicant");

    function applyDefaultFaculty(block) {
        if (!defaultFacultyId) return;
        const select = block.querySelector("select[name=\"applicant_faculty[]\"]");
        if (select) {
            select.value = defaultFacultyId;
        }
    }

    function renumberApplicants() {
        const blocks = applicantContainer.querySelectorAll(".applicant-block");
        blocks.forEach((block, index) => {
            const title = block.querySelector(".applicant-title");
            if (title) {
                title.textContent = "Applicant " + (index + 1);
            }
            const removeBtn = block.querySelector(".remove-applicant-btn");
            if (removeBtn) {
                removeBtn.style.display = (blocks.length > 1 ? "inline-flex" : "none");
            }
        });
    }

    if (addApplicantBtn && applicantContainer) {
        addApplicantBtn.addEventListener("click", () => {
            const blocks = applicantContainer.querySelectorAll(".applicant-block");
            if (!blocks.length) return;

            const first = blocks[0];
            const clone = first.cloneNode(true);

            // Clear values
            clone.querySelectorAll("input, select").forEach(el => {
                el.value = "";
            });

            applyDefaultFaculty(clone);
            applicantContainer.appendChild(clone);
            renumberApplicants();
        });

        // Delegate remove button clicks
        applicantContainer.addEventListener("click", (e) => {
            if (e.target.closest(".remove-applicant-btn")) {
                const block = e.target.closest(".applicant-block");
                if (!block) return;

                const all = applicantContainer.querySelectorAll(".applicant-block");
                if (all.length <= 1) return; // keep at least 1

                block.remove();
                renumberApplicants();
            }
        });

        const blocks = applicantContainer.querySelectorAll(".applicant-block");
        blocks.forEach(applyDefaultFaculty);
        renumberApplicants();
    }


    // ----------------------------
    // Validation per step
    // ----------------------------
    function validateStep(step) {
        clearError();

        // Step 1 - Applicant details
        if (step === 1) {
            let ok = true;
            document.querySelectorAll(".uthm-only").forEach(field => {
                if (!field.value.trim()) ok = false;
            });

            if (!ok) {
                showError("Please complete all applicant fields.");
                return false;
            }
        }

        // Step 2 - Date and time
        if (step === 2) {
            if (!serviceField?.value) {
                showError("Please choose a service before confirming date and time.");
                return false;
            }
            if (!dateField.value || !startField.value || !endField.value) {
                showError("Please complete date and time.");
                return false;
            }
            if (startField.value >= endField.value) {
                showError("End time must be after start time.");
                return false;
            }
            if (slotConflict === true) {
                showError("Selected slot is not available. Please choose another time.");
                return false;
            }
        }

        // Step 3 - Required fields (activity + pdf)
        if (step === 3) {
            let ok = true;
            document.querySelectorAll("#step3 .required-field").forEach(f => {
                if (!f.value.trim()) ok = false;
            });

            if (!ok) {
                showError("Please fill all required fields (activity and PDF).");
                return false;
            }
        }

        return true;
    }


    // ----------------------------
    // Next / Prev buttons
    // ----------------------------
    nextBtn.addEventListener("click", async () => {
        if (!validateStep(currentStep)) return;
        if (currentStep === 2) {
            const ok = await checkSlotConflict();
            if (!ok) {
                showError("Selected slot is not available. Please choose another time.");
                return;
            }
        }
        if (currentStep < 3) currentStep++;
        showStep(currentStep);
    });

    prevBtn.addEventListener("click", () => {
        if (currentStep > 1) currentStep--;
        showStep(currentStep);
    });

    // ----------------------------
    // Slot availability listeners
    // ----------------------------
    [dateField, startField, endField].forEach(field => {
        if (!field) return;
        field.addEventListener("change", () => {
            refreshRecommendedSlots();
            checkSlotConflict();
        });
    });

    if (recommendEl) {
        recommendEl.addEventListener("click", (e) => {
            const btn = e.target.closest(".recommend-slot-btn");
            if (!btn) return;

            dateField.value = btn.dataset.date || "";
            startField.value = btn.dataset.start || "";
            endField.value = btn.dataset.end || "";

            refreshRecommendedSlots();
            checkSlotConflict();
        });
    }

    window.addEventListener("assetSelectionUpdated", () => {
        refreshRecommendedSlots();
        checkSlotConflict();
    });


    // ----------------------------
    // Submit handler
    // ----------------------------
    form.addEventListener("submit", e => {
        e.preventDefault();
        clearError();

        const serviceId = serviceField?.value || "";
        const assetString = document.getElementById("asset_selection_modal").value;
        if (!serviceId) {
            showError("Service selection missing. Please choose a laboratory service before booking.");
            return;
        }
        if (!assetString) {
            showError("Linked equipment is missing for the selected service. Please choose another service.");
            return;
        }

        const fd = new FormData(form);

        fetch("/api/bookings/submit", {
            method: "POST",
            headers: {"X-Requested-With": "XMLHttpRequest"},
            body  : fd
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === "success") {
                errorArea.innerHTML = `
                    <div class="alert alert-success small mb-2">
                        <i class="bi bi-check-circle me-1"></i>${data.message}
                    </div>
                `;

                setTimeout(() => {
                    const modalInstance = bootstrap.Modal.getInstance(
                        document.getElementById("bookingModal")
                    );
                    if (modalInstance) modalInstance.hide();

                    form.reset();
                    window.resetBookingWizard();
                }, 1200);

            } else {
                showError(data.message || "Failed to submit booking.");
            }
        })
        .catch(() => {
            showError("Unexpected error submitting booking.");
        });
    });


    // Initial
    renderSelectedServiceSummary(null);
    showStep(1);
});
</script>
<?php endif; ?>
