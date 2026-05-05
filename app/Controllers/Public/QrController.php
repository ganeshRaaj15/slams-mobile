<?php

namespace App\Controllers\Public;

use App\Controllers\BaseController;
use App\Models\AssetModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class QrController extends BaseController
{
    public function asset($code = null)
    {
        $code = trim((string) $code);
        if ($code === '') {
            throw PageNotFoundException::forPageNotFound('Asset not found.');
        }

        $assetModel = new AssetModel();
        $asset = null;

        if (ctype_digit($code)) {
            $asset = $assetModel->find((int) $code);
        }

        if (! $asset) {
            $asset = $assetModel
                ->where('asset_code', strtoupper($code))
                ->first();
        }

        if (! $asset) {
            throw PageNotFoundException::forPageNotFound('Asset not found.');
        }

        $labId = (int) ($asset['lab_id'] ?? 0);
        if ($labId <= 0) {
            throw PageNotFoundException::forPageNotFound('Laboratory not found.');
        }

        $qty = (int) $this->request->getGet('qty');
        $openParam = $this->request->getGet('open');
        $open = $openParam === '0' ? 0 : 1;

        $queryParams = [
            'asset' => (int) $asset['id'],
            'open' => $open,
        ];

        if ($qty > 0) {
            $queryParams['qty'] = $qty;
        }

        $query = http_build_query($queryParams);

        return redirect()->to(site_url("/laboratories/{$labId}?{$query}"));
    }
}
