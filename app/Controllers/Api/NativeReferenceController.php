<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\FacultyModel;

class NativeReferenceController extends BaseController
{
    public function faculties()
    {
        $faculties = (new FacultyModel())
            ->orderBy('name_bm', 'ASC')
            ->findAll();

        return $this->response->setJSON([
            'status' => 'success',
            'faculties' => array_map(static function (array $faculty): array {
                return [
                    'id' => (int) $faculty['id'],
                    'code' => (string) ($faculty['code'] ?? ''),
                    'name_bm' => (string) ($faculty['name_bm'] ?? ''),
                    'name_en' => (string) ($faculty['name_en'] ?? ''),
                    'is_fkmp' => (bool) ($faculty['is_fkmp'] ?? false),
                    'label' => trim(((string) ($faculty['code'] ?? '')) . ' - ' . ((string) ($faculty['name_en'] ?? ''))),
                ];
            }, $faculties),
        ]);
    }
}
