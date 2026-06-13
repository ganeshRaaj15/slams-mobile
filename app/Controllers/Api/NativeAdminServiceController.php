<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\ServiceBundleService;
use App\Models\AssetModel;
use App\Models\LabServiceModel;
use App\Models\LaboratoryModel;
use App\Models\ServiceAssetRequirementModel;
use App\Models\ServiceEquipmentModel;
use CodeIgniter\Shield\Entities\User;

class NativeAdminServiceController extends BaseController
{
    protected LabServiceModel $serviceModel;
    protected LaboratoryModel $labModel;
    protected AssetModel $assetModel;
    protected ServiceAssetRequirementModel $requirementModel;
    protected ServiceEquipmentModel $equipmentModel;
    protected ServiceBundleService $bundleService;

    public function __construct()
    {
        helper('auth');
        $this->serviceModel = new LabServiceModel();
        $this->labModel = new LaboratoryModel();
        $this->assetModel = new AssetModel();
        $this->requirementModel = new ServiceAssetRequirementModel();
        $this->equipmentModel = new ServiceEquipmentModel();
        $this->bundleService = new ServiceBundleService();
    }

    public function index()
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $user;
        }

        $filters = [
            'q' => trim((string) $this->request->getGet('q')),
            'lab_id' => max((int) $this->request->getGet('lab_id'), 0),
            'active' => trim((string) $this->request->getGet('active')),
        ];
        if (! in_array($filters['active'], ['1', '0'], true)) {
            $filters['active'] = '';
        }

        $labIds = $this->manageableLabIds($user);
        $builder = $this->serviceModel
            ->select('lab_services.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = lab_services.laboratory_id', 'left');

        if ($labIds === []) {
            $builder->where('1 = 0');
        } else {
            $builder->whereIn('lab_services.laboratory_id', $labIds);
        }

        if ($filters['q'] !== '') {
            $builder->groupStart()
                ->like('lab_services.service_name', $filters['q'])
                ->orLike('lab_services.field_name', $filters['q'])
                ->orLike('laboratories.name', $filters['q'])
                ->groupEnd();
        }
        if ($filters['lab_id'] > 0) {
            $builder->where('lab_services.laboratory_id', $filters['lab_id']);
        }
        if ($filters['active'] !== '') {
            $builder->where('lab_services.is_active', (int) $filters['active']);
        }

        $services = $builder
            ->orderBy('laboratories.name', 'ASC')
            ->orderBy('lab_services.service_name', 'ASC')
            ->findAll();

        foreach ($services as &$service) {
            $service = $this->serializeService($service);
        }
        unset($service);

        return $this->response->setJSON([
            'status' => 'success',
            'services' => $services,
            'labs' => array_map(fn(array $lab): array => $this->serializeLabOption($lab), $this->manageableLabs($user)),
            'filters' => $filters,
            'permissions' => [
                'scoped_to_pic_labs' => $this->isPicUser($user),
            ],
        ]);
    }

    public function show(int $id)
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $user;
        }

        $service = $this->serviceWithLab($id);
        if (! $service) {
            return $this->response->setStatusCode(404)->setJSON(['status' => 'error', 'message' => 'Service not found.']);
        }
        if (! $this->canManageLabId((int) $service['laboratory_id'], $user)) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'service' => $this->serializeService($service),
            'labs' => array_map(fn(array $lab): array => $this->serializeLabOption($lab), $this->manageableLabs($user)),
            'assets' => array_map(fn(array $asset): array => $this->serializeAssetOption($asset), $this->assetsForManageableLabs($user)),
        ]);
    }

    public function store()
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $user;
        }

        $payload = $this->collectPayload();
        if ($error = $this->validatePayload($payload, 0, $user)) {
            return $this->response->setStatusCode(422)->setJSON(['status' => 'error', 'message' => $error]);
        }

        $requirements = $this->validatedRequirements((int) $payload['laboratory_id']);
        if (is_string($requirements)) {
            return $this->response->setStatusCode(422)->setJSON(['status' => 'error', 'message' => $requirements]);
        }

        $serviceId = (int) $this->serviceModel->insert($payload, true);
        $this->persistRequirements($serviceId, (int) $payload['laboratory_id'], $requirements);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Service created successfully.',
            'service' => $this->serializeService($this->serviceWithLab($serviceId) ?? $this->serviceModel->find($serviceId) ?? []),
        ]);
    }

    public function update(int $id)
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $user;
        }

        $existing = $this->serviceModel->find($id);
        if (! $existing) {
            return $this->response->setStatusCode(404)->setJSON(['status' => 'error', 'message' => 'Service not found.']);
        }

        $payload = $this->collectPayload();
        if ($error = $this->validatePayload($payload, $id, $user, $existing)) {
            return $this->response->setStatusCode(422)->setJSON(['status' => 'error', 'message' => $error]);
        }

        $requirements = $this->validatedRequirements((int) $payload['laboratory_id']);
        if (is_string($requirements)) {
            return $this->response->setStatusCode(422)->setJSON(['status' => 'error', 'message' => $requirements]);
        }

        $this->serviceModel->update($id, $payload);
        $this->persistRequirements($id, (int) $payload['laboratory_id'], $requirements);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Service updated successfully.',
            'service' => $this->serializeService($this->serviceWithLab($id) ?? $this->serviceModel->find($id) ?? []),
        ]);
    }

    public function delete(int $id)
    {
        $user = $this->authorizedUser();
        if (! $user instanceof User) {
            return $user;
        }

        $service = $this->serviceModel->find($id);
        if (! $service) {
            return $this->response->setStatusCode(404)->setJSON(['status' => 'error', 'message' => 'Service not found.']);
        }
        if (! $this->canManageLabId((int) ($service['laboratory_id'] ?? 0), $user)) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $this->serviceModel->delete($id);

        return $this->response->setJSON(['status' => 'success', 'message' => 'Service deleted successfully.']);
    }

    protected function validatePayload(array $payload, int $ignoreId, User $user, ?array $existing = null): ?string
    {
        if (! $this->validateData($payload, [
            'laboratory_id' => 'required|integer',
            'field_name' => 'permit_empty|max_length[150]',
            'service_name' => 'required|min_length[3]|max_length[255]',
            'acceptance_criteria' => 'permit_empty|string',
            'calibration_status' => 'permit_empty|in_list[valid,expired,unknown]',
            'service_notes' => 'permit_empty|string',
            'is_active' => 'permit_empty|in_list[0,1]',
        ])) {
            return implode(' ', $this->validator->getErrors());
        }

        $labId = (int) ($payload['laboratory_id'] ?? 0);
        if (! $this->canManageLabId($labId, $user)) {
            return 'You can only manage services for laboratories assigned to you.';
        }

        if ($existing && ! $this->canManageLabId((int) ($existing['laboratory_id'] ?? 0), $user)) {
            return 'You are not allowed to update this service.';
        }

        $duplicate = $this->serviceModel
            ->where('laboratory_id', $labId)
            ->where('LOWER(service_name) =', strtolower((string) ($payload['service_name'] ?? '')));
        if ($ignoreId > 0) {
            $duplicate->where('id !=', $ignoreId);
        }
        if ($duplicate->first()) {
            return 'A service with this name already exists in the selected laboratory.';
        }

        return null;
    }

    protected function collectPayload(): array
    {
        $json = $this->request->getJSON(true);
        $data = is_array($json) && $json !== [] ? $json : ($this->request->getPost() ?: []);

        return [
            'laboratory_id' => (int) ($data['laboratory_id'] ?? 0),
            'field_name' => trim((string) ($data['field_name'] ?? '')),
            'service_name' => trim((string) ($data['service_name'] ?? '')),
            'acceptance_criteria' => trim((string) ($data['acceptance_criteria'] ?? '')),
            'calibration_status' => trim((string) ($data['calibration_status'] ?? '')) ?: 'unknown',
            'service_notes' => trim((string) ($data['service_notes'] ?? '')),
            'is_active' => $this->truthy($data['is_active'] ?? 1) ? 1 : 0,
        ];
    }

    protected function validatedRequirements(int $labId): array|string
    {
        $json = $this->request->getJSON(true);
        $data = is_array($json) && $json !== [] ? $json : ($this->request->getPost() ?: []);
        $rows = $data['requirements'] ?? null;

        $requirements = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $assetId = (int) ($row['asset_id'] ?? 0);
                $quantity = max((int) ($row['quantity_required'] ?? 0), 0);
                if ($assetId > 0 && $quantity > 0) {
                    $requirements[$assetId] = $quantity;
                }
            }
        } else {
            $assetIds = $this->request->getPost('requirement_asset_id');
            $quantities = $this->request->getPost('requirement_quantity');
            if (is_array($assetIds) && is_array($quantities)) {
                foreach ($assetIds as $index => $assetIdRaw) {
                    $assetId = (int) $assetIdRaw;
                    $quantity = max((int) ($quantities[$index] ?? 0), 0);
                    if ($assetId > 0 && $quantity > 0) {
                        $requirements[$assetId] = $quantity;
                    }
                }
            }
        }

        if ($requirements === []) {
            return 'Select at least one asset and quantity for this service bundle.';
        }

        $assets = $this->assetModel->where('lab_id', $labId)->whereIn('id', array_keys($requirements))->findAll();
        if (count($assets) !== count($requirements)) {
            return 'One or more selected assets do not belong to the selected laboratory.';
        }

        foreach ($assets as $asset) {
            $assetId = (int) $asset['id'];
            $total = max((int) ($asset['total_quantity'] ?? $asset['quantity'] ?? 0), 1);
            if (($requirements[$assetId] ?? 0) > $total) {
                return 'Required quantity for ' . ($asset['name'] ?? 'an asset') . ' exceeds the asset total quantity.';
            }
        }

        return $requirements;
    }

    protected function persistRequirements(int $serviceId, int $labId, array $requirements): void
    {
        $this->requirementModel->where('lab_service_id', $serviceId)->delete();
        $this->equipmentModel->where('lab_service_id', $serviceId)->delete();

        $assets = $this->assetModel->where('lab_id', $labId)->whereIn('id', array_keys($requirements))->findAll();
        $assetMap = [];
        foreach ($assets as $asset) {
            $assetMap[(int) $asset['id']] = $asset;
        }

        $sort = 1;
        foreach ($requirements as $assetId => $quantityRequired) {
            $this->requirementModel->insert([
                'lab_service_id' => $serviceId,
                'asset_id' => $assetId,
                'quantity_required' => $quantityRequired,
                'sort_order' => $sort,
            ]);

            $asset = $assetMap[$assetId] ?? null;
            $equipmentLabel = trim((string) (($asset['name'] ?? '') . (! empty($asset['model']) ? ' - ' . $asset['model'] : '')));
            $this->equipmentModel->insert([
                'lab_service_id' => $serviceId,
                'equipment_model' => $equipmentLabel !== '' ? $equipmentLabel : ('Asset #' . $assetId),
                'criteria_note' => 'Required quantity: ' . $quantityRequired,
                'calibration_status' => 'unknown',
                'sort_order' => $sort,
            ]);
            $sort++;
        }
    }

    protected function serializeService(array $service): array
    {
        if (! isset($service['id'], $service['laboratory_id'])) {
            return $service;
        }

        $requirements = $this->bundleService->requirementsForService((int) $service['laboratory_id'], (int) $service['id']);
        $summarySet = $this->bundleService->serviceSummariesForLab((int) $service['laboratory_id']);
        $current = null;
        foreach ($summarySet as $item) {
            if ((int) $item['id'] === (int) $service['id']) {
                $current = $item;
                break;
            }
        }

        return [
            'id' => (int) $service['id'],
            'laboratory_id' => (int) ($service['laboratory_id'] ?? 0),
            'lab_name' => (string) ($service['lab_name'] ?? ''),
            'lab_room' => (string) ($service['lab_room'] ?? ''),
            'field_name' => (string) ($service['field_name'] ?? ''),
            'service_name' => (string) ($service['service_name'] ?? ''),
            'acceptance_criteria' => (string) ($service['acceptance_criteria'] ?? ''),
            'calibration_status' => (string) ($service['calibration_status'] ?? 'unknown'),
            'service_notes' => (string) ($service['service_notes'] ?? ''),
            'is_active' => (bool) ($service['is_active'] ?? true),
            'equipment_models' => (string) ($current['equipment_models'] ?? ''),
            'bundle_summary' => (string) ($current['bundle_summary'] ?? ''),
            'is_bookable' => (bool) ($current['is_bookable'] ?? false),
            'required_assets' => array_values($requirements),
        ];
    }

    protected function serializeLabOption(array $lab): array
    {
        return [
            'id' => (int) $lab['id'],
            'name' => (string) ($lab['name'] ?? ''),
            'room' => (string) ($lab['room'] ?? ''),
            'label' => trim(((string) ($lab['name'] ?? '')) . ' - ' . ((string) ($lab['room'] ?? ''))),
        ];
    }

    protected function serializeAssetOption(array $asset): array
    {
        return [
            'id' => (int) $asset['id'],
            'lab_id' => (int) ($asset['lab_id'] ?? 0),
            'name' => (string) ($asset['name'] ?? ''),
            'asset_code' => (string) ($asset['asset_code'] ?? ''),
            'model' => (string) ($asset['model'] ?? ''),
            'status' => (string) ($asset['status'] ?? ''),
            'quantity' => (int) ($asset['quantity'] ?? 0),
            'total_quantity' => (int) ($asset['total_quantity'] ?? 0),
            'lab_name' => (string) ($asset['lab_name'] ?? ''),
            'lab_room' => (string) ($asset['lab_room'] ?? ''),
            'label' => trim(((string) ($asset['name'] ?? '')) . ' (' . ((string) ($asset['asset_code'] ?? '')) . ')'),
        ];
    }

    protected function serviceWithLab(int $id): ?array
    {
        $service = $this->serviceModel
            ->select('lab_services.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = lab_services.laboratory_id', 'left')
            ->where('lab_services.id', $id)
            ->first();

        return is_array($service) ? $service : null;
    }

    protected function assetsForManageableLabs(User $user): array
    {
        $labIds = $this->manageableLabIds($user);
        if ($labIds === []) {
            return [];
        }

        return $this->assetModel
            ->select('assets.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = assets.lab_id', 'left')
            ->whereIn('assets.lab_id', $labIds)
            ->orderBy('laboratories.name', 'ASC')
            ->orderBy('assets.name', 'ASC')
            ->findAll();
    }

    protected function authorizedUser()
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->response->setStatusCode(401)->setJSON(['status' => 'error', 'message' => 'Unauthenticated.']);
        }
        if (! $user->inGroup('admin') && ! $user->inGroup('pic')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        return $user;
    }

    protected function isPicUser(User $user): bool
    {
        return $user->inGroup('pic') && ! $user->inGroup('admin');
    }

    protected function manageableLabIds(User $user): array
    {
        if (! $this->isPicUser($user)) {
            return array_map(static fn(array $lab): int => (int) $lab['id'], $this->labModel->findAll());
        }

        return array_map(
            static fn(array $lab): int => (int) $lab['id'],
            $this->labModel
                ->where('LOWER(TRIM(pic_email)) =', strtolower(trim((string) $user->email)))
                ->findAll()
        );
    }

    protected function manageableLabs(User $user): array
    {
        $labIds = $this->manageableLabIds($user);
        if ($labIds === []) {
            return [];
        }

        return $this->labModel->whereIn('id', $labIds)->orderBy('name', 'ASC')->findAll();
    }

    protected function canManageLabId(int $labId, User $user): bool
    {
        return $labId > 0 && in_array($labId, $this->manageableLabIds($user), true);
    }

    protected function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
