<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMaintenanceTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'asset_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
            ],
            'reported_by' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
            ],
            'assigned_technician_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
            ],
            'title' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'issue_type' => [
                'type' => 'ENUM',
                'constraint' => ['preventive', 'corrective', 'inspection', 'calibration', 'other'],
                'default' => 'corrective',
            ],
            'priority' => [
                'type' => 'ENUM',
                'constraint' => ['low', 'medium', 'high', 'critical'],
                'default' => 'medium',
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['reported', 'scheduled', 'in_progress', 'completed', 'cancelled'],
                'default' => 'reported',
            ],
            'asset_status_before' => [
                'type' => 'ENUM',
                'constraint' => ['available', 'maintenance', 'faulty'],
                'default' => 'available',
            ],
            'asset_status_after' => [
                'type' => 'ENUM',
                'constraint' => ['available', 'maintenance', 'faulty'],
                'null' => true,
            ],
            'scheduled_for' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'started_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'resolution_notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('asset_id');
        $this->forge->addKey('reported_by');
        $this->forge->addKey('assigned_technician_id');
        $this->forge->addForeignKey('asset_id', 'assets', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reported_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('assigned_technician_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('maintenance_records');

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'maintenance_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
            ],
            'changed_by' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
            ],
            'from_status' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'to_status' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('maintenance_id');
        $this->forge->addKey('changed_by');
        $this->forge->addForeignKey('maintenance_id', 'maintenance_records', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('changed_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('maintenance_logs');
    }

    public function down()
    {
        $this->forge->dropTable('maintenance_logs', true);
        $this->forge->dropTable('maintenance_records', true);
    }
}
