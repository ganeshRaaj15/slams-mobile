<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

class NativeHealthController extends BaseController
{
    public function show()
    {
        return $this->response->setJSON([
            'status' => 'success',
            'service' => 'slams-mobile-api',
            'app' => 'SLAMS Mobile',
        ]);
    }
}
