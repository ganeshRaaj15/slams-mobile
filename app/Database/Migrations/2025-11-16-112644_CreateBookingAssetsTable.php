<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBookingAssetsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'unsigned' => true,
                'auto_increment' => true
            ],

            'booking_id' => [
                'type' => 'INT',
                'unsigned' => true
            ],

            'asset_id' => [
                'type' => 'INT',
                'unsigned' => true
            ],

            'quantity_used' => [
                'type' => 'INT',
                'unsigned' => true,
                'default' => 1
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('booking_id', 'bookings', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('asset_id', 'assets', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('booking_assets');
    }

    public function down()
    {
        $this->forge->dropTable('booking_assets');
    }
}
