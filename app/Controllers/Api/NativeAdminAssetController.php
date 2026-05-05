<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AssetModel;
use App\Models\LaboratoryModel;
use App\Models\MaintenanceRecordModel;
use CodeIgniter\Shield\Entities\User;

class NativeAdminAssetController extends BaseController
{
    protected AssetModel $assetModel;
    protected LaboratoryModel $labModel;
    protected MaintenanceRecordModel $maintenanceModel;

    public function __construct()
    {
        helper(['auth', 'filesystem']);
        $this->assetModel = new AssetModel();
        $this->labModel = new LaboratoryModel();
        $this->maintenanceModel = new MaintenanceRecordModel();

        $directory = FCPATH . 'images/assets';
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    public function index()
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $filters = [
            'q' => trim((string) $this->request->getGet('q')),
            'lab_id' => (int) $this->request->getGet('lab_id'),
            'status' => trim((string) $this->request->getGet('status')),
        ];
        if (! in_array($filters['status'], ['available', 'maintenance', 'faulty'], true)) {
            $filters['status'] = '';
        }

        $builder = $this->assetModel
            ->select('assets.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = assets.lab_id', 'left');

        if ($filters['q'] !== '') {
            $builder = $builder->groupStart()
                ->like('assets.name', $filters['q'])
                ->orLike('assets.asset_code', $filters['q'])
                ->orLike('assets.category', $filters['q'])
                ->orLike('assets.brand', $filters['q'])
                ->orLike('assets.model', $filters['q'])
                ->orLike('assets.serial_number', $filters['q'])
                ->orLike('laboratories.name', $filters['q'])
                ->groupEnd();
        }
        if ($filters['lab_id'] > 0) {
            $builder = $builder->where('assets.lab_id', $filters['lab_id']);
        }
        if ($filters['status'] !== '') {
            $builder = $builder->where('assets.status', $filters['status']);
        }

        $assets = $builder
            ->orderBy('laboratories.name', 'ASC')
            ->orderBy('assets.name', 'ASC')
            ->findAll();

        $maintenanceStats = $this->maintenanceStatsMap();
        foreach ($assets as &$asset) {
            $asset = $this->serializeAsset($asset, $maintenanceStats[(int) $asset['id']] ?? null);
        }
        unset($asset);

        return $this->response->setJSON([
            'status' => 'success',
            'assets' => $assets,
            'labs' => array_map(fn(array $lab): array => $this->serializeLabOption($lab), $this->labModel->orderBy('name', 'ASC')->findAll()),
            'filters' => $filters,
            'status_options' => ['available', 'maintenance', 'faulty'],
        ]);
    }

    public function show(int $id)
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $asset = $this->assetModel
            ->select('assets.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = assets.lab_id', 'left')
            ->where('assets.id', $id)
            ->first();

