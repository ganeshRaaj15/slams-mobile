<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFacultyAndApprovalFlowToBookings extends Migration
{
    public function up()
    {
        // Add new columns
        $fields = [
            'faculty_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'user_type',
            ],
            'approval_flow' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'user_type',
            ],
        ];

        $this->forge->addColumn('bookings', $fields);

        // Add foreign key constraint
        $this->db->query("
            ALTER TABLE `bookings`
            ADD CONSTRAINT `bookings_faculty_id_fk`
                FOREIGN KEY (`faculty_id`)
                REFERENCES `faculties`(`id`)
                ON UPDATE CASCADE
                ON DELETE SET NULL
        ");
    }

    public function down()
    {
        // Drop FK first
        $this->db->query("
            ALTER TABLE `bookings`
            DROP FOREIGN KEY `bookings_faculty_id_fk`
        ");

        // Then drop columns
        $this->forge->dropColumn('bookings', 'faculty_id');
        $this->forge->dropColumn('bookings', 'approval_flow');
    }
}
