<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddManagedAssetAvailability extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('assets') && ! $this->db->fieldExists('total_quantity', 'assets')) {
            $this->forge->addColumn('assets', [
                'total_quantity' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'quantity',
                ],
            ]);
        }

        if ($this->db->tableExists('maintenance_records') && ! $this->db->fieldExists('quantity_affected', 'maintenance_records')) {
            $this->forge->addColumn('maintenance_records', [
                'quantity_affected' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'default' => 1,
                    'after' => 'asset_id',
                ],
            ]);
        }

        if ($this->db->tableExists('assets')) {
            $this->db->query('UPDATE assets SET total_quantity = quantity WHERE total_quantity IS NULL OR total_quantity < quantity');
        }

        if ($this->db->tableExists('maintenance_records')) {
            $this->db->query('UPDATE maintenance_records SET quantity_affected = 1 WHERE quantity_affected IS NULL OR quantity_affected < 1');
        }

        if ($this->db->tableExists('assets') && $this->db->tableExists('maintenance_records')) {
            $assets = $this->db->table('assets')->select('id, quantity, total_quantity, status')->get()->getResultArray();

            foreach ($assets as $asset) {
                $assetId = (int) $asset['id'];
                $total = max((int) ($asset['total_quantity'] ?? 0), (int) ($asset['quantity'] ?? 0), 1);

                $openRow = $this->db->table('maintenance_records')
                    ->selectSum('quantity_affected')
                    ->where('asset_id', $assetId)
                    ->whereIn('status', ['reported', 'scheduled', 'in_progress'])
                    ->get()
                    ->getRowArray();

                $openUnits = min(max((int) ($openRow['quantity_affected'] ?? 0), 0), $total);
                $available = max($total - $openUnits, 0);
                $status = $openUnits > 0 ? 'maintenance' : ((($asset['status'] ?? '') === 'faulty' && (int) ($asset['quantity'] ?? 0) === 0) ? 'faulty' : 'available');

                $this->db->table('assets')
                    ->where('id', $assetId)
                    ->update([
                        'quantity' => $available,
                        'total_quantity' => $total,
                        'status' => $status,
                    ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('total_quantity', 'assets')) {
            $this->forge->dropColumn('assets', 'total_quantity');
        }

        if ($this->db->fieldExists('quantity_affected', 'maintenance_records')) {
            $this->forge->dropColumn('maintenance_records', 'quantity_affected');
        }
    }
}