        if (! $asset) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Asset not found.',
            ]);
        }

        $maintenanceHistory = $this->maintenanceModel->withRelations()
            ->where('maintenance_records.asset_id', $id)
            ->orderBy('maintenance_records.created_at', 'DESC')
            ->findAll();

        return $this->response->setJSON([
            'status' => 'success',
            'asset' => $this->serializeAsset($asset, $this->maintenanceStatsMap()[$id] ?? null),
            'maintenance_history' => array_map(fn(array $record): array => $this->serializeMaintenanceHistory($record), $maintenanceHistory),
            'labs' => array_map(fn(array $lab): array => $this->serializeLabOption($lab), $this->labModel->orderBy('name', 'ASC')->findAll()),
        ]);
    }

    public function store()
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $payload = $this->collectPayload();
        if (! $this->validate($this->rules())) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Invalid asset payload.',
                'errors' => $this->validator->getErrors(),
            ]);
        }

        if ($duplicateMessage = $this->duplicateMessage($payload)) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => $duplicateMessage,
            ]);
        }

        $payload['image'] = $this->handleImageUpload();
        $payload['quantity'] = $payload['total_quantity'];
        $payload['status'] = 'available';
        $assetId = (int) $this->assetModel->insert($payload, true);

        $asset = $this->assetModel
            ->select('assets.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = assets.lab_id', 'left')
            ->where('assets.id', $assetId)
            ->first();

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Asset created successfully.',
            'asset' => $asset ? $this->serializeAsset($asset, null) : null,
        ]);
    }

    public function update(int $id)
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $asset = $this->assetModel->find($id);
        if (! $asset) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Asset not found.',
            ]);
        }

        $payload = $this->collectPayload();
        if (! $this->validate($this->rules())) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Invalid asset payload.',
                'errors' => $this->validator->getErrors(),
            ]);
        }

        if ($duplicateMessage = $this->duplicateMessage($payload, $id)) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => $duplicateMessage,
            ]);
        }

        $openUnits = min($this->assetModel->openMaintenanceUnits($id), $payload['total_quantity']);
        $payload['quantity'] = max($payload['total_quantity'] - $openUnits, 0);
        $payload['status'] = $openUnits > 0 ? 'maintenance' : 'available';
        $payload['image'] = $this->handleImageUpload($asset['image'] ?? null);

        $this->assetModel->update($id, $payload);
        $this->assetModel->syncManagedAvailability($id);

        $updated = $this->assetModel
            ->select('assets.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = assets.lab_id', 'left')
            ->where('assets.id', $id)
            ->first();

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Asset updated successfully.',
            'asset' => $updated ? $this->serializeAsset($updated, $this->maintenanceStatsMap()[$id] ?? null) : null,
        ]);
    }

    public function delete(int $id)
    {
        $user = $this->authorizedAdmin();
        if (! $user instanceof User) {
            return $user;
        }

        $asset = $this->assetModel->find($id);
        if (! $asset) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Asset not found.',
            ]);
        }

        $hasMaintenance = $this->maintenanceModel->where('asset_id', $id)->countAllResults() > 0;
        if ($hasMaintenance) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'This asset has maintenance history and cannot be deleted. Keep the record for traceability.',
            ]);
        }

        if (! empty($asset['image']) && is_file(FCPATH . $asset['image'])) {
            unlink(FCPATH . $asset['image']);
        }

        $this->assetModel->delete($id);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Asset deleted successfully.',
        ]);
    }

    protected function serializeAsset(array $asset, ?array $stat): array
    {
        $asset = $this->applyLegacyDefaults($asset);
        $maintenanceQuantity = max($asset['total_quantity'] - $asset['quantity'], 0);

        return [
            'id' => (int) $asset['id'],
            'lab_id' => (int) ($asset['lab_id'] ?? 0),
            'lab_name' => (string) ($asset['lab_name'] ?? ''),
            'lab_room' => (string) ($asset['lab_room'] ?? ''),
            'asset_code' => (string) ($asset['asset_code'] ?? ''),
            'name' => (string) ($asset['name'] ?? ''),
            'category' => (string) ($asset['category'] ?? ''),
            'brand' => (string) ($asset['brand'] ?? ''),
            'model' => (string) ($asset['model'] ?? ''),
            'serial_number' => (string) ($asset['serial_number'] ?? ''),
            'specifications' => (string) ($asset['specifications'] ?? ''),
            'status' => (string) ($asset['status'] ?? ''),
            'location_note' => (string) ($asset['location_note'] ?? ''),
            'purchase_date' => (string) ($asset['purchase_date'] ?? ''),
            'quantity' => (int) ($asset['quantity'] ?? 0),
            'total_quantity' => (int) ($asset['total_quantity'] ?? 0),
            'maintenance_quantity' => $maintenanceQuantity,
            'image' => (string) ($asset['image'] ?? ''),
            'image_url' => ! empty($asset['image']) ? base_url('/' . ltrim((string) $asset['image'], '/')) : '',
            'maintenance_total' => (int) ($stat['total_records'] ?? 0),
            'maintenance_open' => (int) ($stat['open_records'] ?? 0),
            'last_completed_at' => (string) ($stat['last_completed_at'] ?? ''),
            'last_reported_at' => (string) ($stat['last_reported_at'] ?? ''),
        ];
    }

    protected function serializeMaintenanceHistory(array $record): array
    {
        return [
            'id' => (int) $record['id'],
            'title' => (string) ($record['title'] ?? ''),
            'status' => (string) ($record['status'] ?? ''),
            'status_label' => $this->maintenanceModel->statusLabel((string) ($record['status'] ?? 'reported')),
            'priority' => (string) ($record['priority'] ?? ''),
            'issue_type' => (string) ($record['issue_type'] ?? ''),
            'quantity_affected' => (int) ($record['quantity_affected'] ?? 0),
            'reported_by_name' => (string) ($record['reported_by_name'] ?? $record['reported_by_username'] ?? ''),
            'technician_name' => (string) ($record['technician_name'] ?? $record['technician_username'] ?? ''),
            'created_at' => (string) ($record['created_at'] ?? ''),
            'completed_at' => (string) ($record['completed_at'] ?? ''),
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

    protected function rules(): array
    {
        return [
            'asset_code' => 'required|min_length[3]|max_length[50]',
            'name' => 'required|min_length[3]|max_length[255]',
            'category' => 'permit_empty|max_length[100]',
            'brand' => 'permit_empty|max_length[100]',
            'model' => 'permit_empty|max_length[100]',
            'serial_number' => 'permit_empty|max_length[100]',
            'lab_id' => 'required|integer',
            'total_quantity' => 'required|integer|greater_than[0]',
            'location_note' => 'permit_empty|max_length[255]',
            'purchase_date' => 'permit_empty|valid_date[Y-m-d]',
            'specifications' => 'permit_empty|string',
            'image' => 'permit_empty|max_size[image,2048]|ext_in[image,jpg,jpeg,png,gif,webp]',
        ];
    }

    protected function collectPayload(): array
    {
        return [
            'asset_code' => strtoupper(trim((string) $this->request->getPost('asset_code'))),
            'name' => trim((string) $this->request->getPost('name')),
            'category' => trim((string) $this->request->getPost('category')),
            'brand' => trim((string) $this->request->getPost('brand')),
            'model' => trim((string) $this->request->getPost('model')),
            'serial_number' => trim((string) $this->request->getPost('serial_number')),
            'lab_id' => (int) $this->request->getPost('lab_id'),
            'total_quantity' => (int) $this->request->getPost('total_quantity'),
            'location_note' => trim((string) $this->request->getPost('location_note')),
            'purchase_date' => trim((string) $this->request->getPost('purchase_date')) ?: null,
            'specifications' => trim((string) $this->request->getPost('specifications')),
        ];
    }

    protected function duplicateMessage(array $payload, int $ignoreId = 0): ?string
    {
        $codeQuery = $this->assetModel->where('asset_code', $payload['asset_code']);
        if ($ignoreId > 0) {
            $codeQuery->where('id !=', $ignoreId);
        }
        if ($payload['asset_code'] !== '' && $codeQuery->first()) {
            return 'Asset code already exists.';
        }

        if ($payload['serial_number'] !== '') {
            $serialQuery = $this->assetModel->where('serial_number', $payload['serial_number']);
            if ($ignoreId > 0) {
                $serialQuery->where('id !=', $ignoreId);
            }
            if ($serialQuery->first()) {
                return 'Serial number already exists.';
            }
        }

        return null;
    }

    protected function handleImageUpload(?string $currentImage = null): ?string
    {
        $file = $this->request->getFile('image');
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return $currentImage;
        }

        if ($currentImage && is_file(FCPATH . $currentImage)) {
            unlink(FCPATH . $currentImage);
        }

        $newName = $file->getRandomName();
        if ($file->move(FCPATH . 'images/assets', $newName)) {
            return 'images/assets/' . $newName;
        }

        return $currentImage;
    }

    protected function applyLegacyDefaults(array $asset): array
    {
        $asset['asset_code'] = ! empty($asset['asset_code']) ? $asset['asset_code'] : ('AST-' . str_pad((string) ($asset['id'] ?? 0), 4, '0', STR_PAD_LEFT));
        $asset['category'] = ! empty($asset['category']) ? $asset['category'] : 'General Equipment';
        $asset['total_quantity'] = max((int) ($asset['total_quantity'] ?? 0), (int) ($asset['quantity'] ?? 0), 1);
        $asset['quantity'] = max((int) ($asset['quantity'] ?? 0), 0);
        $asset['status'] = ! empty($asset['status']) ? $asset['status'] : 'available';

        return $asset;
    }

    protected function maintenanceStatsMap(): array
    {
        $statsRows = $this->maintenanceModel
            ->select("asset_id, COUNT(*) AS total_records, SUM(CASE WHEN status IN ('reported','scheduled','in_progress','testing') THEN 1 ELSE 0 END) AS open_records, MAX(completed_at) AS last_completed_at, MAX(created_at) AS last_reported_at")
            ->groupBy('asset_id')
            ->findAll();

        $maintenanceStats = [];
        foreach ($statsRows as $row) {
            $maintenanceStats[(int) $row['asset_id']] = $row;
        }

        return $maintenanceStats;
    }

    protected function authorizedAdmin()
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ]);
        }

        if (! $user->inGroup('admin')) {
            return $this->response->setStatusCode(403)->setJSON([
                'status' => 'error',
                'message' => 'Unauthorized access.',
            ]);
        }

        return $user;
    }
}
