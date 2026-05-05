<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNativePushTokensTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'expo_push_token' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'platform' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'unknown',
            ],
            'device_name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
            ],
            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
            ],
            'last_used_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_error_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_error_message' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'is_active']);
        $this->forge->addUniqueKey('expo_push_token');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('native_push_tokens', true);
    }

    public function down()
    {
        $this->forge->dropTable('native_push_tokens', true);
    }
}
