<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUserIdToBookings extends Migration
{
    public function up()
    {
        // Add the user_id column
        $this->forge->addColumn('bookings', [
            'user_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,   // allow NULL for external users
                'after'      => 'id'
            ],
        ]);

        // Add foreign key constraint
        $this->db->query("
            ALTER TABLE bookings 
            ADD CONSTRAINT fk_bookings_user 
            FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ");
    }

    public function down()
    {
        // Drop the foreign key THEN the column
        $this->db->query("ALTER TABLE bookings DROP FOREIGN KEY fk_bookings_user");
        $this->forge->dropColumn('bookings', 'user_id');
    }
}
