<?php

namespace App\Controllers\Api;

use App\Libraries\AssetIntelligenceService;
use App\Controllers\BaseController;
use App\Libraries\MaintenanceForecastService;
use App\Libraries\MaintenancePredictionService;
use App\Libraries\NotificationService;
use App\Libraries\StudentRoleService;
use App\Models\SettingsModel;
use CodeIgniter\Shield\Entities\User;

class NativeAdminSettingsController extends BaseController
{
    protected SettingsModel $settings;
    protected StudentRoleService $studentRoleService;

    public function __construct()
    {
        helper('auth');
        $this->settings = new SettingsModel();
        $this->studentRoleService = new StudentRoleService();
    }

    public function show()
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $rows = $this->settings
            ->where('class', 'system')
            ->orderBy('key', 'ASC')
            ->findAll();

        $storedSettings = [];
        foreach ($rows as $row) {
            $storedSettings[$row['key']] = [
                'value' => $row['value'],
                'type' => $row['type'],
            ];
        }

        $settings = [];
        foreach ($this->managedSettings() as $key => $meta) {
            $settings[$key] = [
                'label' => $meta['label'] ?? ucwords(str_replace('_', ' ', $key)),
                'value' => $this->normalizeSettingValue((string) ($storedSettings[$key]['value'] ?? $meta['default'])),
                'type' => $storedSettings[$key]['type'] ?? $meta['type'],
                'hint' => $meta['hint'] ?? null,
            ];
        }

        $slotsJson = setting('system.booking_slots') ?? '[]';
        $bookingSlots = json_decode($slotsJson, true);
        if (! is_array($bookingSlots)) {
            $bookingSlots = [];
        }

        return $this->response->setJSON([
            'status' => 'success',
            'settings' => $settings,
            'booking_slots' => array_values($bookingSlots),
        ]);
    }

    public function update()
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $payload = $this->requestPayload();
        $managedSettings = $this->managedSettings();
        $rules = [];
        foreach ($managedSettings as $key => $meta) {
            $rules[$key] = $meta['rules'];
        }

        if (! $this->validateData($payload, $rules)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid settings payload.',
                    'errors' => $this->validator->getErrors(),
                ]);
        }

        foreach ($managedSettings as $key => $meta) {
            $value = $this->normalizeSettingValue((string) ($payload[$key] ?? ''));
            if ($key === 'student_email_domain') {
                $value = $this->studentRoleService->normalizeDomain($value);
            }

            $this->upsertSystemSetting($key, $value, $meta['type']);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Settings updated.',
        ]);
    }

    public function saveSlots()
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $payload = $this->requestPayload();
        $slots = $payload['slots'] ?? [];
        if (is_string($slots)) {
            $decoded = json_decode($slots, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $slots = $decoded;
            }
        }

        if (! is_array($slots)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid slots format.',
                ]);
        }

        foreach ($slots as $slot) {
            if (empty($slot['start']) || empty($slot['end'])) {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'Each slot must have start and end times.',
                    ]);
            }

            if ($slot['start'] >= $slot['end']) {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'Start time must be earlier than end time.',
                    ]);
            }
        }

        $normalized = [];
        foreach ($slots as $slot) {
            $normalized[] = [
                'start' => $slot['start'],
                'end' => $slot['end'],
            ];
        }

        usort($normalized, static function (array $a, array $b): int {
            if ($a['start'] === $b['start']) {
                return strcmp($a['end'], $b['end']);
            }

            return strcmp($a['start'], $b['start']);
        });

        for ($i = 1, $count = count($normalized); $i < $count; $i++) {
            $previous = $normalized[$i - 1];
            $current = $normalized[$i];
            if ($current['start'] < $previous['end']) {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'status' => 'error',
                        'message' => 'Time slots cannot overlap.',
                    ]);
            }
        }

        foreach ($slots as &$slot) {
            $slot['label'] = $slot['start'] . ' - ' . $slot['end'];
        }
        unset($slot);

        $json = json_encode(array_values($slots));
        $existing = $this->settings
            ->where('class', 'system')
            ->where('key', 'booking_slots')
            ->first();

        if ($existing) {
            $this->settings
                ->where('class', 'system')
                ->where('key', 'booking_slots')
                ->set([
                    'value' => $json,
                    'type' => 'string',
                    'updated_at' => date('Y-m-d H:i:s'),
                ])
                ->update();
        } else {
            $this->settings->insert([
                'class' => 'system',
                'key' => 'booking_slots',
                'value' => $json,
                'type' => 'string',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Booking slots updated successfully.',
            'slots' => array_values($slots),
        ]);
    }

    public function runScheduledTasks()
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

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

        return $this->response->setJSON([
            'status' => $errors === [] ? 'success' : 'error',
            'message' => 'Scheduled tasks completed.',
            'booking_reminders' => $bookingSent,
            'maintenance_due_reminders' => $maintenanceSent,
            'errors' => $errors,
        ]);
    }

    public function trainMaintenanceModel()
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        try {
            $predictionService = new MaintenancePredictionService();
            $predictionService->trainAndPersist();
            $modelSummary = $predictionService->getModelSummary();
            $assetStats = (new AssetIntelligenceService())->stats((new AssetIntelligenceService())->mapForAssets());

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Maintenance prediction model retrained successfully.',
                'model_summary' => $modelSummary,
                'asset_stats' => $assetStats,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Maintenance model training failed: ' . $e->getMessage());

            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ]);
        }
    }

    protected function authorizedAdmin()
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Unauthenticated.',
                ]);
        }

        if (! $user->inGroup('admin')) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Unauthorized access.',
                ]);
        }

        return $user;
    }

    protected function managedSettings(): array
    {
        return [
            'deputy_dean_email' => [
                'label' => 'Deputy Dean Email',
                'type' => 'string',
                'rules' => 'required|valid_email',
                'default' => '',
                'hint' => 'Faculty approval email address.',
            ],
            'fkmp_faculty_id' => [
                'label' => 'FKMP Faculty ID',
                'type' => 'integer',
                'rules' => 'required|integer',
                'default' => '',
                'hint' => 'Faculty ID used for FKMP approval routing.',
            ],
            'lab_assistant_email' => [
                'label' => 'Lab Assistant Email',
                'type' => 'string',
                'rules' => 'required|valid_email',
                'default' => '',
                'hint' => 'Primary laboratory assistant email address.',
            ],
            'lab_manager_email' => [
                'label' => 'Lab Manager Email',
                'type' => 'string',
                'rules' => 'required|valid_email',
                'default' => '',
                'hint' => 'Primary laboratory manager email address.',
            ],
            'student_email_domain' => [
                'label' => 'Student Email Domain',
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
                'value' => $value,
                'type' => $type,
                'updated_at' => $now,
            ]);

            return;
        }

        $this->settings->insert([
            'class' => 'system',
            'key' => $key,
            'value' => $value,
            'type' => $type,
            'context' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function requestPayload(): array
    {
        $json = $this->request->getJSON(true);
        if (is_array($json) && $json !== []) {
            return $json;
        }

        $post = $this->request->getPost();

        return is_array($post) ? $post : [];
    }

    protected function normalizeSettingValue(string $value): string
    {
        $cleaned = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $value);

        return trim($cleaned ?? $value);
    }
}
