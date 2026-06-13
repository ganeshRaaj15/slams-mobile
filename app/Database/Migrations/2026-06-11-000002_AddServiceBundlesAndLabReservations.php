<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddServiceBundlesAndLabReservations extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('lab_services') && $this->db->tableExists('assets') && ! $this->db->tableExists('service_asset_requirements')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'lab_service_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'asset_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'quantity_required' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'default' => 1,
                ],
                'sort_order' => [
                    'type' => 'INT',
                    'constraint' => 11,
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
            $this->forge->addUniqueKey(['lab_service_id', 'asset_id']);
            $this->forge->addForeignKey('lab_service_id', 'lab_services', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('asset_id', 'assets', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->createTable('service_asset_requirements', true);
        }

        if (! $this->db->tableExists('lab_reservations')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'lab_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'title' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'reservation_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                    'default' => 'manual_block',
                ],
                'start_at' => [
                    'type' => 'DATETIME',
                ],
                'end_at' => [
                    'type' => 'DATETIME',
                ],
                'notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'default' => 'active',
                ],
                'created_by' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
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
            $this->forge->addKey(['lab_id', 'status']);
            $this->forge->addForeignKey('lab_id', 'laboratories', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('lab_reservations', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('lab_reservations', true);
        $this->forge->dropTable('service_asset_requirements', true);
    }
}
