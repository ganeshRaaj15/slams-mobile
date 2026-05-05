<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetModel extends Model
{
    protected $table = 'assets';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'lab_id',
        'lab_service_id',
        'asset_code',
        'name',
        'category',
        'brand',
        'model',
        'serial_number',
        'specifications',
        'status',
        'location_note',
        'purchase_date',
        'quantity',
        'total_quantity',
        'image',
    ];

    public function totalQuantity(array $asset): int
    {
        $available = max((int) ($asset['quantity'] ?? 0), 0);
        $total = max((int) ($asset['total_quantity'] ?? 0), 0);

        if ($total < $available) {
            $total = $available;
        }

        return max($total, 1);
    }

    public function openMaintenanceUnits(int $assetId, int $ignoreRecordId = 0): int
    {
        $builder = $this->db->table('maintenance_records')
            ->selectSum('quantity_affected')
            ->where('asset_id', $assetId)
            ->whereIn('status', ['reported', 'scheduled', 'in_progress', 'testing']);

        if ($ignoreRecordId > 0) {
            $builder->where('id !=', $ignoreRecordId);
        }

        $row = $builder->get()->getRowArray();
        return max((int) ($row['quantity_affected'] ?? 0), 0);
    }

    public function syncManagedAvailability(int $assetId, ?int $ignoreRecordId = null): array
    {
        $asset = $this->find($assetId);
        if (! $asset) {
            return [];
        }

        $total = $this->totalQuantity($asset);
        $openUnits = min($this->openMaintenanceUnits($assetId, (int) $ignoreRecordId), $total);
        $available = max($total - $openUnits, 0);

        $status = $openUnits > 0 ? 'maintenance' : 'available';
        if ($openUnits === 0 && ($asset['status'] ?? '') === 'faulty' && (int) ($asset['quantity'] ?? 0) === 0) {
            $status = 'faulty';
            $available = 0;
        }

        $updates = [
            'quantity' => $available,
            'total_quantity' => $total,
            'status' => $status,
        ];

        $this->update($assetId, $updates);

        return array_merge($asset, $updates, [
            'maintenance_quantity' => $openUnits,
        ]);
    }
}
