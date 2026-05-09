<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AssetModel;
use App\Models\LaboratoryModel;
use App\Models\MaintenanceRecordModel;

class AssetController extends BaseController
{
    protected AssetModel $assetModel;
    protected LaboratoryModel $labModel;
    protected MaintenanceRecordModel $maintenanceModel;

    public function __construct()
    {
        helper(['auth', 'filesystem', 'qr']);

        if (! auth()->loggedIn() || ! auth()->user()->inGroup('admin')) {
            redirect()->to('/')->send();
            exit;
        }

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

        $maintenanceStats = [];
        $statsRows = $this->maintenanceModel
            ->select("asset_id, COUNT(*) AS total_records, SUM(CASE WHEN status IN ('reported','scheduled','in_progress','testing') THEN 1 ELSE 0 END) AS open_records, MAX(completed_at) AS last_completed_at, MAX(created_at) AS last_reported_at")
            ->groupBy('asset_id')
            ->findAll();

        foreach ($statsRows as $row) {
            $maintenanceStats[$row['asset_id']] = $row;
        }

        foreach ($assets as &$asset) {
            $asset = $this->applyLegacyDefaults($asset);
            $asset['maintenance_quantity'] = max($asset['total_quantity'] - $asset['quantity'], 0);
            $stat = $maintenanceStats[$asset['id']] ?? null;
            $asset['maintenance_total'] = (int) ($stat['total_records'] ?? 0);
            $asset['maintenance_open'] = (int) ($stat['open_records'] ?? 0);
            $asset['last_completed_at'] = $stat['last_completed_at'] ?? null;
            $asset['last_reported_at'] = $stat['last_reported_at'] ?? null;
        }
        unset($asset);

        return view('admin/assets/index', [
            'assets' => $assets,
            'labs' => $this->labModel->orderBy('name', 'ASC')->findAll(),
            'filters' => $filters,
            'statusOptions' => ['available', 'maintenance', 'faulty'],
        ]);
    }

    public function qrLabels()
    {
        $search = trim((string) $this->request->getGet('q'));

        $builder = $this->assetModel
            ->select('assets.*, laboratories.name AS lab_name, laboratories.room AS lab_room')
            ->join('laboratories', 'laboratories.id = assets.lab_id', 'left');

        if ($search !== '') {
            $builder->groupStart()
                ->like('assets.name', $search)
                ->orLike('assets.asset_code', $search)
                ->orLike('laboratories.name', $search)
                ->groupEnd();
        }

        $assets = $builder
            ->orderBy('laboratories.name', 'ASC')
            ->orderBy('assets.name', 'ASC')
            ->findAll();

        foreach ($assets as &$asset) {
            $asset = $this->applyLegacyDefaults($asset);
        }
        unset($asset);

        return view('admin/assets/qr_labels', [
            'assets' => $assets,
            'search' => $search,
        ]);
    }
    public function create()
    {
        return view('admin/assets/form', [
            'mode' => 'create',
            'labs' => $this->labModel->orderBy('name', 'ASC')->findAll(),
            'asset' => $this->applyLegacyDefaults([]),
            'maintenanceHistory' => [],
        ]);
    }

    public function store()
    {
        $payload = $this->collectPayload();

        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($duplicateMessage = $this->duplicateMessage($payload)) {
            return redirect()->back()->withInput()->with('errors', ['asset_code' => $duplicateMessage]);
        }

        $payload['image'] = $this->handleImageUpload();
        $payload['quantity'] = $payload['total_quantity'];
        $payload['status'] = 'available';
        $this->assetModel->insert($payload);

        return redirect()->to('/admin/assets')->with('message', 'Asset created successfully.');
    }

    public function edit($id)
    {
        $asset = $this->assetModel->find($id);
        if (! $asset) {
            return redirect()->to('/admin/assets')->with('error', 'Asset not found.');
        }

        $asset = $this->applyLegacyDefaults($asset);
        $asset['maintenance_quantity'] = max($asset['total_quantity'] - $asset['quantity'], 0);

        $maintenanceHistory = $this->maintenanceModel->withRelations()
            ->where('maintenance_records.asset_id', $id)
            ->orderBy('maintenance_records.created_at', 'DESC')
            ->findAll();

        return view('admin/assets/form', [
            'mode' => 'edit',
            'asset' => $asset,
            'labs' => $this->labModel->orderBy('name', 'ASC')->findAll(),
            'maintenanceHistory' => $maintenanceHistory,
        ]);
    }

    public function update($id)
    {
        $asset = $this->assetModel->find($id);
        if (! $asset) {
            return redirect()->to('/admin/assets')->with('error', 'Asset not found.');
        }

        $asset = $this->applyLegacyDefaults($asset);
        $payload = $this->collectPayload();

        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($duplicateMessage = $this->duplicateMessage($payload, (int) $id)) {
            return redirect()->back()->withInput()->with('errors', ['asset_code' => $duplicateMessage]);
        }

        $openUnits = min($this->assetModel->openMaintenanceUnits((int) $id), $payload['total_quantity']);
        $payload['quantity'] = max($payload['total_quantity'] - $openUnits, 0);
        $payload['status'] = $openUnits > 0 ? 'maintenance' : 'available';
        $payload['image'] = $this->handleImageUpload($asset['image'] ?? null);
        $this->assetModel->update($id, $payload);
        $this->assetModel->syncManagedAvailability((int) $id);

        return redirect()->to('/admin/assets')->with('message', 'Asset updated successfully.');
    }

    public function delete($id)
    {
        $asset = $this->assetModel->find($id);
        if (! $asset) {
            return redirect()->to('/admin/assets')->with('error', 'Asset not found.');
        }

        $hasMaintenance = $this->maintenanceModel->where('asset_id', $id)->countAllResults() > 0;
        if ($hasMaintenance) {
            $message = 'This asset has maintenance history and cannot be deleted. Keep the record for traceability.';
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON(['status' => 'error', 'message' => $message]);
            }
            return redirect()->to('/admin/assets')->with('error', $message);
        }

        if (! empty($asset['image']) && is_file(FCPATH . $asset['image'])) {
            unlink(FCPATH . $asset['image']);
        }

        $this->assetModel->delete($id);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['status' => 'success']);
        }

        return redirect()->to('/admin/assets')->with('message', 'Asset deleted successfully.');
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
            'image' => 'permit_empty|max_size[image,2048]|ext_in[image,jpg,jpeg,png,gif]',
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
}
