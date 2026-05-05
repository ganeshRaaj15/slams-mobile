<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceLabAndAssetManagement extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('assets')) {
            $assetFields = [];

            if (! $this->db->fieldExists('asset_code', 'assets')) {
                $assetFields['asset_code'] = ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'lab_id'];
            }
            if (! $this->db->fieldExists('category', 'assets')) {
                $assetFields['category'] = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'name'];
            }
            if (! $this->db->fieldExists('brand', 'assets')) {
                $assetFields['brand'] = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'category'];
            }
            if (! $this->db->fieldExists('model', 'assets')) {
                $assetFields['model'] = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'brand'];
            }
            if (! $this->db->fieldExists('serial_number', 'assets')) {
                $assetFields['serial_number'] = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'model'];
            }
            if (! $this->db->fieldExists('specifications', 'assets')) {
                $assetFields['specifications'] = ['type' => 'TEXT', 'null' => true, 'after' => 'serial_number'];
            }
            if (! $this->db->fieldExists('location_note', 'assets')) {
                $assetFields['location_note'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'status'];
            }
            if (! $this->db->fieldExists('purchase_date', 'assets')) {
                $assetFields['purchase_date'] = ['type' => 'DATE', 'null' => true, 'after' => 'location_note'];
            }

            if ($assetFields !== []) {
                $this->forge->addColumn('assets', $assetFields);
            }
        }

        if ($this->db->tableExists('laboratories')) {
            $labFields = [];

            if (! $this->db->fieldExists('description', 'laboratories')) {
                $labFields['description'] = ['type' => 'TEXT', 'null' => true, 'after' => 'room'];
            }
            if (! $this->db->fieldExists('capacity', 'laboratories')) {
                $labFields['capacity'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'description'];
            }
            if (! $this->db->fieldExists('availability_note', 'laboratories')) {
                $labFields['availability_note'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'capacity'];
            }
            if (! $this->db->fieldExists('safety_note', 'laboratories')) {
                $labFields['safety_note'] = ['type' => 'TEXT', 'null' => true, 'after' => 'availability_note'];
            }

            if ($labFields !== []) {
                $this->forge->addColumn('laboratories', $labFields);
            }
        }
    }

    public function down()
    {
        $assetFields = ['asset_code', 'category', 'brand', 'model', 'serial_number', 'specifications', 'location_note', 'purchase_date'];
        foreach ($assetFields as $field) {
            if ($this->db->fieldExists($field, 'assets')) {
                $this->forge->dropColumn('assets', $field);
            }
        }

        $labFields = ['description', 'capacity', 'availability_note', 'safety_note'];
        foreach ($labFields as $field) {
            if ($this->db->fieldExists($field, 'laboratories')) {
                $this->forge->dropColumn('laboratories', $field);
            }
        }
    }
}