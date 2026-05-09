<?php

namespace App\Controllers\Public;

use App\Controllers\BaseController;
use App\Models\AssetModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class QrController extends BaseController
{
    public function asset($code = null)
    {
        helper('qr');

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

        $webUrl = qr_public_url('laboratories/' . $labId, $queryParams, $this->request);

        if ($open !== 1) {
            return redirect()->to($webUrl);
        }

        $serviceId = (int) ($asset['lab_service_id'] ?? 0);
        $userAgent = $this->request->getUserAgent();
        $isMobile = $userAgent && $userAgent->isMobile();

        if (! $isMobile || $serviceId <= 0) {
            return redirect()->to($webUrl);
        }

        $appQuery = http_build_query([
            'labId' => $labId,
            'serviceId' => $serviceId,
            'assetId' => (int) $asset['id'],
            'qty' => $qty > 0 ? $qty : 1,
        ]);

        return view('public/qr/asset_redirect', [
            'asset' => $asset,
            'labId' => $labId,
            'serviceId' => $serviceId,
            'appUrl' => 'slamsnative://booking' . ($appQuery !== '' ? '?' . $appQuery : ''),
            'webUrl' => $webUrl,
        ]);
    }
}
