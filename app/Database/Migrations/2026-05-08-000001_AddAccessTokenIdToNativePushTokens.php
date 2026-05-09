<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAccessTokenIdToNativePushTokens extends Migration
{
    private const FOREIGN_KEY_NAME = 'native_push_tokens_access_token_id_fk';

    public function up()
    {
        if (! $this->db->tableExists('native_push_tokens')) {
            return;
        }

        if (! $this->db->fieldExists('access_token_id', 'native_push_tokens')) {
            $this->forge->addColumn('native_push_tokens', [
                'access_token_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'user_id',
                ],
            ]);
        }

        $this->forge->addKey('access_token_id');
        $this->forge->addForeignKey('access_token_id', 'auth_identities', 'id', 'CASCADE', 'CASCADE', self::FOREIGN_KEY_NAME);
        $this->forge->processIndexes('native_push_tokens');
    }

    public function down()
    {
        if (! $this->db->tableExists('native_push_tokens')) {
            return;
        }

        if ($this->db->fieldExists('access_token_id', 'native_push_tokens')) {
            $this->forge->dropForeignKey('native_push_tokens', self::FOREIGN_KEY_NAME);
            $this->forge->dropColumn('native_push_tokens', 'access_token_id');
        }
    }
}
