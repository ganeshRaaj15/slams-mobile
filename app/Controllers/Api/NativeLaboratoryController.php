<?php

namespace App\Controllers\Api;

use App\Controllers\Public\LaboratoryController as WebLaboratoryController;
use CodeIgniter\Exceptions\PageNotFoundException;

class NativeLaboratoryController extends WebLaboratoryController
{
    public function index()
    {
        $search = trim((string) $this->request->getGet('q'));

        $query = $this->laboratories->orderBy('name', 'ASC');
        if ($search !== '') {
            $query->like('name', $search);
        }

        $labs = $query->findAll();
        $this->enrichPicDetails($labs);

        return $this->response->setJSON([
            'status' => 'success',
            'labs' => array_map(fn(array $lab): array => $this->serializeLabSummary($lab), $labs),
        ]);
    }

    public function show($id = null)
    {
        $labId = (int) $id;
        $lab = $this->laboratories->find($labId);
        if (! $lab) {
            throw PageNotFoundException::forPageNotFound('Laboratory not found.');
        }

        $labs = [$lab];
        $this->enrichPicDetails($labs);
        $lab = $labs[0];

        $assets = $this->assets
            ->where('lab_id', $labId)
            ->orderBy('name', 'ASC')
            ->findAll();

        return $this->response->setJSON([
            'status' => 'success',
            'lab' => $this->serializeLabDetail($lab, $assets, $this->servicesForLab($labId)),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $labs
     */
    protected function enrichPicDetails(array &$labs): void
    {
        $picEmails = [];
        foreach ($labs as $lab) {
            if (! empty($lab['pic_email'])) {
                $picEmails[] = strtolower(trim((string) $lab['pic_email']));
            }
        }

        $picEmails = array_values(array_unique($picEmails));
        if ($picEmails === []) {
            return;
        }

        $db = \Config\Database::connect();
        $identityRows = $db->table('auth_identities')
            ->select('user_id, secret')
            ->where('type', 'email_password')
            ->whereIn('LOWER(secret)', $picEmails)
            ->get()
            ->getResultArray();

        $emailToUserId = [];
        foreach ($identityRows as $row) {
            $emailToUserId[strtolower(trim((string) $row['secret']))] = (int) $row['user_id'];
        }

        if ($emailToUserId === []) {
            return;
        }

        $profiles = $db->table('users')
            ->select('id, full_name, phone, profile_photo')
            ->whereIn('id', array_values(array_unique(array_values($emailToUserId))))
            ->get()
            ->getResultArray();

        $profileMap = [];
        foreach ($profiles as $profile) {
            $profileMap[(int) $profile['id']] = $profile;
        }

        foreach ($labs as &$lab) {
            $email = strtolower(trim((string) ($lab['pic_email'] ?? '')));
            if ($email === '' || ! isset($emailToUserId[$email])) {
                continue;
            }

            $profile = $profileMap[$emailToUserId[$email]] ?? null;
            if (! $profile) {
                continue;
            }

            if (! empty($profile['full_name'])) {
                $lab['pic_name'] = $profile['full_name'];
            }
            if (! empty($profile['phone'])) {
                $lab['pic_phone'] = $profile['phone'];
            }
            if (! empty($profile['profile_photo'])) {
                $lab['pic_image'] = $profile['profile_photo'];
            }
        }
        unset($lab);
    }

    protected function serializeLabSummary(array $lab): array
    {
        return [
            'id' => (int) $lab['id'],
            'name' => (string) ($lab['name'] ?? ''),
            'room' => (string) ($lab['room'] ?? ''),
            'description' => (string) ($lab['description'] ?? ''),
            'capacity' => (int) ($lab['capacity'] ?? 0),
            'availability_note' => (string) ($lab['availability_note'] ?? ''),
            'safety_note' => (string) ($lab['safety_note'] ?? ''),
            'image' => (string) ($lab['image'] ?? ''),
            'image_url' => $this->mediaUrl('images/labs/' . ltrim((string) ($lab['image'] ?? ''), '/')),
            'pic_name' => (string) ($lab['pic_name'] ?? ''),
            'pic_email' => (string) ($lab['pic_email'] ?? ''),
            'pic_phone' => (string) ($lab['pic_phone'] ?? ''),
            'pic_image' => (string) ($lab['pic_image'] ?? ''),
            'pic_image_url' => $this->mediaUrl('images/pic/' . ltrim((string) ($lab['pic_image'] ?? ''), '/')),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $assets
     * @param array<int, array<string, mixed>> $services
     */
    protected function serializeLabDetail(array $lab, array $assets, array $services): array
    {
        return array_merge($this->serializeLabSummary($lab), [
            'assets' => array_map(function (array $asset): array {
                return [
                    'id' => (int) $asset['id'],
                    'lab_service_id' => isset($asset['lab_service_id']) ? (int) $asset['lab_service_id'] : null,
                    'asset_code' => (string) ($asset['asset_code'] ?? ''),
                    'name' => (string) ($asset['name'] ?? ''),
                    'category' => (string) ($asset['category'] ?? ''),
                    'brand' => (string) ($asset['brand'] ?? ''),
                    'model' => (string) ($asset['model'] ?? ''),
                    'serial_number' => (string) ($asset['serial_number'] ?? ''),
                    'specifications' => (string) ($asset['specifications'] ?? ''),
                    'status' => (string) ($asset['status'] ?? ''),
                    'quantity' => (int) ($asset['quantity'] ?? 0),
                    'total_quantity' => $this->assets->totalQuantity($asset),
                    'location_note' => (string) ($asset['location_note'] ?? ''),
                    'image' => (string) ($asset['image'] ?? ''),
                    'image_url' => $this->mediaUrl('images/assets/' . ltrim((string) ($asset['image'] ?? ''), '/')),
                ];
            }, $assets),
            'services' => array_map(static function (array $service): array {
                return [
                    'id' => (int) $service['id'],
                    'field_name' => (string) ($service['field_name'] ?? ''),
                    'service_name' => (string) ($service['service_name'] ?? ''),
                    'acceptance_criteria' => (string) ($service['acceptance_criteria'] ?? ''),
                    'calibration_status' => (string) ($service['calibration_status'] ?? ''),
                    'equipment_models' => (string) ($service['equipment_models'] ?? ''),
                    'bundle_summary' => (string) ($service['bundle_summary'] ?? ''),
                    'is_bookable' => (bool) ($service['is_bookable'] ?? false),
                    'required_assets' => array_map(static fn(array $asset): array => [
                        'asset_id' => (int) ($asset['asset_id'] ?? 0),
                        'name' => (string) ($asset['name'] ?? ''),
                        'status' => (string) ($asset['status'] ?? ''),
                        'available_quantity' => (int) ($asset['available_quantity'] ?? 0),
                        'quantity_required' => (int) ($asset['quantity_required'] ?? 1),
                        'is_available' => (bool) ($asset['is_available'] ?? false),
                    ], $service['required_assets'] ?? []),
                ];
            }, $services),
        ]);
    }

    protected function mediaUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_ends_with($path, '/')) {
            return '';
        }

        $scheme = $this->request->getUri()->getScheme();
        $host = $this->request->getHeaderLine('Host');

        return rtrim($scheme . '://' . $host, '/') . '/' . ltrim($path, '/');
    }
}
