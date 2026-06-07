<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLabReservationsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'lab_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['manual', 'class'],
                'default'    => 'manual',
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'recurrence' => [
                'type'       => 'ENUM',
                'constraint' => ['none', 'weekly'],
                'default'    => 'none',
            ],
            'date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'day_of_week' => [
                'type'     => 'TINYINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'start_time' => [
                'type' => 'TIME',
            ],
            'end_time' => [
                'type' => 'TIME',
            ],
            'valid_from' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'valid_until' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'created_by' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'notes' => [
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
        $this->forge->addKey(['lab_id', 'date']);
        $this->forge->addKey(['lab_id', 'day_of_week']);
        $this->forge->addForeignKey('lab_id', 'laboratories', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('lab_reservations', true);
    }

    public function down()
    {
        $this->forge->dropTable('lab_reservations', true);
    }
}
