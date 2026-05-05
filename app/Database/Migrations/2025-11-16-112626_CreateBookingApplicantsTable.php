<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBookingApplicantsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'unsigned' => true,
                'auto_increment' => true
            ],

            'booking_id' => [
                'type' => 'INT',
                'unsigned' => true
            ],

            'name' => [
                'type' => 'VARCHAR',
                'constraint' => '255'
            ],

            'matric_id' => [
                'type' => 'VARCHAR',
                'constraint' => '50'
            ],

            'email' => [
                'type' => 'VARCHAR',
                'constraint' => '255'
            ],

            'phone' => [
                'type' => 'VARCHAR',
                'constraint' => '50'
            ],

            'faculty' => [
                'type' => 'VARCHAR',
                'constraint' => '255'
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('booking_id', 'bookings', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('booking_applicants');
    }

    public function down()
    {
        $this->forge->dropTable('booking_applicants');
    }
}
