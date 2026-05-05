<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceMaintenanceWorkflow extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('maintenance_records')) {
            $fields = [];

            if (! $this->db->fieldExists('unit_reference', 'maintenance_records')) {
                $fields['unit_reference'] = [
                    'type' => 'VARCHAR',
                    'constraint' => 120,
                    'null' => true,
                    'after' => 'quantity_affected',
                ];
            }
            if (! $this->db->fieldExists('report_photo_path', 'maintenance_records')) {
                $fields['report_photo_path'] = [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                    'after' => 'description',
                ];
            }
            if (! $this->db->fieldExists('accepted_at', 'maintenance_records')) {
                $fields['accepted_at'] = [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'scheduled_for',
                ];
            }
            if (! $this->db->fieldExists('diagnosis_notes', 'maintenance_records')) {
                $fields['diagnosis_notes'] = [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'accepted_at',
                ];
            }
            if (! $this->db->fieldExists('work_notes', 'maintenance_records')) {
                $fields['work_notes'] = [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'diagnosis_notes',
                ];
            }
            if (! $this->db->fieldExists('tested_at', 'maintenance_records')) {
                $fields['tested_at'] = [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'work_notes',
                ];
            }
            if (! $this->db->fieldExists('test_notes', 'maintenance_records')) {
                $fields['test_notes'] = [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'tested_at',
                ];
            }
            if (! $this->db->fieldExists('completion_photo_path', 'maintenance_records')) {
                $fields['completion_photo_path'] = [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                    'after' => 'test_notes',
                ];
            }

            if ($fields !== []) {
                $this->forge->addColumn('maintenance_records', $fields);
            }

            $this->db->query("ALTER TABLE maintenance_records MODIFY status ENUM('reported','scheduled','in_progress','testing','completed','cancelled') NOT NULL DEFAULT 'reported'");
        }
    }

    public function down()
    {
        if ($this->db->tableExists('maintenance_records')) {
            foreach (['unit_reference', 'report_photo_path', 'accepted_at', 'diagnosis_notes', 'work_notes', 'tested_at', 'test_notes', 'completion_photo_path'] as $field) {
                if ($this->db->fieldExists($field, 'maintenance_records')) {
                    $this->forge->dropColumn('maintenance_records', $field);
                }
            }

            $this->db->query("ALTER TABLE maintenance_records MODIFY status ENUM('reported','scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'reported'");
        }
    }
}
