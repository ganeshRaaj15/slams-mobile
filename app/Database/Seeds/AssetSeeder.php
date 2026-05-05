<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'lab_id' => 1,
                'name' => 'Projector',
                'status' => 'available',
                'quantity' => 2,
                'image' => '/images/assets/placeholder_asset.png',
            ],
            [
                'lab_id' => 1,
                'name' => 'Desktop Computer',
                'status' => 'available',
                'quantity' => 20,
                'image' => '/images/assets/placeholder_asset.png',
            ],
            [
                'lab_id' => 2,
                'name' => '3D Printer',
                'status' => 'maintenance',
                'quantity' => 1,
                'image' => '/images/assets/placeholder_asset.png',
            ],
            [
                'lab_id' => 3,
                'name' => 'Hydraulic Bench',
                'status' => 'faulty',
                'quantity' => 1,
                'image' => '/images/assets/placeholder_asset.png',
            ],
        ];

        $this->db->table('assets')->insertBatch($data);
    }
}
