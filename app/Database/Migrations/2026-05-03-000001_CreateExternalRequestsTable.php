<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateExternalRequestsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
            ],
            'lab_id' => [
                'type' => 'INT',
                'unsigned' => true,
            ],
            'organization_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'contact_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'contact_email' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'contact_phone' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'participant_count' => [
                'type' => 'INT',
                'unsigned' => true,
                'default' => 1,
            ],
            'preferred_date' => [
                'type' => 'DATE',
            ],
            'preferred_start_time' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'preferred_end_time' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'purpose' => [
                'type' => 'TEXT',
            ],
            'equipment_notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => [
                    'submitted',
                    'under_review',
                    'needs_information',
                    'approved_for_scheduling',
                    'rejected',
                    'completed',
                ],
                'default' => 'submitted',
            ],
            'review_notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'reviewed_by' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
            ],
            'reviewed_at' => [
                'type' => 'DATETIME',
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
        $this->forge->addKey(['status', 'preferred_date']);
        $this->forge->addKey('lab_id');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'SET NULL');
        $this->forge->addForeignKey('lab_id', 'laboratories', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reviewed_by', 'users', 'id', 'SET NULL', 'SET NULL');

        $this->forge->createTable('external_requests', true);
    }

    public function down()
    {
        $this->forge->dropTable('external_requests', true);
    }
}
