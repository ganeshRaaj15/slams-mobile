<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\ServiceBundleService;
use App\Models\AssetModel;
use App\Models\LabServiceModel;
use App\Models\LaboratoryModel;
use App\Models\ServiceAssetRequirementModel;
use App\Models\ServiceEquipmentModel;
use CodeIgniter\Shield\Entities\User;

class LabServiceController extends BaseController
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

        if (! auth()->loggedIn() || (! auth()->user()->inGroup('admin') && ! auth()->user()->inGroup('pic'))) {
            redirect()->to('/')->with('error', 'You are not authorized to access this page.')->send();
            exit;
        }

        $this->serviceModel = new LabServiceModel();
        $this->labModel = new LaboratoryModel();
        $this->assetModel = new AssetModel();
        $this->requirementModel = new ServiceAssetRequirementModel();
        $this->equipmentModel = new ServiceEquipmentModel();
        $this->bundleService = new ServiceBundleService();
    }

    public function index()
    {
        $user = auth()->user();
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
                ->orLike('laboratories.room', $filters['q'])
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

        return view('admin/services/index', [
            'services' => $services,
            'labs' => $this->manageableLabs($user),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        $user = auth()->user();

        return view('admin/services/form', [
            'mode' => 'create',
            'service' => null,
            'labs' => $this->manageableLabs($user),
            'labAssets' => $this->assetsForManageableLabs($user),
            'selectedRequirements' => [],
        ]);
    }

    public function edit(int $id)
    {
        $service = $this->serviceWithLab($id);
        if (! $service) {
            return redirect()->to('/admin/services')->with('error', 'Service not found.');
        }
        if (! $this->canManageLabId((int) $service['laboratory_id'])) {
            return redirect()->to('/admin/services')->with('error', 'You are not allowed to edit this service.');
        }

        return view('admin/services/form', [
            'mode' => 'edit',
            'service' => $this->serializeService($service),
            'labs' => $this->manageableLabs(auth()->user()),
            'labAssets' => $this->assetsForManageableLabs(auth()->user()),
            'selectedRequirements' => $this->requirementSelections((int) $service['id']),
        ]);
    }

    public function store()
    {
        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $payload = $this->collectPayload();
        if (! $this->canManageLabId((int) $payload['laboratory_id'])) {
            return redirect()->back()->withInput()->with('error', 'You can only manage services for your assigned laboratories.');
        }
        if ($this->duplicateServiceExists((int) $payload['laboratory_id'], (string) $payload['service_name'])) {
            return redirect()->back()->withInput()->with('error', 'A service with this name already exists in the selected laboratory.');
        }

        $requirements = $this->validatedRequirements((int) $payload['laboratory_id']);
        if (is_string($requirements)) {
            return redirect()->back()->withInput()->with('error', $requirements);
        }

        $serviceId = (int) $this->serviceModel->insert($payload, true);
        $this->persistRequirements($serviceId, (int) $payload['laboratory_id'], $requirements);

        return redirect()->to('/admin/services')->with('message', 'Service created successfully.');
    }

    public function update(int $id)
    {
        $existing = $this->serviceModel->find($id);
        if (! $existing) {
            return redirect()->to('/admin/services')->with('error', 'Service not found.');
        }
        if (! $this->canManageLabId((int) ($existing['laboratory_id'] ?? 0))) {
            return redirect()->to('/admin/services')->with('error', 'You are not allowed to update this service.');
        }

        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $payload = $this->collectPayload();
        if (! $this->canManageLabId((int) $payload['laboratory_id'])) {
            return redirect()->back()->withInput()->with('error', 'You can only assign services to your managed laboratories.');
        }
        if ($this->duplicateServiceExists((int) $payload['laboratory_id'], (string) $payload['service_name'], $id)) {
            return redirect()->back()->withInput()->with('error', 'A service with this name already exists in the selected laboratory.');
        }

        $requirements = $this->validatedRequirements((int) $payload['laboratory_id']);
        if (is_string($requirements)) {
            return redirect()->back()->withInput()->with('error', $requirements);
        }

        $this->serviceModel->update($id, $payload);
        $this->persistRequirements($id, (int) $payload['laboratory_id'], $requirements);

        return redirect()->to('/admin/services')->with('message', 'Service updated successfully.');
    }

    public function delete(int $id)
    {
        $service = $this->serviceModel->find($id);
        if (! $service) {
            return redirect()->to('/admin/services')->with('error', 'Service not found.');
        }
        if (! $this->canManageLabId((int) ($service['laboratory_id'] ?? 0))) {
            return redirect()->to('/admin/services')->with('error', 'You are not allowed to delete this service.');
        }

        $this->serviceModel->delete($id);

        return redirect()->to('/admin/services')->with('message', 'Service deleted successfully.');
    }

    protected function rules(): array
    {
        return [
            'laboratory_id' => 'required|integer',
            'field_name' => 'permit_empty|max_length[150]',
            'service_name' => 'required|min_length[3]|max_length[255]',
            'acceptance_criteria' => 'permit_empty|string',
            'calibration_status' => 'permit_empty|in_list[valid,expired,unknown]',
            'service_notes' => 'permit_empty|string',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];
    }

    protected function collectPayload(): array
    {
        return [
            'laboratory_id' => (int) $this->request->getPost('laboratory_id'),
            'field_name' => trim((string) $this->request->getPost('field_name')),
            'service_name' => trim((string) $this->request->getPost('service_name')),
            'acceptance_criteria' => trim((string) $this->request->getPost('acceptance_criteria')),
            'calibration_status' => trim((string) $this->request->getPost('calibration_status')) ?: 'unknown',
            'service_notes' => trim((string) $this->request->getPost('service_notes')),
            'is_active' => $this->request->getPost('is_active') === '0' ? 0 : 1,
        ];
    }

    protected function validatedRequirements(int $labId): array|string
    {
        $assetIds = $this->request->getPost('requirement_asset_id');
        $quantities = $this->request->getPost('requirement_quantity');

        if (! is_array($assetIds) || ! is_array($quantities)) {
            return 'Select at least one asset bundle requirement for the service.';
        }

        $requirements = [];
        foreach ($assetIds as $index => $assetIdRaw) {
            $assetId = (int) $assetIdRaw;
            $quantity = max((int) ($quantities[$index] ?? 0), 0);
            if ($assetId > 0 && $quantity > 0) {
                $requirements[$assetId] = $quantity;
            }
        }

        if ($requirements === []) {
            return 'Select at least one asset and quantity for this service bundle.';
        }

        $assets = $this->assetModel
            ->where('lab_id', $labId)
            ->whereIn('id', array_keys($requirements))
            ->findAll();

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
        $requirements = $this->bundleService->requirementsForService((int) $service['laboratory_id'], (int) $service['id']);
        $summary = $this->bundleService->serviceSummariesForLab((int) $service['laboratory_id']);
        $summaryMap = [];
        foreach ($summary as $item) {
            $summaryMap[(int) $item['id']] = $item;
        }
        $current = $summaryMap[(int) $service['id']] ?? [];

        $service['lab_name'] = (string) ($service['lab_name'] ?? '');
        $service['lab_room'] = (string) ($service['lab_room'] ?? '');
        $service['required_assets'] = $requirements;
        $service['bundle_summary'] = (string) ($current['bundle_summary'] ?? '');
        $service['equipment_models'] = (string) ($current['equipment_models'] ?? '');
        $service['is_bookable'] = (bool) ($current['is_bookable'] ?? false);

        return $service;
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

    protected function requirementSelections(int $serviceId): array
    {
        $rows = $this->requirementModel->where('lab_service_id', $serviceId)->findAll();
        $selected = [];
        foreach ($rows as $row) {
            $selected[(int) $row['asset_id']] = (int) ($row['quantity_required'] ?? 1);
        }

        return $selected;
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

    protected function manageableLabIds(?User $user = null): array
    {
        $user ??= auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        if ($user->inGroup('admin')) {
            return array_map(static fn(array $lab): int => (int) $lab['id'], $this->labModel->findAll());
        }

        return array_map(
            static fn(array $lab): int => (int) $lab['id'],
            $this->labModel
                ->where('LOWER(TRIM(pic_email)) =', strtolower(trim((string) $user->email)))
                ->findAll()
        );
    }

    protected function manageableLabs(?User $user = null): array
    {
        $labIds = $this->manageableLabIds($user);
        if ($labIds === []) {
            return [];
        }

        return $this->labModel->whereIn('id', $labIds)->orderBy('name', 'ASC')->findAll();
    }

    protected function canManageLabId(int $labId): bool
    {
        return $labId > 0 && in_array($labId, $this->manageableLabIds(auth()->user()), true);
    }

    protected function duplicateServiceExists(int $labId, string $serviceName, int $ignoreId = 0): bool
    {
        $builder = $this->serviceModel
            ->where('laboratory_id', $labId)
            ->where('LOWER(service_name) =', strtolower(trim($serviceName)));

        if ($ignoreId > 0) {
            $builder->where('id !=', $ignoreId);
        }

        return $builder->first() !== null;
    }
}
