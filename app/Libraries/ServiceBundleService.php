<?php

namespace App\Libraries;

use App\Models\AssetModel;
use App\Models\LabServiceModel;
use App\Models\ServiceAssetRequirementModel;

class ServiceBundleService
{
    protected AssetModel $assetModel;
    protected LabServiceModel $serviceModel;
    protected ?ServiceAssetRequirementModel $requirementModel = null;
    protected \CodeIgniter\Database\BaseConnection $db;

    public function __construct(
        ?AssetModel $assetModel = null,
        ?LabServiceModel $serviceModel = null,
        ?ServiceAssetRequirementModel $requirementModel = null
    ) {
        $this->assetModel = $assetModel ?? new AssetModel();
        $this->serviceModel = $serviceModel ?? new LabServiceModel();
        $this->db = db_connect();

        if ($this->db->tableExists('service_asset_requirements')) {
            $this->requirementModel = $requirementModel ?? new ServiceAssetRequirementModel();
        }
    }

    public function requirementsForService(int $labId, int $serviceId): array
    {
        if ($labId <= 0 || $serviceId <= 0) {
            return [];
        }

        $service = $this->serviceModel
            ->where('id', $serviceId)
            ->where('laboratory_id', $labId)
            ->where('is_active', 1)
            ->first();

        if (! is_array($service)) {
            return [];
        }

        $requirements = $this->requirementsFromBundleTable($serviceId, $labId);
        if ($requirements !== []) {
            return $requirements;
        }

        return $this->requirementsFromLegacyAssetLinks($labId, $serviceId);
    }

    public function serviceSummariesForLab(int $labId): array
    {
        if ($labId <= 0 || ! $this->db->tableExists('lab_services')) {
            return [];
        }

        $services = $this->db->table('lab_services ls')
            ->select("
                ls.id,
                ls.field_name,
                ls.service_name,
                ls.acceptance_criteria,
                ls.calibration_status,
                GROUP_CONCAT(
                    DISTINCT NULLIF(TRIM(sem.equipment_model), '')
                    ORDER BY sem.sort_order ASC
                    SEPARATOR ' | '
                ) AS equipment_models
            ", false)
            ->join('service_equipment_models sem', 'sem.lab_service_id = ls.id', 'left')
            ->where('ls.laboratory_id', $labId)
            ->where('ls.is_active', 1)
            ->groupBy('ls.id')
            ->orderBy('ls.service_name', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($services as &$service) {
            $requirements = $this->requirementsForService($labId, (int) $service['id']);
            $service['required_assets'] = array_values($requirements);
            $service['is_bookable'] = $this->requirementsCurrentlyBookable($requirements);
            $service['bundle_summary'] = $this->bundleSummary($requirements);
            if (trim((string) ($service['equipment_models'] ?? '')) === '') {
                $service['equipment_models'] = $service['bundle_summary'];
            }
        }
        unset($service);

        return $services;
    }

    public function requirementMapForService(int $labId, int $serviceId): array
    {
        $requirements = $this->requirementsForService($labId, $serviceId);
        $map = [];

        foreach ($requirements as $requirement) {
            $assetId = (int) ($requirement['asset_id'] ?? 0);
            $quantity = max((int) ($requirement['quantity_required'] ?? 0), 0);
            if ($assetId > 0 && $quantity > 0) {
                $map[$assetId] = $quantity;
            }
        }

        return $map;
    }

    protected function requirementsFromBundleTable(int $serviceId, int $labId): array
    {
        if (! $this->requirementModel instanceof ServiceAssetRequirementModel) {
            return [];
        }

        $rows = $this->requirementModel
            ->select('service_asset_requirements.*, assets.lab_id, assets.name, assets.asset_code, assets.category, assets.model, assets.status, assets.quantity, assets.total_quantity')
            ->join('assets', 'assets.id = service_asset_requirements.asset_id', 'inner')
            ->where('service_asset_requirements.lab_service_id', $serviceId)
            ->where('assets.lab_id', $labId)
            ->orderBy('service_asset_requirements.sort_order', 'ASC')
            ->orderBy('assets.name', 'ASC')
            ->findAll();

        return array_map(fn(array $row): array => $this->serializeRequirement($row), $rows);
    }

    protected function requirementsFromLegacyAssetLinks(int $labId, int $serviceId): array
    {
        $rows = $this->assetModel
            ->where('lab_id', $labId)
            ->where('lab_service_id', $serviceId)
            ->orderBy('name', 'ASC')
            ->findAll();

        return array_map(function (array $asset): array {
            return $this->serializeRequirement([
                'asset_id' => $asset['id'],
                'quantity_required' => 1,
                'name' => $asset['name'] ?? '',
                'asset_code' => $asset['asset_code'] ?? '',
                'category' => $asset['category'] ?? '',
                'model' => $asset['model'] ?? '',
                'status' => $asset['status'] ?? '',
                'quantity' => $asset['quantity'] ?? 0,
                'total_quantity' => $asset['total_quantity'] ?? 0,
            ]);
        }, $rows);
    }

    protected function serializeRequirement(array $row): array
    {
        $available = max((int) ($row['quantity'] ?? 0), 0);
        $required = max((int) ($row['quantity_required'] ?? 1), 1);
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        $isAvailable = $available >= $required && ! in_array($status, ['maintenance', 'faulty'], true);

        return [
            'asset_id' => (int) ($row['asset_id'] ?? $row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'asset_code' => (string) ($row['asset_code'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'model' => (string) ($row['model'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'available_quantity' => $available,
            'total_quantity' => max((int) ($row['total_quantity'] ?? $available), $available),
            'quantity_required' => $required,
            'is_available' => $isAvailable,
        ];
    }

    protected function requirementsCurrentlyBookable(array $requirements): bool
    {
        if ($requirements === []) {
            return false;
        }

        foreach ($requirements as $requirement) {
            if (! ($requirement['is_available'] ?? false)) {
                return false;
            }
        }

        return true;
    }

    protected function bundleSummary(array $requirements): string
    {
        if ($requirements === []) {
            return '';
        }

        $parts = [];
        foreach ($requirements as $requirement) {
            $name = trim((string) ($requirement['name'] ?? ''));
            $quantity = max((int) ($requirement['quantity_required'] ?? 1), 1);
            if ($name !== '') {
                $parts[] = $name . ' x' . $quantity;
            }
        }

        return implode(' | ', $parts);
    }
}
