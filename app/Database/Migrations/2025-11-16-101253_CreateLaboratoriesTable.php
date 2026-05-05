<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLaboratoriesTable extends Migration
{
    public function up()
{
    $this->forge->addField([
        'id' => [
            'type'           => 'INT',
            'unsigned'       => true,
            'auto_increment' => true,
        ],
        'name' => [
            'type' => 'VARCHAR',
            'constraint' => '255',
        ],
        'room' => [
            'type' => 'VARCHAR',
            'constraint' => '50',
            'null' => true,
        ],
        'pic_name' => [
            'type' => 'VARCHAR',
            'constraint' => '255',
        ],
        'pic_email' => [
            'type' => 'VARCHAR',
            'constraint' => '255',
            'null' => true,
        ],
        'pic_phone' => [
            'type' => 'VARCHAR',
            'constraint' => '30',
            'null' => true,
        ],
        'image' => [
            'type' => 'VARCHAR',
            'constraint' => '255',
            'null' => true,
        ],
        'pic_image' => [
            'type' => 'VARCHAR',
            'constraint' => '255',
            'null' => true,
        ],
    ]);

    $this->forge->addKey('id', true);
    $this->forge->createTable('laboratories');
}

public function down()
{
    $this->forge->dropTable('laboratories');
}
}
