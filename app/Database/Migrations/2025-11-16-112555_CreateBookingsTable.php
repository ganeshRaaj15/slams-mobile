<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBookingsTable extends Migration
{
     public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'unsigned' => true,
                'auto_increment' => true
            ],

            'lab_id' => [
                'type' => 'INT',
                'unsigned' => true
            ],

            'user_type' => [
                'type' => 'ENUM',
                'constraint' => ['UTHM', 'EXTERNAL'],
                'default' => 'UTHM'
            ],

            // Date + time slot (fixed range, stored cleanly)
            'date' => [
                'type' => 'DATE'
            ],
            'start_time' => [
                'type' => 'TIME'
            ],
            'end_time' => [
                'type' => 'TIME'
            ],

            // Activity
            'activity' => [
                'type' => 'TEXT',
                'null' => true
            ],

            // Supervisor info (students only)
            'supervisor_name' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true
            ],
            'supervisor_email' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true
            ],
            'supervisor_phone' => [
                'type' => 'VARCHAR',
                'constraint' => '50',
                'null' => true
            ],

            // File upload (PDF)
            'pdf_path' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => true
            ],

            // Approval workflow
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['PENDING', 'APPROVED', 'REJECTED'],
                'default' => 'PENDING'
            ],

            'created_at' => [
                'type' => 'DATETIME',
                'null' => false
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('lab_id', 'laboratories', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('bookings');
    }

    public function down()
    {
        $this->forge->dropTable('bookings');
    }
}
