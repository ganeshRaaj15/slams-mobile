<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePushSubscriptionsTable extends Migration
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
            'endpoint' => [
                'type' => 'VARCHAR',
                'constraint' => 512,
            ],
            'public_key' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'auth_token' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'content_encoding' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'aes128gcm',
            ],
            'user_agent' => [
                'type' => 'TEXT',
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
        $this->forge->addUniqueKey('endpoint');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('push_subscriptions', true);
    }

    public function down()
    {
        $this->forge->dropTable('push_subscriptions', true);
    }
}
