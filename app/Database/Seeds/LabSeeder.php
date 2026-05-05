<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class LabSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'name' => 'Makmal Umum Bahasa Inggeris 1',
                'room' => 'B-213',
                'pic_name' => 'Dr. Ahmad Hakimi',
                'pic_email' => 'hakimi@uthm.edu.my',
                'pic_phone' => '07-1234567',
                'image' => '/images/labs/placeholder_lab.png',
                'pic_image' => '/images/pic/placeholder_pic.png',
            ],
            [
                'name' => 'Makmal Reka Bentuk Mekanikal',
                'room' => 'A1-102',
                'pic_name' => 'Ts. Nur Farah',
                'pic_email' => 'farah@uthm.edu.my',
                'pic_phone' => '07-9876543',
                'image' => '/images/labs/placeholder_lab.png',
                'pic_image' => '/images/pic/placeholder_pic.png',
            ],
            [
                'name' => 'Makmal Pneumatik & Hidraulik',
                'room' => 'C3-301',
                'pic_name' => 'Ir. Mohamad Fazli',
                'pic_email' => 'fazli@uthm.edu.my',
                'pic_phone' => '07-1112223',
                'image' => '/images/labs/placeholder_lab.png',
                'pic_image' => '/images/pic/placeholder_pic.png',
            ],
        ];

        $this->db->table('laboratories')->insertBatch($data);
    }
}
