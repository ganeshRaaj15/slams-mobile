<?php

namespace App\Controllers\Public;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;

class AppLinkController extends BaseController
{
    public function booking($bookingId = null)
    {
        $bookingId = (int) $bookingId;
        if ($bookingId <= 0) {
            throw PageNotFoundException::forPageNotFound('Booking not found.');
        }

        $webUrl = site_url('/dashboard/student?focus_booking=' . $bookingId);
        $appUrl = 'slamsnative://booking-detail?bookingId=' . $bookingId;
        $userAgent = $this->request->getUserAgent();
        $isMobile = $userAgent && $userAgent->isMobile();

        if (! $isMobile) {
            return redirect()->to($webUrl);
        }

        return view('public/app_links/booking_redirect', [
            'bookingId' => $bookingId,
            'appUrl' => $appUrl,
            'webUrl' => $webUrl,
        ]);
    }
}
