<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAssetsTable extends Migration
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
            'type' => 'INT',
            'unsigned' => true,
        ],
        'name' => [
            'type' => 'VARCHAR',
            'constraint' => '255',
        ],
        'status' => [
            'type' => 'ENUM',
            'constraint' => ['available', 'maintenance', 'faulty'],
            'default' => 'available',
        ],
        'quantity' => [
            'type' => 'INT',
            'default' => 1,
        ],
        'image' => [
            'type' => 'VARCHAR',
            'constraint' => '255',
            'null' => true,
        ],
    ]);

    $this->forge->addKey('id', true);
    $this->forge->addForeignKey('lab_id', 'laboratories', 'id', 'CASCADE', 'CASCADE');

    $this->forge->createTable('assets');
}

public function down()
{
    $this->forge->dropTable('assets');
}
}
