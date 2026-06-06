<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNativeOtpTokensTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
            ],
            'otp_token' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'otp_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'device_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('otp_token');
        $this->forge->addKey(['user_id', 'expires_at']);

        $this->forge->createTable('native_otp_tokens');
    }

    public function down(): void
    {
        $this->forge->dropTable('native_otp_tokens', true);
    }
}
