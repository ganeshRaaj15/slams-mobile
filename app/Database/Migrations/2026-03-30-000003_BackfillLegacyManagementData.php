<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillLegacyManagementData extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('assets')) {
            $assets = $this->db->table('assets')->select('id, name, asset_code, category')->get()->getResultArray();

            foreach ($assets as $asset) {
                $updates = [];

                if (empty($asset['asset_code'])) {
                    $updates['asset_code'] = 'AST-' . str_pad((string) $asset['id'], 4, '0', STR_PAD_LEFT);
                }

                if (empty($asset['category'])) {
                    $updates['category'] = 'General Equipment';
                }

                if ($updates !== []) {
                    $this->db->table('assets')->where('id', $asset['id'])->update($updates);
                }
            }
        }
    }

    public function down()
    {
        // No destructive rollback for legacy data backfill.
    }
}