<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProfileFieldsToUsers extends Migration
{
    public function up()
    {
        $fields = [
            'full_name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
            ],
            'phone' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'null' => true,
            ],
            'faculty_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
            ],
            'profile_photo' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
        ];

        $this->forge->addColumn('users', $fields);
        $this->forge->addKey('faculty_id');
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['full_name', 'phone', 'faculty_id', 'profile_photo']);
    }
}
