<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTwofaEnabledToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'twofa_enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
                'after'      => 'profile_photo',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'twofa_enabled');
    }
}
