<?= $this->extend('layouts/main_admin') ?>
<?= $this->section('content') ?>

<?php
$settingMeta = [
    'fkmp_faculty_id' => [
        'label' => 'Direct Approval Faculty ID',
        'hint' => 'Faculty ID that completes approval at the PIC stage. The legacy setting key is kept for backward compatibility.',
    ],
];
?>

<div class="settings-page">
    <!-- PAGE HEADER -->
    <div class="dashboard-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
            <div>
                <h1>System Settings</h1>
                <p>Manage configuration values for Smart Lab Management System</p>
            </div>
            <a href="/dashboard/admin" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- FLASH MESSAGES -->
    <?php if (session()->getFlashdata('message')): ?>
        <div class="alert alert-success alert-glass mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill fs-5 me-2"></i>
                <div class="flex-grow-1"><?= session()->getFlashdata('message') ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('warning')): ?>
        <div class="alert alert-warning alert-glass mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                <div class="flex-grow-1"><?= esc(session()->getFlashdata('warning')) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- VALIDATION ERRORS -->
    <?php if (session()->getFlashdata('errors')): ?>
        <div class="alert alert-danger alert-glass mb-4">
            <div class="d-flex">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                <div class="flex-grow-1">
                    <div class="fw-semibold mb-1">Please fix the following errors:</div>
                    <ul class="mb-0 ps-3">
                        <?php foreach (session()->getFlashdata('errors') as $error): ?>
                            <li><?= esc($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- GENERAL SETTINGS CARD -->
    <div class="glass-card mb-5">
        <div class="settings-card-header">
            <h5>
                <i class="bi bi-gear-fill"></i>
                General Settings
            </h5>
        </div>

        <div class="card-body p-4">
            <form action="/admin/settings/update" method="post">
                <?= csrf_field() ?>

                <div class="info-box">
                    <p class="d-flex align-items-center mb-0">
                        <i class="bi bi-info-circle-fill me-2" style="color: #3b82f6;"></i>
                        Configure general system parameters and behaviors
                    </p>
                </div>

                <div class="row g-4">
                    <?php foreach ($settings as $key => $row): ?>
                        <?php
                        $meta = $settingMeta[$key] ?? null;
                        $settingLabel = $meta['label'] ?? ucwords(str_replace('_', ' ', $key));
                        $settingHint = $meta['hint'] ?? ($row['hint'] ?? null);
                        ?>
                        <div class="col-md-6">
                            <div class="form-group-glass">
                                <label for="<?= esc($key) ?>">
                                    <?php if ($row['type'] === 'integer'): ?>
                                        <i class="bi bi-123"></i>
                                    <?php elseif ($row['type'] === 'bool'): ?>
                                        <i class="bi bi-toggle-on"></i>
                                    <?php else: ?>
                                        <i class="bi bi-input-cursor-text"></i>
                                    <?php endif; ?>
                                    <?= esc($settingLabel) ?>
                                </label>

                                <?php if ($row['type'] === 'integer'): ?>
                                    <input type="number" 
                                           name="<?= esc($key) ?>" 
                                           id="<?= esc($key) ?>"
                                           value="<?= esc(old($key, $row['value'])) ?>"
                                           class="form-control form-control-glass" 
                                           required>

                                <?php elseif ($row['type'] === 'bool'): ?>
                                    <select name="<?= esc($key) ?>" 
                                            id="<?= esc($key) ?>"
                                            class="form-control form-control-glass" 
                                            required>
                                        <option value="1" <?= (old($key, $row['value']) ? 'selected' : '') ?>>Enabled</option>
                                        <option value="0" <?= (!old($key, $row['value']) ? 'selected' : '') ?>>Disabled</option>
                                    </select>

                                <?php else: ?>
                                    <input type="text" 
                                           name="<?= esc($key) ?>" 
                                           id="<?= esc($key) ?>"
                                           value="<?= esc(old($key, $row['value'])) ?>"
                                           class="form-control form-control-glass" 
                                           required>
                                <?php endif; ?>
                                
                                <div class="form-hint">
                                    <?php if (!empty($settingHint)): ?>
                                        <?= esc($settingHint) ?>
                                    <?php elseif ($row['type'] === 'integer'): ?>
                                        Numeric value
                                    <?php elseif ($row['type'] === 'bool'): ?>
                                        Toggle feature on/off
                                    <?php else: ?>
                                        Text configuration
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="border-top pt-4 mt-4">
                    <button type="submit" class="btn btn-primary-glass px-4">
                        <i class="bi bi-save me-2"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="glass-card mb-5">
        <div class="settings-card-header">
            <h5>
                <i class="bi bi-phone-vibrate"></i>
                Mobile Push Notifications
            </h5>
        </div>

        <div class="card-body p-4">
            <div class="info-box">
                <p class="d-flex align-items-center mb-0">
                    <i class="bi bi-info-circle-fill me-2" style="color: #3b82f6;"></i>
                    Web push lets signed-in devices receive booking, external request, and maintenance alerts even when the app is not open.
                </p>
            </div>

            <?php $webPush = $webPush ?? ['configured' => false, 'subject' => '', 'hasPublicKey' => false, 'hasPrivateKey' => false, 'defaultTtl' => 1800]; ?>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="form-group-glass h-100">
                        <label><i class="bi bi-shield-check"></i> Status</label>
                        <div class="pt-2">
                            <?php if (! empty($webPush['configured'])): ?>
                                <span class="badge bg-success fs-6">Configured</span>
                                <div class="form-hint mt-2">Push delivery is ready on the server side.</div>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark fs-6">Not Configured</span>
                                <div class="form-hint mt-2">Add VAPID keys to `.env` before users can subscribe.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group-glass h-100">
                        <label><i class="bi bi-key"></i> Keys</label>
                        <div class="pt-2 small text-muted">
                            <div>Public key: <?= ! empty($webPush['hasPublicKey']) ? 'present' : 'missing' ?></div>
                            <div>Private key: <?= ! empty($webPush['hasPrivateKey']) ? 'present' : 'missing' ?></div>
                            <div class="mt-2">Default TTL: <?= esc((int) ($webPush['defaultTtl'] ?? 1800)) ?> seconds</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group-glass h-100">
                        <label><i class="bi bi-envelope-paper"></i> Subject</label>
                        <div class="pt-2 small text-muted">
                            <?= ! empty($webPush['subject']) ? esc($webPush['subject']) : 'Not set yet' ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-top pt-4 mt-4">
                <div class="fw-semibold mb-2">Setup</div>
                <div class="small text-muted mb-3">Run the key generator once, copy the output to `.env`, restart the app, then enable push from a signed-in device.</div>
                <pre class="bg-dark text-light rounded-3 p-3 small mb-0"><code>php spark slams:generate-web-push-keys mailto:lab-admin@example.com</code></pre>
            </div>
        </div>
    </div>

    <!-- SCHEDULED TASK DEMO TRIGGER -->
    <div class="glass-card mb-5">
        <div class="settings-card-header">
            <h5>
                <i class="bi bi-bell-fill"></i>
                Reminder Checks
            </h5>
        </div>

        <div class="card-body p-4">
            <div class="info-box">
                <p class="d-flex align-items-center mb-0">
                    <i class="bi bi-info-circle-fill me-2" style="color: #3b82f6;"></i>
                    Run booking and maintenance reminder checks manually for demo. Production deployments should still use cron or Windows Task Scheduler.
                </p>
            </div>

            <form action="/admin/settings/run-scheduled-tasks" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-glass">
                    <i class="bi bi-play-circle me-2"></i> Run Reminder Checks Now
                </button>
            </form>
        </div>
    </div>

    <!-- BOOKING SLOT EDITOR -->
    <div class="glass-card">
        <div class="settings-card-header">
            <h5>
                <i class="bi bi-clock-history"></i>
                Booking Time Slots
            </h5>
        </div>

        <div class="card-body p-4">
            <div class="info-box">
                <p class="d-flex align-items-center mb-0">
                    <i class="bi bi-info-circle-fill me-2" style="color: #3b82f6;"></i>
                    These time slots determine what users may choose when booking a laboratory. You can add, remove, or edit booking slot times.
                </p>
            </div>

            <div class="table-responsive">
                <table class="table table-glass align-middle" id="slotTable">
                    <thead>
                        <tr>
                            <th style="width: 45%;">Start Time</th>
                            <th style="width: 45%;">End Time</th>
                            <th style="width: 10%;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($bookingSlots)): ?>
                            <?php foreach ($bookingSlots as $slot): ?>
                                <tr>
                                    <td>
                                        <input type="time" 
                                               class="form-control form-control-glass slot-start"
                                               value="<?= esc(substr($slot['start'], 0, 5)) ?>">
                                    </td>
                                    <td>
                                        <input type="time" 
                                               class="form-control form-control-glass slot-end"
                                               value="<?= esc(substr($slot['end'], 0, 5)) ?>">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-slot">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    <i class="bi bi-clock me-2"></i> No time slots configured
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4">
                <button id="addSlot" class="btn btn-glass" type="button">
                    <i class="bi bi-plus-circle me-2"></i> Add New Slot
                </button>
                
                <div>
                    <button id="saveSlots" class="btn btn-primary-glass px-4">
                        <i class="bi bi-save me-2"></i> Save Time Slots
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    const slotTable = document.querySelector("#slotTable tbody");

    // Add a new empty row
    document.querySelector("#addSlot").addEventListener("click", () => {
        const row = document.createElement("tr");
        row.innerHTML = `
            <td>
                <input type="time" class="form-control form-control-glass slot-start">
            </td>
            <td>
                <input type="time" class="form-control form-control-glass slot-end">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger remove-slot">
                    <i class="bi bi-trash"></i>
                </button>
            </td>`;
        slotTable.appendChild(row);
        
        // Remove the "no slots" message if present
        const emptyMessage = slotTable.querySelector('.text-muted');
        if (emptyMessage && emptyMessage.parentElement.tagName === 'TR') {
            emptyMessage.parentElement.remove();
        }
    });

    // Remove a row
    slotTable.addEventListener("click", (e) => {
        if (e.target.closest(".remove-slot")) {
            const row = e.target.closest("tr");
            row.remove();
            
            // Add "no slots" message if table is empty
            if (slotTable.children.length === 0) {
                const emptyRow = document.createElement("tr");
                emptyRow.innerHTML = `
                    <td colspan="3" class="text-center text-muted py-4">
                        <i class="bi bi-clock me-2"></i> No time slots configured
                    </td>`;
                slotTable.appendChild(emptyRow);
            }
        }
    });

    // Save slots via AJAX
    document.querySelector("#saveSlots").addEventListener("click", () => {
        const rows = slotTable.querySelectorAll("tr");
        const slots = [];

        rows.forEach(row => {
            const startInput = row.querySelector(".slot-start");
            const endInput = row.querySelector(".slot-end");
            
            // Skip rows that are the empty message
            if (startInput && endInput) {
                const start = startInput.value;
                const end = endInput.value;

                if (start && end) {
                    slots.push({ start, end });
                }
            }
        });

        // Validate at least one slot
        if (slots.length === 0) {
            alert("Please add at least one time slot.");
            return;
        }

        // Validate time order
        for (const slot of slots) {
            if (slot.start >= slot.end) {
                alert("Start time must be before end time for all slots.");
                return;
            }
        }

        // Validate no overlaps
        const sorted = [...slots].sort((a, b) => {
            if (a.start === b.start) return a.end.localeCompare(b.end);
            return a.start.localeCompare(b.start);
        });

        for (let i = 1; i < sorted.length; i++) {
            const prev = sorted[i - 1];
            const cur = sorted[i];
            if (cur.start < prev.end) {
                alert("Time slots cannot overlap.");
                return;
            }
        }

        // Show loading state
        const saveButton = document.querySelector("#saveSlots");
        const originalText = saveButton.innerHTML; 
        saveButton.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Saving...';
        saveButton.disabled = true;

        // POST to slot-saving endpoint
        fetch("/admin/settings/save-slots", {
            method: "POST",
            headers: { 
                "X-Requested-With": "XMLHttpRequest",
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: new URLSearchParams({
                slots: JSON.stringify(slots),
                "<?= csrf_token() ?>": "<?= csrf_hash() ?>",
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === "success") {
                // Show success message
                const alertDiv = document.createElement("div");
                alertDiv.className = "alert alert-success alert-glass mt-4";
                alertDiv.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill fs-5 me-2"></i>
                        <div class="flex-grow-1">Booking slots updated successfully!</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                
                // Insert after the booking slots card
                document.querySelector(".glass-card:last-child").after(alertDiv);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            } else {
                alert("Error: " + data.message);
            }
        })
        .catch(() => {
            alert("An unexpected error occurred while saving.");
        })
        .finally(() => {
            // Restore button state
            saveButton.innerHTML = originalText;
            saveButton.disabled = false;
        });
    });

});
</script>

<?= $this->endSection() ?>
