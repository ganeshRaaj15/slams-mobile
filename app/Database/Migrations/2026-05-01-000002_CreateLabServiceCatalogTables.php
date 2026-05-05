<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLabServiceCatalogTables extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('lab_services')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'laboratory_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'field_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 150,
                    'null' => true,
                ],
                'service_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'acceptance_criteria' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'calibration_status' => [
                    'type' => 'ENUM',
                    'constraint' => ['valid', 'expired', 'unknown'],
                    'default' => 'unknown',
                ],
                'service_notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'source_row_no' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                ],
                'is_active' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey(['laboratory_id', 'service_name']);
            $this->forge->addForeignKey('laboratory_id', 'laboratories', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('lab_services', true);
        }

        if (! $this->db->tableExists('service_equipment_models')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'lab_service_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'equipment_model' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
                'criteria_note' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'calibration_status' => [
                    'type' => 'ENUM',
                    'constraint' => ['valid', 'expired', 'unknown'],
                    'default' => 'unknown',
                ],
                'source_row_no' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                ],
                'sort_order' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'default' => 0,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey(['lab_service_id', 'sort_order']);
            $this->forge->addForeignKey('lab_service_id', 'lab_services', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('service_equipment_models', true);
        }

        if ($this->db->tableExists('assets') && ! $this->db->fieldExists('lab_service_id', 'assets')) {
            $this->forge->addColumn('assets', [
                'lab_service_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'lab_id',
                ],
            ]);

            $this->db->query('ALTER TABLE `assets` ADD INDEX `idx_assets_lab_service_id` (`lab_service_id`)');
        }
    }

    public function down()
    {
        if ($this->db->tableExists('assets') && $this->db->fieldExists('lab_service_id', 'assets')) {
            $this->db->query('ALTER TABLE `assets` DROP INDEX `idx_assets_lab_service_id`');
            $this->forge->dropColumn('assets', 'lab_service_id');
        }

        $this->forge->dropTable('service_equipment_models', true);
        $this->forge->dropTable('lab_services', true);
    }
}
