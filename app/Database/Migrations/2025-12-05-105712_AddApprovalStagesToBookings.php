<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddApprovalStagesToBookings extends Migration
{
    public function up()
    {
        // New approval-stage columns
        $fields = [
            'approved_by_pic' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'after'      => 'status',
            ],
            'approved_by_manager' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'after'      => 'approved_by_pic',
            ],
        ];

        $this->forge->addColumn('bookings', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('bookings', 'approved_by_pic');
        $this->forge->dropColumn('bookings', 'approved_by_manager');
    }
}
