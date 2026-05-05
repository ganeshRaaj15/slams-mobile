<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class FacultySeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'code'    => 'FKAAB', 
                'name_bm' => 'Fakulti Kejuruteraan Awam dan Alam Bina',
                'name_en' => 'Faculty of Civil Engineering and Built Environment',
                'is_fkmp' => 0,
            ],
            [
                'code'    => 'FKEE',
                'name_bm' => 'Fakulti Kejuruteraan Elektrik dan Elektronik',
                'name_en' => 'Faculty of Electrical and Electronic Engineering',
                'is_fkmp' => 0,
            ],
            [
                'code'    => 'FKMP',
                'name_bm' => 'Fakulti Kejuruteraan Mekanikal dan Pembuatan',
                'name_en' => 'Faculty of Mechanical and Manufacturing Engineering',
                'is_fkmp' => 1, // FKMP
            ],
            [
                'code'    => 'FPTP',
                'name_bm' => 'Fakulti Pengurusan Teknologi dan Perniagaan',
                'name_en' => 'Faculty of Technology Management and Business',
                'is_fkmp' => 0,
            ],
            [
                'code'    => 'FPTV',
                'name_bm' => 'Fakulti Pendidikan Teknikal dan Vokasional',
                'name_en' => 'Faculty of Technical and Vocational Education',
                'is_fkmp' => 0,
            ],
            [
                'code'    => 'FSKTM', 
                'name_bm' => 'Fakulti Sains Komputer dan Teknologi Maklumat',
                'name_en' => 'Faculty of Computer Science and Information Technology',
                'is_fkmp' => 0,
            ],
            [
                'code'    => 'FAST',
                'name_bm' => 'Fakulti Sains Gunaan dan Teknologi',
                'name_en' => 'Faculty of Applied Sciences and Technology',
                'is_fkmp' => 0,
            ],
            [
                'code'    => 'FTK',
                'name_bm' => 'Fakulti Teknologi Kejuruteraan',
                'name_en' => 'Faculty of Engineering Technology',
                'is_fkmp' => 0,
            ],
            [
                'code'    => 'PPD', 
                'name_bm' => 'Pusat Pengajian Diploma',
                'name_en' => 'Centre for Diploma Studies',
                'is_fkmp' => 0,
            ],
        ];

        $this->db->table('faculties')->insertBatch($data);
    }
}
