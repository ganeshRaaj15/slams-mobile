<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\MaintenanceForecastService;
use App\Libraries\NotificationService;
use App\Libraries\StudentRoleService;
use App\Libraries\WebPushConfiguration;
use App\Models\SettingsModel;

class SettingsController extends BaseController
{
    protected $settings;
    protected StudentRoleService $studentRoleService;

    public function __construct()
    {
        helper(['auth']);

        if (!auth()->loggedIn() || !auth()->user()->inGroup('admin')) {
            redirect()->to('/')->with('error', 'Unauthorized access.')->send();
            exit;
        }

        $this->settings = new SettingsModel();
        $this->studentRoleService = new StudentRoleService();
    }

    /**
     * Display all settings including booking slot editor
     */
    public function index()
    {
        $rows = $this->settings
            ->where('class', 'system')
            ->orderBy('key', 'ASC')
            ->findAll();

        $storedSettings = [];
        foreach ($rows as $row) {
            $storedSettings[$row['key']] = [
                'value' => $row['value'],
                'type'  => $row['type']
            ];
        }

        $settings = [];
        foreach ($this->managedSettings() as $key => $meta) {
            $settings[$key] = [
                'value' => $storedSettings[$key]['value'] ?? $meta['default'],
                'type'  => $storedSettings[$key]['type'] ?? $meta['type'],
                'hint'  => $meta['hint'] ?? null,
            ];
        }

        foreach ($storedSettings as $key => $row) {
            if (! isset($settings[$key])) {
                $settings[$key] = $row;
            }
        }

        ksort($settings);

        $slotsJson = setting('system.booking_slots') ?? '[]';
        $bookingSlots = json_decode($slotsJson, true);

        return view('admin/settings/index', [
            'settings'      => $settings,
            'bookingSlots'  => $bookingSlots,
            'webPush'       => (new WebPushConfiguration())->diagnostics(),
        ]);
    }


    /**
     * Save non-slot settings (emails, faculty, etc.)
     */
    public function update()
    {
        $managedSettings = $this->managedSettings();
        $rules = [];
        foreach ($managedSettings as $key => $meta) {
            $rules[$key] = $meta['rules'];
        }

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        foreach ($managedSettings as $key => $meta) {
            $value = trim((string) $this->request->getPost($key));

            if ($key === 'student_email_domain') {
                $value = $this->studentRoleService->normalizeDomain($value);
            }

            $this->upsertSystemSetting($key, $value, $meta['type']);
        }

        return redirect()->to('/admin/settings')->with('message', 'Settings updated.');
    }


    /**
     * AJAX: Save booking slots
     */
    public function saveSlots()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON([
                'status' => 'error',
                'message' => 'Invalid request type.'
            ]);
        }

        $slots = $this->request->getPost('slots');

        if (is_string($slots)) {
            $decoded = json_decode($slots, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $slots = $decoded;
            }
        }

        if (!is_array($slots)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid slots format.'
            ]);
        }

        // Validate each time slot
        foreach ($slots as $slot) {
            if (
                empty($slot['start']) ||
                empty($slot['end'])
            ) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Each slot must have start and end times.'
                ]);
            }

            if ($slot['start'] >= $slot['end']) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Start time must be earlier than end time.'
                ]);
            }
        }

        // Normalize and check for overlaps
        $normalized = [];
        foreach ($slots as $slot) {
            $start = $slot['start'];
            $end = $slot['end'];
            $normalized[] = ['start' => $start, 'end' => $end];
        }

        usort($normalized, function ($a, $b) {
            if ($a['start'] === $b['start']) {
                return strcmp($a['end'], $b['end']);
            }
            return strcmp($a['start'], $b['start']);
        });

        for ($i = 1; $i < count($normalized); $i++) {
            $prev = $normalized[$i - 1];
            $cur = $normalized[$i];
            if ($cur['start'] < $prev['end']) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Time slots cannot overlap.'
                ]);
            }
        }

        // Generate labels automatically
        foreach ($slots as &$s) {
            $s['label'] = $s['start'] . ' - ' . $s['end'];
        }

        // Convert to JSON
        $json = json_encode($slots);

        // Save to settings table (upsert)
        $existing = $this->settings
            ->where('class', 'system')
            ->where('key', 'booking_slots')
            ->first();

        if ($existing) {
            $this->settings
                ->where('class', 'system')
                ->where('key', 'booking_slots')
                ->set([
                    'value'      => $json,
                    'type'       => 'string',
                    'updated_at' => date('Y-m-d H:i:s')
                ])
                ->update();
        } else {
            $this->settings->insert([
                'class'      => 'system',
                'key'        => 'booking_slots',
                'value'      => $json,
                'type'       => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Booking slots updated successfully.',
            'slots'   => $slots
        ]);
    }

    public function runScheduledTasks()
    {
        $bookingSent = 0;
        $maintenanceSent = 0;
        $errors = [];

        try {
            $bookingSent = (new NotificationService())->sendUpcomingBookingReminders(24);
        } catch (\Throwable $e) {
            $errors[] = 'booking reminders';
            log_message('error', 'Manual scheduled task failed [booking reminders]: ' . $e->getMessage());
        }

        try {
            $maintenanceSent = (new MaintenanceForecastService())->sendUpcomingDueReminders(30);
        } catch (\Throwable $e) {
            $errors[] = 'maintenance due reminders';
            log_message('error', 'Manual scheduled task failed [maintenance due reminders]: ' . $e->getMessage());
        }

        $message = 'Scheduled tasks completed. Booking reminders: ' . $bookingSent . '. Maintenance due reminders: ' . $maintenanceSent . '.';
        $redirect = redirect()->to('/admin/settings')->with('message', $message);

        if ($errors !== []) {
            return $redirect->with('warning', 'Some scheduled task checks failed: ' . implode(', ', $errors) . '. The app is still usable; check writable/logs for details.');
        }

        return $redirect;
    }

    protected function managedSettings(): array
    {
        return [
            'deputy_dean_email' => [
                'type' => 'string',
                'rules' => 'required|valid_email',
                'default' => '',
                'hint' => 'Faculty approval email address.',
            ],
            'fkmp_faculty_id' => [
                'type' => 'integer',
                'rules' => 'required|integer',
                'default' => '',
                'hint' => 'Faculty ID used for FKMP approval routing.',
            ],
            'lab_assistant_email' => [
                'type' => 'string',
                'rules' => 'required|valid_email',
                'default' => '',
                'hint' => 'Primary laboratory assistant email address.',
            ],
            'lab_manager_email' => [
                'type' => 'string',
                'rules' => 'required|valid_email',
                'default' => '',
                'hint' => 'Primary laboratory manager email address.',
            ],
            'student_email_domain' => [
                'type' => 'string',
                'rules' => 'required|max_length[255]',
                'default' => StudentRoleService::DEFAULT_STUDENT_EMAIL_DOMAIN,
                'hint' => 'Emails ending with this domain are auto-assigned the Student role when users register or log in.',
            ],
        ];
    }

    protected function upsertSystemSetting(string $key, string $value, string $type): void
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->settings
            ->where('class', 'system')
            ->where('key', $key)
            ->first();

        if ($existing) {
            $this->settings->update($existing['id'], [
                'value'      => $value,
                'type'       => $type,
                'updated_at' => $now,
            ]);

            return;
        }

        $this->settings->insert([
            'class'      => 'system',
            'key'        => $key,
            'value'      => $value,
            'type'       => $type,
            'context'    => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
